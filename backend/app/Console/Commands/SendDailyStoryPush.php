<?php

declare(strict_types=1);

namespace App\Console\Commands;

use App\Services\DailyStoryNotifier;
use Illuminate\Console\Command;
use Illuminate\Support\Carbon;

/**
 * Gửi thông báo "truyện mới hôm nay" cho máy nào đang tới giờ tối. Lịch chạy mỗi giờ
 * ở routes/console.php — cần deploy/stories-scheduler.timer trên server. Xem DailyStoryNotifier.
 */
class SendDailyStoryPush extends Command
{
    protected $signature = 'push:daily-story {--dry-run : chỉ in truyện được chọn và số máy tới lượt, không gửi}';

    protected $description = 'Thông báo truyện mới nhiều lượt xem nhất trong 24 giờ qua cho app di động';

    public function handle(DailyStoryNotifier $notifier): int
    {
        $stats = $notifier->run(Carbon::now(), (bool) $this->option('dry-run'));

        $this->info(sprintf(
            'story=%s due=%d sent=%d removed=%d failed=%d',
            $stats['story'] ?? '-',
            $stats['due'],
            $stats['sent'],
            $stats['removed'],
            $stats['failed'],
        ));

        return self::SUCCESS;
    }
}
