<?php

declare(strict_types=1);

namespace App\Console\Commands;

use App\Models\QueuedStory;
use App\Services\StoryQueue;
use Illuminate\Console\Command;
use RuntimeException;
use Throwable;

/**
 * Đưa một lô truyện vào KHO chờ đăng thay vì đăng ngay — xem StoryQueue.
 *
 * File là một mảng gói giống `stories:import`, mỗi gói thêm `source_key` (khoá của
 * truyện ở nguồn, vd. id series Blogbio) để xếp hàng lại cùng file không nhân đôi:
 *
 *   [ { "source_key": "...", "story": {...}, "chapters": [...] }, ... ]
 *
 * Bìa: `--covers=<thư mục>` chứa `<story.slug>.jpg` (khuôn 600x800 của Stories).
 * Thứ tự trong file là thứ tự đăng.
 *
 *   php artisan stories:queue-add darkchapter.json --source=darkchapter --covers=covers --noindex
 *   php artisan stories:queue-add darkchapter.json --source=darkchapter --dry
 */
class QueueStoryBundle extends Command
{
    protected $signature = 'stories:queue-add
        {path : File JSON (mảng gói có source_key)}
        {--source= : Tên nguồn, vd. darkchapter}
        {--covers= : Thư mục chứa bìa <slug>.jpg}
        {--noindex : Truyện đăng ra có noindex (đã đăng ở site khác)}
        {--dry : Chỉ kiểm tra, không ghi}';

    protected $description = 'Xếp truyện vào kho chờ đăng (mỗi ngày stories:release-queued đăng vài truyện)';

    public function handle(StoryQueue $queue): int
    {
        $source = trim((string) $this->option('source'));
        if ($source === '') {
            $this->error('Thiếu --source.');

            return self::FAILURE;
        }

        $path = (string) $this->argument('path');
        try {
            $items = json_decode((string) file_get_contents($path), true, 512, JSON_THROW_ON_ERROR);
        } catch (Throwable $e) {
            $this->error("Không đọc được {$path}: ".$e->getMessage());

            return self::FAILURE;
        }
        if (! is_array($items) || ! array_is_list($items)) {
            $this->error('File phải là một mảng gói.');

            return self::FAILURE;
        }

        $coversDir = $this->option('covers') ? rtrim((string) $this->option('covers'), '/') : null;
        $dry = (bool) $this->option('dry');
        $counts = ['added' => 0, 'updated' => 0, 'kept' => 0, 'failed' => 0];

        foreach ($items as $i => $item) {
            $key = is_array($item) ? (string) ($item['source_key'] ?? '') : '';
            $label = $key !== '' ? $key : 'gói #'.($i + 1);
            try {
                if ($key === '') {
                    throw new RuntimeException('thiếu source_key');
                }
                $slug = (string) ($item['story']['slug'] ?? '');
                $cover = $coversDir !== null && $slug !== '' ? "{$coversDir}/{$slug}.jpg" : null;
                if ($cover !== null && ! is_file($cover)) {
                    $this->warn("  ! {$label}: không có bìa {$cover}");
                    $cover = null;
                }

                if ($dry) {
                    $exists = QueuedStory::query()->where(['source' => $source, 'source_key' => $key])->value('status');
                    $counts[$exists === null ? 'added' : ($exists === 'pending' ? 'updated' : 'kept')]++;

                    continue;
                }

                [, $state] = $queue->enqueue($item, $source, $key, $cover, (bool) $this->option('noindex'));
                $counts[$state]++;
            } catch (Throwable $e) {
                $this->error("  ✗ {$label}: ".$e->getMessage());
                $counts['failed']++;
            }
        }

        $pending = QueuedStory::query()->where('status', 'pending')->count();
        $this->info(sprintf(
            '%sThêm %d, cập nhật %d, giữ nguyên (đã đăng/đã bỏ) %d, lỗi %d. Kho đang chờ: %d truyện.',
            $dry ? '[thử] ' : '',
            $counts['added'], $counts['updated'], $counts['kept'], $counts['failed'], $pending,
        ));

        return $counts['failed'] > 0 ? self::FAILURE : self::SUCCESS;
    }
}
