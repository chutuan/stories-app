<?php

declare(strict_types=1);

namespace App\Console\Commands;

use App\Models\Story;
use Illuminate\Console\Command;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Str;

/**
 * Đặt lại slug truyện theo tiêu đề hiện tại; URL cũ 301 sang URL mới.
 *
 * VÌ SAO: tiêu đề kiểu Facebook đã được rút gọn (01/11/2026) nhưng slug giữ nguyên, nên
 * 140 truyện mang URL chẳng liên quan gì tới tên, và 222 slug dài quá 80 ký tự — Google
 * hiện nguyên đường dẫn đó trên kết quả tìm kiếm. Tuấn chọn đổi, có 301 vĩnh viễn.
 *
 * Slug cũ được Story::booted() ghi vào story_slug_redirects; route {story:slug} tra bảng
 * đó (Story::resolveRouteBinding). KHÔNG đổi `updated_at` (thứ tự /browse và lastmod
 * trong sitemap không được nhảy chỉ vì đổi URL). Ảnh bìa giữ đường dẫn cũ — `thumbnail`
 * lưu đường dẫn file, không suy ra từ slug; ảnh chia sẻ og/{slug}.jpg cũ bị xoá, lần
 * đầu có người mở URL mới thì SocialCardGenerator dựng lại.
 *
 * Mặc định chỉ truyện hiện trên web: truyện ẩn trên web (hidden_on_web) chỉ còn trong
 * app, mà app gọi truyện theo id. Chạy --dry-run trước và đọc danh sách.
 */
class ReslugStories extends Command
{
    /** Độ dài tối đa của slug mới, cắt ở ranh giới từ. */
    private const MAX = 60;

    protected $signature = 'stories:reslug
        {--dry-run : chỉ in danh sách đổi, không ghi gì}
        {--story=* : chỉ những truyện này (id)}
        {--include-hidden : cả truyện ẩn trên web}';

    protected $description = 'Đặt lại slug truyện theo tiêu đề (≤ 60 ký tự); slug cũ tự chuyển hướng 301';

    public function handle(): int
    {
        $dry = (bool) $this->option('dry-run');
        $ids = array_map('intval', (array) $this->option('story'));

        $stories = Story::query()
            ->when(! $this->option('include-hidden'), fn ($q) => $q->visibleOnWeb())
            ->when($ids, fn ($q) => $q->whereKey($ids))
            ->orderBy('id')
            ->get();

        $rows = [];
        foreach ($stories as $story) {
            $new = $this->slugFor($story);
            if ($new === $story->slug) {
                continue;
            }

            $rows[] = [$story->id, $story->slug, $new];
            if ($dry) {
                continue;
            }

            $old = $story->slug;
            $story->slug = $new;
            $story->timestamps = false;
            $story->save();

            Storage::disk('public')->delete('og/'.$old.'.jpg');
        }

        $this->table(['id', 'slug cũ', 'slug mới'], $rows);
        $this->info(($dry ? '[dry-run] Sẽ đổi ' : 'Đã đổi ').count($rows).'/'.$stories->count().' truyện.');

        return self::SUCCESS;
    }

    /**
     * Slug ngắn từ tiêu đề, không đụng slug của truyện khác hay slug cũ đang chuyển hướng
     * về truyện khác. Slug hiện tại đã đúng (kể cả dạng có hậu tố -2) thì giữ nguyên.
     */
    private function slugFor(Story $story): string
    {
        $base = Str::slug((string) $story->title);
        if (strlen($base) > self::MAX) {
            $cut = substr($base, 0, self::MAX + 1);
            $base = str_contains($cut, '-') ? substr($cut, 0, (int) strrpos($cut, '-')) : substr($base, 0, self::MAX);
        }
        if ($base === '') {
            $base = 'story-'.$story->id;
        }

        $candidate = $base;
        for ($n = 2; Story::slugTaken($candidate, $story->id); $n++) {
            $candidate = $base.'-'.$n;
        }

        return $candidate;
    }
}
