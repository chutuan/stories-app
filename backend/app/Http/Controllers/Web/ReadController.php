<?php

declare(strict_types=1);

namespace App\Http\Controllers\Web;

use App\Http\Controllers\Controller;
use App\Models\Category;
use App\Models\Chapter;
use App\Models\Story;
use App\Services\SocialCardGenerator;
use App\Support\GenreIntros;
use App\Support\StructuredData;
use App\Support\ViewCounter;
use Illuminate\Contracts\View\View;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Http\Response;
use Illuminate\Pagination\LengthAwarePaginator;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\Storage;

/**
 * Bản web đọc truyện tại tunastory.com.
 *
 * Lý do tồn tại: chạy Google AdSense. Doanh thu AdSense đến từ lượt xem trang có
 * nội dung đọc được và Google thu thập được, nên ở đây MỌI CHƯƠNG ĐỀU MIỄN PHÍ —
 * khác app, nơi chương 2 trở đi tốn 30 xu. Khoá nội dung trên web sẽ khiến phần
 * lớn trang trở nên trống rỗng với trình thu thập và triệt tiêu chính mục đích
 * của bản web này.
 *
 * Hệ quả kinh doanh cần biết: người đọc chịu khó tìm sẽ đọc được miễn phí trên
 * web thay vì xem quảng cáo trong app. Đổi lại, web kiếm tiền bằng AdSense và
 * kéo được lưu lượng tìm kiếm mà app không bao giờ với tới. Muốn khoá bớt thì
 * chỉnh ở đây, nhưng phải chấp nhận mất phần doanh thu AdSense tương ứng.
 *
 * Truy vấn cố ý bám sát App\Http\Controllers\Api\* để web và app không hiện ra
 * hai thứ tự khác nhau cho cùng một danh mục.
 */
class ReadController extends Controller
{
    /** Số truyện mỗi trang ở /browse và /search. */
    private const PER_PAGE = 24;

    /**
     * Trang chủ, theo bố cục quen thuộc của các web đọc truyện: truyện nổi bật cạnh
     * bảng xếp hạng, rồi lưới "Recently updated", rồi vài hàng cuộn ngang theo thể loại.
     *
     * MỘT TRUYỆN CHỈ HIỆN MỘT LẦN trên cả trang: mỗi mục loại các truyện mục trước đã
     * lấy ($shown). Kho có nhiều truyện vừa được đọc nhiều vừa mới cập nhật, không loại
     * thì cùng một bìa lặp ba bốn lần và trang chủ trông nghèo hơn thực tế.
     */
    public function home(): View
    {
        // Nổi bật = truyện nhiều lượt xem nhất 7 ngày qua, giống hệt app (Story::scopeTopThisWeek).
        // Tuần chưa có lượt xem nào (DB mới, dev) thì quay về truyện admin đánh dấu.
        $featured = $this->cards()->topThisWeek()->first()
            ?? $this->cards()->where('is_featured', true)->latest('updated_at')->first();

        $shown = collect($featured ? [$featured->id] : []);

        // Bảng xếp hạng đánh số, đứng cạnh truyện nổi bật. Theo `views` thật — không
        // bao giờ dựng số mồi (xem partials/rating). Cạnh truyện nổi bật chỉ đủ cao cho
        // 5 dòng; không có truyện nổi bật thì bảng chiếm cả hàng, chia hai cột, 10 dòng.
        $ranking = $this->cards()
            ->whereNotIn('id', $shown)
            ->orderByDesc('views')
            ->orderByDesc('id')
            ->limit($featured ? 5 : 10)
            ->get();
        $shown = $shown->merge($ranking->pluck('id'));

        $updated = $this->cards()
            ->whereNotIn('id', $shown)
            ->orderByDesc('updated_at')
            ->orderByDesc('id')
            ->limit(12)
            ->get();
        $shown = $shown->merge($updated->pluck('id'));

        // Ba thể loại đông truyện nhất, mỗi thể loại một hàng cuộn ngang. Hàng nào sau
        // khi loại trùng còn dưới 4 truyện thì bỏ — một hàng ba bìa trông như trang lỗi.
        $shelves = [];
        $topCategories = Category::query()->withWebStoryCount()->orderByDesc('stories_count')->limit(3)->get();
        foreach ($topCategories as $category) {
            $stories = $this->cards()
                ->whereHas('categories', fn ($q) => $q->whereKey($category->getKey()))
                ->whereNotIn('id', $shown)
                ->orderByDesc('views')
                ->orderByDesc('id')
                ->limit(12)
                ->get();
            if ($stories->count() >= 4) {
                $shelves[] = ['category' => $category, 'stories' => $stories];
                $shown = $shown->merge($stories->pluck('id'));
            }
        }

        return view('public.home', [
            'featured' => $featured,
            'ranking' => $ranking,
            'updated' => $updated,
            'shelves' => $shelves,
            'categories' => $this->webCategories(),
            'jsonLd' => [
                StructuredData::website(route('public.home'), route('public.search')),
                StructuredData::organization(
                    route('public.home'),
                    asset('icons/icon-512.png'),
                    config('app.support_email'),
                    route('public.editorial'),
                ),
            ],
        ]);
    }

