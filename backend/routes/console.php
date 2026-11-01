<?php

use App\Console\Commands\ReleaseQueuedStories;
use Illuminate\Foundation\Inspiring;
use Illuminate\Support\Facades\Artisan;
use Illuminate\Support\Facades\Schedule;

Artisan::command('inspire', function () {
    $this->comment(Inspiring::quote());
})->purpose('Display an inspiring quote');

// Mỗi giờ: máy nào đang tới giờ tối (theo múi giờ của máy) thì nhận truyện mới hôm nay.
// Cần `schedule:run` chạy mỗi phút trên server: deploy/stories-scheduler.timer.
Schedule::command('push:daily-story')->hourly()->withoutOverlapping();

// Mỗi ngày đăng vài truyện đầu hàng trong kho chờ (StoryQueue). 13:00 UTC = sáng sớm ở Mỹ:
// truyện kịp có lượt xem trước khi push:daily-story chọn "truyện mới 24h" lúc 19–22h giờ máy.
Schedule::command('stories:release-queued', ['--count' => ReleaseQueuedStories::DAILY])
    ->dailyAt('13:00')
    ->withoutOverlapping();
