<?php

declare(strict_types=1);

namespace App\Console\Commands;

use App\Models\Story;
use App\Services\FacebookPublisher;
use Illuminate\Console\Command;
use Throwable;

/**
 * Đăng MỘT truyện lên Page Facebook TunaStory. Chạy mỗi giờ theo lịch ở routes/console.php
 * (Tuấn chọn 24 bài/ngày, 02/11/2026). Xem App\Services\FacebookPublisher.
 *
 *   php artisan facebook:post-next --dry          # xem truyện kế tiếp và nội dung bài
 *   php artisan facebook:post-next                # đăng thật
 *   php artisan facebook:post-next --story=196    # đăng đúng truyện này
 */
class PostNextToFacebook extends Command
{
    protected $signature = 'facebook:post-next
        {--story= : Đăng truyện có id này thay vì truyện kế tiếp}
        {--dry : Chỉ in nội dung bài, không đăng}';

    protected $description = 'Đăng truyện kế tiếp lên Page Facebook (ảnh bìa + mở đầu, link ở bình luận)';

    public function handle(FacebookPublisher $publisher): int
    {
        $story = $this->option('story')
            ? Story::query()->find((int) $this->option('story'))
            : $publisher->next();

        if ($this->option('dry')) {
            if (! $story) {
                $this->warn('Không còn truyện nào để đăng.');

                return self::SUCCESS;
            }
            $this->line("[{$story->id}] {$story->title}");
            $this->line($publisher->caption($story, 0));
            $this->line('--- bình luận: '.$publisher->comment($story));

            return self::SUCCESS;
        }

        if (! $publisher->enabled()) {
            $this->warn('Chưa bật (FACEBOOK_ENABLED / FACEBOOK_PAGE_ID / FACEBOOK_PAGE_TOKEN) — không đăng.');

            return self::SUCCESS;
        }

        // Bình luận link của bài trước hỏng thì gửi bù trước — bài không link là bài cụt. Chạy cả
        // khi đã hết truyện để đăng, nếu không bài cuối cùng hỏng bình luận sẽ cụt mãi.
        $publisher->commentPending();

        if (! $story) {
            $this->warn('Không còn truyện nào để đăng.');

            return self::SUCCESS;
        }

        try {
            $record = $publisher->publish($story);
        } catch (Throwable $e) {
            $this->error("Không đăng được [{$story->id}] {$story->title}: ".$e->getMessage());

            return self::FAILURE;
        }

        $this->info("Đã đăng [{$story->id}] {$story->title} → {$record->post_id}".($record->comment_id ? '' : ' (CHƯA có bình luận link)'));

        return self::SUCCESS;
    }
}