    /**
     * Trang giới thiệu.
     *
     * Số liệu lấy động từ cơ sở dữ liệu chứ không viết cứng: kho tăng gần như mỗi
     * ngày (11 truyện hôm 10/09, 15 truyện hôm 12/09), mà một trang About ghi sai
     * số lượng còn tệ hơn là không có trang About.
     */
    public function about(): View
    {
        // Thống kê kho: đếm trong PHP, không đếm bằng SQL.
        //
        // Bản đầu dùng SUM(LENGTH - LENGTH(REPLACE(content,' ',''))) và hụt ~9%
        // (132.259 thay vì 145.939) vì đoạn văn ngăn nhau bằng \n\n chứ không
        // phải dấu cách. Vá công thức SQL cho đúng mọi loại khoảng trắng là thứ
        // vừa khó đọc vừa dễ sai lần nữa; str_word_count() thì không bàn cãi.
        //
        // Đệm một giờ: truyện mới chỉ thêm vài lần mỗi ngày, và không đáng kéo
        // 146.000 từ ra khỏi cơ sở dữ liệu cho mỗi lượt xem trang About.
        $stats = Cache::remember('about.stats', now()->addHour(), function (): array {
            $contents = Chapter::query()->whereHas('story', fn ($q) => $q->visibleOnWeb())->pluck('content');

            return [
                'stories' => Story::query()->visibleOnWeb()->count(),
                'chapters' => $contents->count(),
                'words' => $contents->sum(fn (?string $c) => str_word_count((string) $c)),
                'audio' => Story::query()
                    ->visibleOnWeb()
                    ->has('chapters')
                    ->withCount([
                        'chapters',
                        'chapters as narrated_count' => fn ($q) => $q->whereNotNull('audio_path'),
                    ])
                    ->get()
                    ->filter(fn (Story $s) => $s->chapters_count === $s->narrated_count)
                    ->count(),
            ];
        });

        // Truyện đọc miễn phí trọn vẹn: free_chapters >= tổng số chương. Cùng phép
        // lọc với tab Free trên trang chủ (StoryController::index), giữ một định
        // nghĩa duy nhất cho "free" trên toàn site.
        $freeStories = Story::query()
            ->visibleOnWeb()
            ->withCount('chapters')
            ->has('chapters')
            ->whereRaw('stories.free_chapters >= (select count(*) from chapters where chapters.story_id = stories.id)')
            ->orderBy('title')
            ->get();

        return view('public.about', [
            'storyCount' => $stats['stories'],
            'chapterCount' => $stats['chapters'],
            'wordCount' => $stats['words'],
            'audioCount' => $stats['audio'],
            'freeStories' => $freeStories,
            'jsonLd' => [
                StructuredData::aboutPage(route('public.about'), route('public.home')),
                StructuredData::breadcrumbs([
                    ['name' => 'Home', 'url' => route('public.home')],
                    ['name' => 'About'],
                ]),
            ],
        ]);
    }

