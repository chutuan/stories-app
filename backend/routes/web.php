<?php

use App\Http\Controllers\Admin\AuthController;
use App\Http\Controllers\Admin\CategoryController;
use App\Http\Controllers\Admin\ChapterController;
use App\Http\Controllers\Admin\DashboardController;
use App\Http\Controllers\Admin\StoryController;
use App\Http\Controllers\Web\ReactionController;
use App\Http\Controllers\Web\ReadController;
use Illuminate\Support\Facades\Route;

// Trang công khai — domain gốc phục vụ người dùng cuối và các trang bắt buộc
// để nộp App Store / Google Play (privacy, terms). Admin nằm ở /admin.
// --- Bản web đọc truyện (chạy AdSense) ---
//
// Ràng buộc model khai TẠI ĐÂY bằng {story:slug} / {category:slug} chứ KHÔNG
// đặt getRouteKeyName() trên model. Lý do sống còn: routes/api.php dùng chính
// ràng buộc đó với `show(Story $story)`, nên đổi khoá trên model sẽ biến
// /api/stories/1 thành 404 và giết luôn app đang chạy. Cú pháp {tham số:cột}
// chỉ đổi khoá cho đúng route này.
Route::get('/', [ReadController::class, 'home'])->name('public.home');
Route::get('/browse', [ReadController::class, 'browse'])->name('public.browse');
Route::get('/search', [ReadController::class, 'search'])->name('public.search');
Route::get('/genre/{category:slug}', [ReadController::class, 'category'])->name('public.category');
Route::get('/story/{story:slug}', [ReadController::class, 'story'])->name('public.story');
Route::get('/story/{story:slug}/chapter/{number}', [ReadController::class, 'chapter'])
    ->whereNumber('number')
    ->name('public.chapter');
// Thang hài lòng cuối chương. throttle chặn bấm liên tục bằng script; không có
// tài khoản nên đây là lớp bảo vệ duy nhất ngoài cookie.
Route::post('/story/{story:slug}/chapter/{number}/react', [ReactionController::class, 'store'])
    ->whereNumber('number')
    ->middleware('throttle:20,1')
    ->name('public.react');
Route::get('/og/{story:slug}.jpg', [ReadController::class, 'socialCard'])->name('public.og');
Route::get('/sitemap.xml', [ReadController::class, 'sitemap'])->name('public.sitemap');

Route::get('/about', [ReadController::class, 'about'])->name('public.about');
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

// ads.txt cho AdSense: cùng vai trò với app-ads.txt ở trên nhưng cho WEBSITE.
// Hai file KHÔNG thay thế được cho nhau — Google thu thập app-ads.txt cho quảng
// cáo trong app và ads.txt cho quảng cáo trên trang web, dù publisher ID là một.
// Thiếu ads.txt thì AdSense vẫn chạy nhưng bị hạn chế người mua và giảm doanh thu.
Route::get('/ads.txt', function () {
    $pub = config('app.admob_publisher_id');
    abort_if(blank($pub), 404);

    return response(
        "google.com, {$pub}, DIRECT, f08c47fec0942fa0\n",
        200,
        ['Content-Type' => 'text/plain; charset=UTF-8']
    );
})->name('public.ads');

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
