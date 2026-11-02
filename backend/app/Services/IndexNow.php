<?php

declare(strict_types=1);

namespace App\Services;

use App\Models\Category;
use App\Models\Chapter;
use App\Models\Story;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\Http;

/**
 * Báo cho các máy tìm kiếm dùng IndexNow (Bing, Yandex, Seznam, Naver, Yep) ngay khi có
 * URL mới hoặc vừa sửa, thay vì ngồi chờ bot tự quay lại.
 *
 * Đáng làm vì Bing là chỉ mục mà DuckDuckGo, Yahoo, Ecosia và ChatGPT Search dựa vào — một
 * lần gửi là vào được cả nhóm đó. Google KHÔNG dùng IndexNow; với Google vẫn là sitemap.
 *
 * Giao thức: POST danh sách URL kèm một khoá; máy tìm kiếm tải https://<host>/<khoá>.txt
 * để xác nhận mình là chủ host. Khoá vì vậy là thứ CÔNG KHAI, không phải bí mật.
 * https://www.indexnow.org/documentation
 */
class IndexNow
{
    private const ENDPOINT = 'https://api.indexnow.org/indexnow';

    /** Giới hạn của giao thức cho một lần gửi. */
    private const BATCH = 10000;

    /**
     * Khoá 32 ký tự hex. Không khai INDEXNOW_KEY thì suy ra từ APP_KEY: cố định giữa các lần
     * deploy mà không phải thêm gì vào .env. Băm một chiều nên không lộ gì về APP_KEY.
     */
    public static function key(): string
    {
        $key = config('services.indexnow.key');

        return is_string($key) && preg_match('/^[a-f0-9]{32}$/', $key)
            ? $key
            : substr(hash('sha256', 'indexnow|'.config('app.key')), 0, 32);
    }

    public function enabled(): bool
    {
        return (bool) config('services.indexnow.enabled');
    }

    /**
     * URL truyện và chương sửa từ $since; null = toàn bộ site (lần gửi đầu tiên).
     *
     * Cùng điều kiện với sitemap: chỉ truyện hiện trên web và không noindex. Báo một URL
     * noindex là mời bot tới đọc trang mà mình đã bảo đừng lập chỉ mục.
     *
     * @return list<string>
     */
    public function urls(?Carbon $since = null): array
    {
        $urls = [];

        if ($since === null) {
            foreach (['public.home', 'public.browse', 'public.about', 'public.editorial', 'public.editor', 'public.contact'] as $name) {
                $urls[] = $this->url(route($name, absolute: false));
            }
            // Cùng ngưỡng với sitemap (ReadController::webCategories): thể loại quá ít truyện
            // không có trang riêng.
            $categories = Category::query()->withWebStoryCount()->get()
                ->filter(fn (Category $c) => $c->stories_count >= Category::WEB_MIN_STORIES);
            foreach ($categories as $category) {
                $urls[] = $this->url(route('public.category', $category, false));
            }
        }

        $stories = Story::query()
            ->visibleOnWeb()
            ->where('noindex', false)
            ->when($since, fn ($q) => $q->where('updated_at', '>=', $since))
            ->get(['id', 'slug']);
        foreach ($stories as $story) {
            $urls[] = $this->url(route('public.story', $story, false));
        }

        $chapters = Chapter::query()
            ->whereHas('story', fn ($q) => $q->visibleOnWeb()->where('noindex', false))
            ->when($since, fn ($q) => $q->where('updated_at', '>=', $since))
            ->with('story:id,slug')
            ->orderBy('story_id')
            ->orderBy('number')
            ->get(['id', 'story_id', 'number']);
        foreach ($chapters as $chapter) {
            $urls[] = $this->url(route('public.chapter', [$chapter->story, $chapter->number], false));
        }

        return array_values(array_unique($urls));
    }

    /**
     * Gửi danh sách URL. Ném lỗi khi máy chủ IndexNow từ chối (vd. 403 = chưa tải được
     * file khoá) để lệnh gọi biết mà không đánh dấu là đã gửi.
     *
     * @param  list<string>  $urls
     */
    public function submit(array $urls): void
    {
        $site = $this->site();

        foreach (array_chunk($urls, self::BATCH) as $batch) {
            Http::timeout(15)
                ->acceptJson()
                ->post(self::ENDPOINT, [
                    'host' => parse_url($site, PHP_URL_HOST),
                    'key' => self::key(),
                    'keyLocation' => $site.'/'.self::key().'.txt',
                    'urlList' => $batch,
                ])
                ->throw();
        }
    }

    /**
     * Tên miền của bản web. KHÔNG dùng APP_URL: APP_URL là api.tunastory.com (app di động
     * và ảnh), còn trang đọc nằm ở tunastory.com. route() chạy từ lệnh console lấy APP_URL,
     * nên ở đây chỉ lấy đường dẫn rồi gắn tên miền web vào.
     */
    private function site(): string
    {
        return rtrim((string) config('services.indexnow.site'), '/');
    }

    private function url(string $path): string
    {
        return $this->site().$path;
    }
}