    /**
     * Trang liên hệ.
     *
     * Tách hẳn thành một trang thay vì để địa chỉ email rải rác trong About và
     * Terms: người soát AdSense đi tìm ĐÚNG một trang tên "Contact" trong điều
     * hướng, và người muốn gửi khiếu nại bản quyền cũng vậy. Không có form —
     * form đòi chống spam và một hòm thư phải trực; một địa chỉ thật thì không.
     */
    public function contact(): View
    {
        return view('public.contact', [
            'jsonLd' => [
                StructuredData::contactPage(
                    route('public.contact'),
                    route('public.home'),
                    config('app.support_email'),
                ),
                StructuredData::breadcrumbs([
                    ['name' => 'Home', 'url' => route('public.home')],
                    ['name' => 'Contact'],
                ]),
            ],
        ]);
    }

    /**
     * Chuẩn biên tập.
     *
     * Nội dung trang này phải KHỚP VỚI THỰC TẾ, không phải khớp với điều dễ nghe:
     * nó khai cách truyện được đặt hàng, viết bằng công cụ AI, soát trước khi đăng,
     * và cả chỗ đặt quảng cáo trong chương (một ô, sau tối thiểu 400 từ, chương
     * dưới 600 từ thì không có — xem public/chapter.blade.php). Sửa ngưỡng ở đó
     * mà quên sửa ở đây là biến trang này thành lời khai sai.
     */
    public function editorial(): View
    {
        return view('public.editorial', [
            'jsonLd' => [
                StructuredData::editorialPage(route('public.editorial'), route('public.home')),
                StructuredData::breadcrumbs([
                    ['name' => 'Home', 'url' => route('public.home')],
                    ['name' => 'Editorial standards'],
                ]),
            ],
        ]);
    }

    /**
     * Trang người biên tập — người thật đứng tên trên mọi truyện.
     *
     * Truyện soạn với công cụ AI. Byline cũ ghi bút danh / "Tuan" trong khi trang About
     * nói đó là bút danh, và JSON-LD khai tác giả là tổ chức "Stories": ba nơi nói ba kiểu.
     * Giờ cả ba trỏ về một người có tên thật và một trang nói rõ người đó làm gì — và KHÔNG
     * làm gì (không tự tay viết từng câu). Chỉ ghi điều đúng; đừng thêm tiểu sử bịa.
     */
    public function editor(): View
    {
        $latest = $this->cards()
            ->where('noindex', false)
            ->orderByDesc('created_at')
            ->orderByDesc('id')
            ->limit(6)
            ->get();

        return view('public.editor', [
            'latest' => $latest,
            'storyCount' => Story::query()->visibleOnWeb()->count(),
            'jsonLd' => [
                StructuredData::editorPage(
                    route('public.editor'),
                    route('public.home'),
                    config('app.editor_name'),
                    config('app.support_email'),
                ),
                StructuredData::breadcrumbs([
                    ['name' => 'Home', 'url' => route('public.home')],
                    ['name' => 'About', 'url' => route('public.about')],
                    ['name' => config('app.editor_name')],
                ]),
            ],
        ]);
    }

