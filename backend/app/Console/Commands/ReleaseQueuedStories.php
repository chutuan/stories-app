<?php

declare(strict_types=1);

namespace App\Console\Commands;

use App\Models\QueuedStory;
use App\Services\StoryQueue;
use Illuminate\Console\Command;
use Throwable;

/**
 * Đăng vài truyện đầu hàng trong kho (StoryQueue). Chạy theo lịch mỗi ngày — xem
 * routes/console.php. Một truyện hỏng bị đánh dấu `failed` rồi bỏ qua, không chặn
 * các truyện sau; số truyện đăng được vẫn đủ `--count` nếu kho còn.
 *
 *   php artisan stories:release-queued --count=4
 *   php artisan stories:release-queued --dry        # xem truyện nào sẽ được đăng
 */
class ReleaseQueuedStories extends Command
{
    /** Số truyện đăng mỗi ngày theo lịch. 10 từ 02/11/2026; Tuấn giảm còn 4 ngày 10/11/2026. */
    public const DAILY = 4;

    protected $signature = 'stories:release-queued
        {--count=4 : Số truyện đăng lần này}
        {--dry : Chỉ liệt kê, không đăng}';

    protected $description = 'Đăng các truyện đầu hàng trong kho chờ';

    public function handle(StoryQueue $queue): int
    {
        $want = max(0, (int) $this->option('count'));

        if ($this->option('dry')) {
            foreach (QueuedStory::query()->pending()->limit($want)->get() as $item) {
                $this->line("  · #{$item->position} {$item->title}");
            }
            $this->info('Kho đang chờ: '.QueuedStory::query()->where('status', 'pending')->count().' truyện.');

            return self::SUCCESS;
        }

        $released = [];
        $failed = 0;
        while (count($released) < $want) {
            $item = QueuedStory::query()->pending()->first();
            if (! $item) {
                break;
            }
            try {
                $story = $queue->release($item);
                $released[] = $story->id;
                $this->info("  ✓ [{$story->id}] {$story->title}");
            } catch (Throwable $e) {
                $failed++;
                $this->error("  ✗ {$item->title}: ".$e->getMessage());
            }
        }

        if ($released !== []) {
            // Bản WebP nhỏ cho web, chỉ cho truyện vừa đăng.
            $this->call('covers:derive', ['--story' => $released]);
        }

        $left = QueuedStory::query()->where('status', 'pending')->count();
        $this->info(sprintf('Đã đăng %d truyện, lỗi %d. Kho còn %d truyện.', count($released), $failed, $left));

        return $failed > 0 ? self::FAILURE : self::SUCCESS;
    }
}
