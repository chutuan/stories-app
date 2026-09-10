<?php

declare(strict_types=1);

namespace App\Http\Controllers\Web;

use App\Http\Controllers\Controller;
use App\Models\Category;
use App\Models\Chapter;
use App\Models\Story;
use App\Services\SocialCardGenerator;
use App\Support\StructuredData;
use Illuminate\Contracts\View\View;
use Illuminate\Http\Request;
use Illuminate\Http\Response;
use Illuminate\Pagination\LengthAwarePaginator;
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

    /** Trang chủ: truyện nổi bật + hai hàng như màn Home của app. */
    public function home(): View
    {
        $featured = $this->cards()
            ->where('is_featured', true)
            ->latest('updated_at')
            ->first();

        return view('public.home', [
            'featured' => $featured,
            'updated' => $this->cards()->orderByDesc('updated_at')->limit(12)->get(),
            'newest' => $this->cards()->orderByDesc('created_at')->limit(12)->get(),
            'categories' => Category::query()->orderBy('name')->get(),
            'jsonLd' => [
                StructuredData::website(route('public.home'), route('public.search')),
                StructuredData::organization(
                    route('public.home'),
                    asset('icons/icon-512.png'),
                    config('app.support_email'),
                ),
            ],
        ]);
    }

    /** Toàn bộ kho truyện, phân trang. */
    public function browse(): View
    {
        $stories = $this->cards()->orderByDesc('updated_at')->paginate(self::PER_PAGE);
        $this->abortOnEmptyPage($stories);

        return view('public.browse', [
            'stories' => $stories,
            'categories' => Category::query()->orderBy('name')->get(),
            'category' => null,
            'jsonLd' => [StructuredData::collection(
                $stories, null, route('public.browse'), route('public.home'),
                fn (Story $s) => route('public.story', $s),
            )],
        ]);
    }

    /** Truyện trong một thể loại. */
    public function category(Category $category): View
    {
        $stories = $this->cards()
            ->whereHas('categories', fn ($q) => $q->whereKey($category->getKey()))
            ->orderByDesc('updated_at')
            ->paginate(self::PER_PAGE);

        $this->abortOnEmptyPage($stories);

        return view('public.browse', [
            'stories' => $stories,
            'categories' => Category::query()->orderBy('name')->get(),
            'category' => $category,
            'jsonLd' => [StructuredData::collection(
                $stories, $category, route('public.category', $category), route('public.home'),
                fn (Story $s) => route('public.story', $s),
            )],
        ]);
    }

    /** Trang chi tiết truyện: tóm tắt + danh sách chương. */
    public function story(Story $story): View
    {
        $story->load(['categories', 'chapters' => fn ($q) => $q->orderBy('number')]);

        return view('public.story', [
            'story' => $story,
            'jsonLd' => [
                StructuredData::book($story, route('public.story', $story), route('public.og', $story)),
                StructuredData::breadcrumbs([
                    ['name' => 'Home', 'url' => route('public.home')],
                    ['name' => 'All stories', 'url' => route('public.browse')],
                    ['name' => $story->title],
                ]),
            ],
        ]);
    }

    /**
     * Trang đọc một chương — đây là trang mang nội dung và mang tiền quảng cáo.
     *
     * Chương được tìm theo `number` trong phạm vi truyện chứ không theo id, để
     * URL đọc được và trùng khớp với cách app đánh địa chỉ chương.
     */
    public function chapter(Story $story, int $number): View
    {
        $chapter = Chapter::query()
            ->where('story_id', $story->getKey())
            ->where('number', $number)
            ->firstOrFail();

        $chapter->setRelation('story', $story);

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
        $clean = trim(preg_replace('/^\s*Chapter\s+\d+\s*[:\-–—]\s*/iu', '', (string) $chapter->title) ?? '');
        $heading = 'Chapter '.$chapter->number.($clean !== '' ? ': '.$clean : '');

        return view('public.chapter', [
            'story' => $story,
            'chapter' => $chapter,
            'prev' => $prev,
            'next' => $next,
            'paragraphs' => $paragraphs,
            'heading' => $heading,
            'metaDescription' => $this->chapterDescription($story, $chapter, $clean, $paragraphs),
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
            ['loc' => route('public.privacy'), 'priority' => '0.3', 'freq' => 'yearly'],
            ['loc' => route('public.terms'), 'priority' => '0.3', 'freq' => 'yearly'],
        ];

        foreach (Category::query()->orderBy('name')->get() as $category) {
            $urls[] = [
                'loc' => route('public.category', $category),
                'priority' => '0.6',
                'freq' => 'weekly',
            ];
        }

        $stories = Story::query()->with(['chapters' => fn ($q) => $q->orderBy('number')])->get();

        foreach ($stories as $story) {
            $urls[] = [
                'loc' => route('public.story', $story),
                'lastmod' => $story->updated_at?->toAtomString(),
                'priority' => '0.9',
                'freq' => 'weekly',
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

    /** Truy vấn dùng chung cho mọi lưới truyện — giữ một chỗ để khỏi lệch nhau. */
    private function cards()
    {
        return Story::query()
            ->with('categories')
            ->withCount('chapters')
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