    /**
     * Toàn bộ kho truyện, phân trang.
     *
     * Sắp theo `updated_at` RỒI `id`. Chỉ một khoá thì các truyện trùng giờ cập nhật (nạp
     * cả lô một lúc, có lô hàng chục truyện cùng giây) không có thứ tự cố định: MySQL trả
     * mỗi trang một kiểu, nên cùng một truyện hiện ở trang 2 lẫn trang 3 còn truyện khác
     * không ở trang nào — kiểm 01/11 mất 42 truyện khỏi /browse. Mọi lưới phân trang hay
     * cắt `limit` trong file này đều phải có `id` làm khoá phụ.
     */
    public function browse(): View
    {
        $stories = $this->cards()->orderByDesc('updated_at')->orderByDesc('id')->paginate(self::PER_PAGE);
        $this->abortOnEmptyPage($stories);

        return view('public.browse', [
            'stories' => $stories,
            'categories' => $this->webCategories(),
            'category' => null,
            'intro' => null,
            'jsonLd' => [StructuredData::collection(
                $stories, null, $this->pageUrl(route('public.browse'), $stories), route('public.home'),
                fn (Story $s) => route('public.story', $s),
            )],
        ]);
    }

    /** Truyện trong một thể loại. */
    public function category(Category $category): View|RedirectResponse
    {
        if ($r = $this->canonicalOr(route('public.category', $category))) {
            return $r;
        }

        $stories = $this->cards()
            ->whereHas('categories', fn ($q) => $q->whereKey($category->getKey()))
            ->orderByDesc('updated_at')
            ->orderByDesc('id')
            ->paginate(self::PER_PAGE);

        $this->abortOnEmptyPage($stories);

        return view('public.browse', [
            'stories' => $stories,
            'categories' => $this->webCategories(),
            'category' => $category,
            // Thể loại mỏng vẫn mở được (thẻ truyện có link tới nó) nhưng không cho lập chỉ mục.
            'thin' => $stories->total() < Category::WEB_MIN_STORIES,
            // Đoạn giới thiệu chỉ in ở trang 1: lặp nguyên văn trên trang 2, 3… là tạo nội
            // dung trùng giữa các trang của chính mình.
            'intro' => $stories->onFirstPage() ? GenreIntros::for($category->slug) : null,
            'jsonLd' => [StructuredData::collection(
                $stories, $category, $this->pageUrl(route('public.category', $category), $stories), route('public.home'),
                fn (Story $s) => route('public.story', $s),
            )],
        ]);
    }

    /** URL của đúng trang đang xem — khớp với canonical trong layout (trang 1 không mang ?page). */
    private function pageUrl(string $base, LengthAwarePaginator $page): string
    {
        return $page->currentPage() > 1 ? $base.'?page='.$page->currentPage() : $base;
    }

    /**
     * Chuyển hướng 301 khi URL đang dùng KHÁC dạng chuẩn.
     *
     * Đối chiếu MySQL của bảng không phân biệt hoa thường, nên
     * /story/The-Sixty-Dollar-Suit tìm thấy đúng truyện và trả 200 kèm canonical
     * tự trỏ về chính biến thể viết hoa đó — mỗi cách viết hoa là một URL trùng,
     * và số cách viết hoa thì không giới hạn.
     */
    private function canonicalOr(string $canonical): ?RedirectResponse
    {
        return url()->current() === $canonical ? null : redirect($canonical, 301);
    }

