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
// Lô darkchapter vào kho ngày 01/11/2026; Tuấn chọn đăng "từ mai" nên lượt đầu là 02/11.
Schedule::command('stories:release-queued', ['--count' => ReleaseQueuedStories::DAILY])
    ->dailyAt('13:00')
    ->when(fn () => now()->gte('2026-11-02'))
    ->withoutOverlapping();

// Mỗi giờ báo URL mới/sửa cho Bing & co. qua IndexNow. Phút 05 để lượt đăng truyện 13:00
// (cùng covers:derive chạy sau nó) xong trước. Xem App\Console\Commands\SubmitIndexNow.
Schedule::command('seo:indexnow')->hourlyAt(5)->withoutOverlapping();

// Mỗi giờ một truyện lên Page Facebook TunaStory (Tuấn chọn 24 bài/ngày, 02/11/2026). Phút 30 để
// không chạy cùng IndexNow (phút 05) và lượt đăng truyện 13:00. Tắt khi chưa có token — xem
// App\Services\FacebookPublisher.
Schedule::command('facebook:post-next')->hourlyAt(30)->withoutOverlapping();

// Gỡ 2FA của một tài khoản admin khi mất cả điện thoại lẫn mã khôi phục. Chạy trên server:
//   sudo -u www-data php artisan admin:2fa-reset email@...
// Lần đăng nhập sau (bằng mật khẩu) sẽ bị đưa thẳng tới trang Bảo mật để bật lại.
Artisan::command('admin:2fa-reset {email}', function (string $email) {
    $user = \App\Models\User::where('email', $email)->first();

    if (! $user) {
        $this->error("Không có tài khoản {$email}.");

        return 1;
    }

    $user->forceFill([
        'two_factor_secret' => null,
        'two_factor_recovery_codes' => null,
        'two_factor_confirmed_at' => null,
        'two_factor_last_used_step' => null,
    ]);
    // Cookie "ghi nhớ đăng nhập" cũ cũng hết hiệu lực.
    $user->setRememberToken(\Illuminate\Support\Str::random(60));
    $user->save();

    $this->info("Đã gỡ 2FA của {$email}. Đăng nhập lại để bật trên điện thoại mới.");

    return 0;
})->purpose('Gỡ xác thực 2 bước của một tài khoản admin (mất điện thoại)');
