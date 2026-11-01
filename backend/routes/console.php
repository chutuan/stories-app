<?php

use Illuminate\Foundation\Inspiring;
use Illuminate\Support\Facades\Artisan;
use Illuminate\Support\Facades\Schedule;

Artisan::command('inspire', function () {
    $this->comment(Inspiring::quote());
})->purpose('Display an inspiring quote');

// Mỗi giờ: máy nào đang tới giờ tối (theo múi giờ của máy) thì nhận truyện mới hôm nay.
// Cần `schedule:run` chạy mỗi phút trên server: deploy/stories-scheduler.timer.
Schedule::command('push:daily-story')->hourly()->withoutOverlapping();