    /** Trang chi tiết truyện: tóm tắt + danh sách chương. */
    public function story(Story $story): View|RedirectResponse
    {
        abort_if($story->hidden_on_web, 404);

        if ($r = $this->canonicalOr(route('public.story', $story))) {
            return $r;
        }

        $story->load(['categories', 'chapters' => fn ($q) => $q->orderBy('number')]);
        $story->loadCount('reactions')->loadAvg('reactions', 'score');

        ViewCounter::record(request(), $story, null, ViewCounter::SOURCE_WEB);

        // "You may also like": cùng thể loại, đọc nhiều nhất trước. Truyện không có thể
        // loại nào thì lấy truyện đọc nhiều nhất của cả kho, để mục này không bao giờ trống.
        $categoryIds = $story->categories->modelKeys();
        $related = $this->cards()
            ->whereKeyNot($story->getKey())
            ->when($categoryIds, fn ($q) => $q->whereHas('categories', fn ($c) => $c->whereKey($categoryIds)))
            ->orderByDesc('views')
            ->orderByDesc('id')
            ->limit(6)
            ->get();

        // Thời gian đọc cả truyện, 230 từ/phút (tốc độ đọc văn xuôi tiếng Anh trên màn hình).
        $words = $story->chapters->sum(fn (Chapter $c) => str_word_count((string) $c->content));

        // Đường dẫn breadcrumb trong JSON-LD phải KHỚP với dòng đường dẫn người đọc
        // thấy (Home › Stories › thể loại › truyện). Bản cũ khai "All stories" và bỏ
        // thể loại: Google hiện trên kết quả tìm kiếm một đường dẫn mà trang không có.
        $genre = $story->categories->first();
        $crumbs = [
            ['name' => 'Home', 'url' => route('public.home')],
            ['name' => 'Stories', 'url' => route('public.browse')],
        ];
        if ($genre) {
            $crumbs[] = ['name' => $genre->name, 'url' => route('public.category', $genre)];
        }
        $crumbs[] = ['name' => $story->title];

        return view('public.story', [
            'story' => $story,
            'related' => $related,
            'readMinutes' => max(1, (int) round($words / 230)),
            // Cắt theo KÝ TỰ ở ranh giới từ (clip), không theo số từ: 26 từ dài có thể
            // vượt xa 155 ký tự và Google cắt ngang câu.
            'metaDescription' => $this->clip(
                $story->description ?: $story->title.' — a free short serial you can read in one sitting.',
                155,
            ),
            'jsonLd' => [
                StructuredData::book($story, route('public.story', $story), route('public.og', $story)),
                StructuredData::breadcrumbs($crumbs),
            ],
        ]);
    }

    /**
     * Trang đọc một chương — đây là trang mang nội dung và mang tiền quảng cáo.
     *
     * Chương được tìm theo `number` trong phạm vi truyện chứ không theo id, để
     * URL đọc được và trùng khớp với cách app đánh địa chỉ chương.
     */
    public function chapter(Story $story, int $number): View|RedirectResponse
    {
        abort_if($story->hidden_on_web, 404);

        if ($r = $this->canonicalOr(route('public.chapter', [$story, $number]))) {
            return $r;
        }

        $chapter = Chapter::query()
            ->where('story_id', $story->getKey())
            ->where('number', $number)
            ->firstOrFail();

        $chapter->setRelation('story', $story);

        ViewCounter::record(request(), $story, $chapter, ViewCounter::SOURCE_WEB);

        // Chương liền kề: lấy `number` gần nhất hai phía thay vì number±1, vì kho
        // có thể khuyết chương (đăng dở, hoặc chương bị gỡ) và number+1 khi đó
        // trỏ vào hư không, làm nút Next chết giữa truyện.
        $prev = Chapter::query()
            ->where('story_id', $story->getKey())
            ->where('number', '<', $number)
            ->orderByDesc('number')
            ->first(['number', 'title']);

        $next = Chapter::query()
            ->where('story_id', $story->getKey())
            ->where('number', '>', $number)
            ->orderBy('number')
            ->first(['number', 'title']);

        $paragraphs = $this->paragraphs((string) $chapter->content);
        $clean = $chapter->cleanTitle();
        $heading = 'Chapter '.$chapter->number.($clean !== '' ? ': '.$clean : '');

        // Hết truyện: gợi ý luôn vài truyện cùng thể loại thay vì chỉ một nút sang /browse.
        // Đây là lúc người đọc quyết định đi tiếp hay rời site — và là liên kết nội bộ
        // giúp Google tìm tới truyện khác từ trang chương (trang mang phần lớn lượt vào).
        $related = collect();
        if (! $next) {
            $categoryIds = $story->categories()->pluck('categories.id')->all();
            $related = $this->cards()
                ->whereKeyNot($story->getKey())
                ->when($categoryIds, fn ($q) => $q->whereHas('categories', fn ($c) => $c->whereKey($categoryIds)))
                ->orderByDesc('views')
                ->orderByDesc('id')
                ->limit(4)
                ->get();
        }

        return view('public.chapter', [
            'story' => $story,
            'chapter' => $chapter,
            'prev' => $prev,
            'next' => $next,
            // Ô "nhảy tới chương" trên thanh đọc: chỉ số và tên, không kéo nội dung.
            'chapterList' => Chapter::query()
                ->where('story_id', $story->getKey())
                ->orderBy('number')
                ->get(['number', 'title']),
            'paragraphs' => $paragraphs,
            'heading' => $heading,
            'related' => $related,
            'metaDescription' => $this->chapterDescription($story, $chapter, $clean, $paragraphs),
            'reaction' => ReactionController::summary($chapter, request()),
            'jsonLd' => [
                StructuredData::article(
                    $story, $chapter, $heading,
                    route('public.chapter', [$story, $chapter->number]),
                    route('public.story', $story),
                    route('public.og', $story),
                    $this->chapterDescription($story, $chapter, $clean, $paragraphs),
                ),
                StructuredData::breadcrumbs([
                    ['name' => 'Home', 'url' => route('public.home')],
                    ['name' => $story->title, 'url' => route('public.story', $story)],
                    ['name' => 'Chapter '.$chapter->number],
                ]),
            ],
        ]);
    }

