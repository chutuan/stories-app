<?php

use App\Http\Controllers\Admin\AuthController;
use App\Http\Controllers\Admin\CategoryController;
use App\Http\Controllers\Admin\ChapterController;
use App\Http\Controllers\Admin\DashboardController;
use App\Http\Controllers\Admin\StoryController;
use Illuminate\Support\Facades\Route;

// Trang công khai — domain gốc phục vụ người dùng cuối và các trang bắt buộc
// để nộp App Store / Google Play (privacy, terms). Admin nằm ở /admin.
Route::view('/', 'public.home')->name('public.home');
Route::view('/privacy', 'public.privacy')->name('public.privacy');
Route::view('/terms', 'public.terms')->name('public.terms');

// app-ads.txt cho AdMob: xác thực app là của mình, chống gian lận mạo danh kho
// quảng cáo. Google thu thập file này tại domain khai trong store listing.
// Chưa cấu hình ADMOB_PUBLISHER_ID -> trả 404 thay vì file rỗng gây hiểu nhầm.
Route::get('/app-ads.txt', function () {
    $pub = config('app.admob_publisher_id');
    abort_if(blank($pub), 404);

    return response(
        "google.com, {$pub}, DIRECT, f08c47fec0942fa0\n",
        200,
        ['Content-Type' => 'text/plain; charset=UTF-8']
    );
})->name('public.app-ads');

Route::prefix('admin')->name('admin.')->group(function () {
    Route::get('login', [AuthController::class, 'showLogin'])->name('login');
    Route::post('login', [AuthController::class, 'login'])->name('login.attempt');
    Route::post('logout', [AuthController::class, 'logout'])->name('logout');

    Route::middleware('auth')->group(function () {
        Route::get('/', [DashboardController::class, 'index'])->name('dashboard');

        Route::resource('categories', CategoryController::class)
            ->except(['show'])
            ->parameters(['categories' => 'category']);

        Route::resource('stories', StoryController::class)
            ->except(['show'])
            ->parameters(['stories' => 'story']);

        Route::delete('stories/{story}/chapters/{chapter}/audio', [ChapterController::class, 'destroyAudio'])
            ->name('stories.chapters.audio.destroy');

        Route::post('stories/{story}/chapters/{chapter}/audio/generate', [ChapterController::class, 'generateAudio'])
            ->name('stories.chapters.audio.generate');

        Route::resource('stories.chapters', ChapterController::class)
            ->except(['show'])
            ->parameters(['stories' => 'story', 'chapters' => 'chapter']);
    });
});