    /**
     * Tìm kiếm.
     *
     * Khớp cả TÊN THỂ LOẠI chứ không chỉ tiêu đề/tác giả. API cho app chỉ khớp
     * title/author, nên gõ "Revenge" ra màn trắng dù kho có hẳn một thể loại tên
     * đó — trên web thì một trang kết quả rỗng vừa mất lượt xem quảng cáo vừa là
     * trang mỏng mà AdSense không thích.
     */
    public function search(Request $request): View
    {
        $q = trim((string) $request->query('q', ''));

        $stories = $q === ''
            ? new LengthAwarePaginator([], 0, self::PER_PAGE)
            : $this->cards()
                ->where(function ($query) use ($q) {
                    $like = '%'.str_replace(['%', '_'], ['\%', '\_'], $q).'%';
                    $query->where('title', 'like', $like)
                        ->orWhere('author', 'like', $like)
                        ->orWhereHas('categories', fn ($c) => $c->where('name', 'like', $like));
                })
                ->orderByDesc('updated_at')
                ->orderByDesc('id')
                ->paginate(self::PER_PAGE)
                ->withQueryString();

        return view('public.search', ['stories' => $stories, 'q' => $q]);
    }

    /**
     * sitemap.xml — mọi URL đọc được, để Google tìm ra hết các trang chương.
     *
     * Sinh trực tiếp chứ không cache: kho có 14 truyện / 70 chương, truy vấn
     * chưa tới một phần nghìn giây. Khi nào lên vài nghìn chương thì hãy cache
     * hoặc tách thành sitemap index.
     */
    public function sitemap(): Response
    {
        $urls = [
            ['loc' => route('public.home'), 'priority' => '1.0', 'freq' => 'daily'],
            ['loc' => route('public.browse'), 'priority' => '0.8', 'freq' => 'daily'],
            ['loc' => route('public.about'), 'priority' => '0.5', 'freq' => 'monthly'],
            ['loc' => route('public.editorial'), 'priority' => '0.5', 'freq' => 'monthly'],
            ['loc' => route('public.editor'), 'priority' => '0.5', 'freq' => 'monthly'],
            ['loc' => route('public.contact'), 'priority' => '0.4', 'freq' => 'monthly'],
            ['loc' => route('public.privacy'), 'priority' => '0.3', 'freq' => 'yearly'],
            ['loc' => route('public.terms'), 'priority' => '0.3', 'freq' => 'yearly'],
        ];

        foreach ($this->webCategories() as $category) {
            $urls[] = [
                'loc' => route('public.category', $category),
                'priority' => '0.6',
                'freq' => 'weekly',
            ];
        }

        // Truyện noindex không vào sitemap: khai một URL rồi bảo Google đừng lập chỉ
        // mục là hai tín hiệu ngược nhau, Search Console sẽ báo lỗi.
        $stories = Story::query()
            ->visibleOnWeb()
            ->where('noindex', false)
            ->with(['chapters' => fn ($q) => $q->orderBy('number')])
            ->get();

        foreach ($stories as $story) {
            $urls[] = [
                'loc' => route('public.story', $story),
                'lastmod' => $story->updated_at?->toAtomString(),
                'priority' => '0.9',
                'freq' => 'weekly',
                // Khai ảnh bìa để Google biết ảnh nào thuộc trang nào.
                //
                // Chỉ có tác dụng từ 13/09/2026, khi robots.txt của api mở
                // /storage/stories/ cho bot ảnh — trước đó host đó Disallow trọn
                // gói nên Googlebot-Image không lấy được ảnh nào.
                //
                // Ảnh nằm trên api.tunastory.com còn sitemap ở tunastory.com. Khai
                // chéo tên miền là hợp lệ khi robots.txt của host chứa ảnh cho
                // phép — đúng trường hợp này.
                //
                // CHỈ <image:loc>. Google đã bỏ dùng image:title và image:caption
                // từ 2022, thêm vào chỉ làm file to ra chứ không đổi được gì.
                'image' => $story->thumbnail_url,
            ];

            foreach ($story->chapters as $chapter) {
                $urls[] = [
                    'loc' => route('public.chapter', [$story, $chapter->number]),
                    'lastmod' => $chapter->updated_at?->toAtomString(),
                    'priority' => '0.7',
                    'freq' => 'monthly',
                ];
            }
        }

        return response()
            ->view('public.sitemap', ['urls' => $urls])
            ->header('Content-Type', 'application/xml; charset=UTF-8');
    }

    /**
     * Ảnh chia sẻ mạng xã hội 1200×630 của một truyện.
     *
     * Phục vụ qua route thay vì trỏ thẳng vào file trong storage, để lần đầu có ai
     * chia sẻ thì ảnh được dựng ngay lúc đó — không cần chạy lệnh dựng trước cho
     * cả kho mỗi lần thêm truyện.
     */
    public function socialCard(Story $story, SocialCardGenerator $cards): \Symfony\Component\HttpFoundation\Response
    {
        abort_if($story->hidden_on_web, 404);

        $path = $cards->forStory($story);

        return response(Storage::disk('public')->get($path), 200, [
            'Content-Type' => 'image/jpeg',
            // Trình thu thập của mạng xã hội đệm rất lâu; một tuần là đủ để đổi bìa
            // rồi chia sẻ lại mà không phải chờ hàng tháng.
            'Cache-Control' => 'public, max-age=604800',
        ]);
    }

    /**
     * Trang phân trang vượt quá trang cuối phải trả 404, không phải 200.
     *
     * `/browse?page=99` đang trả 200 kèm `index, follow` và canonical tự trỏ, hiển
     * thị "No stories here yet." — đó là soft 404, và vì N không có giới hạn nên nó
     * mở ra một không gian URL vô hạn cho Googlebot bò vào. Trang 1 vẫn hợp lệ khi
     * kho rỗng, nên chỉ chặn từ trang 2 trở đi.
     */
    private function abortOnEmptyPage(LengthAwarePaginator $page): void
    {
        if ($page->currentPage() > 1 && $page->isEmpty()) {
            abort(404);
        }
    }

    /**
     * Mô tả meta cho trang chương.
     *
     * KHÔNG dùng nguyên đoạn văn đầu tiên. Truyện thường mở bằng một dòng tiêu đề
     * IN HOA hoặc một câu thoại cụt ("Okay."), nên 55 trên 75 trang từng có mô tả
     * vô nghĩa — có trang chỉ 14 ký tự, có trang là nguyên dòng chữ hoa trang trí.
     *
     * Thay vào đó: nêu đây là chương mấy của truyện nào, rồi mới ghép đoạn văn xuôi
     * ĐẦU TIÊN ĐỦ DÀI, và cắt ở ranh giới TỪ chứ không cắt giữa chữ.
     */
    private function chapterDescription(Story $story, Chapter $chapter, string $cleanTitle, array $paragraphs): string
    {
        $lead = '';
        foreach ($paragraphs as $p) {
            $p = trim($p);
            // Bỏ qua dòng tiêu đề in hoa và câu quá ngắn — chúng không mô tả gì.
            if (mb_strlen($p) < 60 || $p === mb_strtoupper($p, 'UTF-8')) {
                continue;
            }
            $lead = $p;
            break;
        }

        // Bỏ dấu câu cuối tên chương trước khi nối ", chapter N": tên kiểu "She Said No."
        // từng cho ra "She Said No., chapter 3 of …".
        $cleanTitle = rtrim($cleanTitle, ' .!?…:;,');
        $prefix = $cleanTitle !== ''
            ? sprintf('%s, chapter %d of %s. ', $cleanTitle, $chapter->number, $story->title)
            : sprintf('Chapter %d of %s. ', $chapter->number, $story->title);

        return $this->clip($prefix.$lead, 155);
    }

    /**
     * Cắt chuỗi ở ranh giới TỪ.
     *
     * Str::limit() cắt đúng số ký tự nên chẻ đôi từ giữa chừng — 29 trang từng có
     * mô tả gãy kiểu "…she thought it was my f…". Google hiển thị nguyên chuỗi đó.
     */
    private function clip(string $text, int $max): string
    {
        $text = trim(preg_replace('/\s+/u', ' ', $text) ?? '');

        if (mb_strlen($text) <= $max) {
            return $text;
        }

        $cut = mb_substr($text, 0, $max - 1);
        $space = mb_strrpos($cut, ' ');

        return rtrim($space !== false ? mb_substr($cut, 0, $space) : $cut, ' ,.;:—-').'…';
    }

    /**
     * Thể loại lên menu, ô lọc và sitemap của web: đủ Category::WEB_MIN_STORIES truyện
     * hiện trên web. `stories_count` đã trừ truyện ẩn trên web.
     */
    private function webCategories()
    {
        return Category::query()
            ->withWebStoryCount()
            ->orderBy('name')
            ->get()
            ->filter(fn (Category $c) => $c->stories_count >= Category::WEB_MIN_STORIES)
            ->values();
    }

    /** Truy vấn dùng chung cho mọi lưới truyện — giữ một chỗ để khỏi lệch nhau. */
    private function cards()
    {
        return Story::query()
            ->visibleOnWeb()
            ->with('categories')
            ->withCount(['chapters', 'reactions'])
            ->withAvg('reactions', 'score')
            ->withMin('chapters', 'number')
            ->withMax('chapters', 'number');
    }

    /**
     * Cắt nội dung chương thành các đoạn văn.
     *
     * Nội dung lưu dạng văn bản thuần với dòng trống ngăn đoạn. Phải cắt ở
     * server rồi in ra từng thẻ <p>: nhét cả khối vào một <p> duy nhất thì mất
     * hết ngắt đoạn, còn dùng {!! !!} để giữ xuống dòng là mở đường cho XSS khi
     * nội dung do AI ở ngoài gửi vào qua đường ingest.
     *
     * @return list<string>
     */
    private function paragraphs(string $content): array
    {
        $normalised = str_replace(["\r\n", "\r"], "\n", $content);
        $parts = preg_split('/\n\s*\n/', $normalised) ?: [];

        return array_values(array_filter(array_map(
            static fn (string $p): string => trim(preg_replace('/\s*\n\s*/', ' ', $p) ?? ''),
            $parts,
        ), static fn (string $p): bool => $p !== ''));
    }
}
