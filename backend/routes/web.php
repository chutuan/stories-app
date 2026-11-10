<?php

use App\Http\Controllers\Admin\AudioRequestController;
use App\Http\Controllers\Admin\AuthController;
use App\Http\Controllers\Admin\CategoryController;
use App\Http\Controllers\Admin\ChapterController;
use App\Http\Controllers\Admin\DashboardController;
use App\Http\Controllers\Admin\VoteController;
use App\Http\Controllers\Admin\QueueController;
use App\Http\Controllers\Admin\SettingsController;
use App\Http\Controllers\Admin\StoryController;
use App\Http\Controllers\Web\ReactionController;
use App\Http\Controllers\Web\ReadController;
use App\Services\IndexNow;
use Illuminate\Http\Request;
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
// Trang nào được cache ở biên do App\Http\Middleware\PublicPageCache quyết định
// theo TÊN route — cố tình để một chỗ thay vì rải ra đây, vì nhầm một trang có nội
// dung riêng từng người vào danh sách là người này thấy dữ liệu của người khác.
Route::get('/', [ReadController::class, 'home'])->name('public.home');
Route::get('/browse', [ReadController::class, 'browse'])->name('public.browse');
Route::get('/genre/{category:slug}', [ReadController::class, 'category'])->name('public.category');
Route::get('/story/{story:slug}', [ReadController::class, 'story'])->name('public.story');
Route::get('/about', [ReadController::class, 'about'])->name('public.about');
Route::get('/contact', [ReadController::class, 'contact'])->name('public.contact');
// Trang chuẩn biên tập: nói rõ truyện được đặt hàng, viết, soát và sửa thế nào.
// Đây là trang mà dòng khai minh bạch ở chân mọi trang trỏ tới, nên đừng đổi
// đường dẫn mà không sửa partials/disclosure.blade.php.
Route::get('/editorial', [ReadController::class, 'editorial'])->name('public.editorial');
// Trang người biên tập: byline "Edited by …" trên mọi truyện trỏ về đây. Đường dẫn cố
// định, không mang tên người, để đổi EDITOR_NAME không làm gãy link đã được index.
Route::get('/editor', [ReadController::class, 'editor'])->name('public.editor');
Route::view('/privacy', 'public.privacy')->name('public.privacy');
Route::view('/terms', 'public.terms')->name('public.terms');
Route::get('/search', [ReadController::class, 'search'])->name('public.search');
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
// RSS truyện mới: nguồn cho Pinterest/Facebook tự đăng và trình đọc RSS. Xem ReadController::feed.
Route::get('/feed.xml', [ReadController::class, 'feed'])->name('public.feed');
// File khoá IndexNow: máy tìm kiếm tải về để xác nhận lời báo URL đúng là từ chủ site.
// Ràng buộc 32 ký tự hex để không nuốt /ads.txt, /app-ads.txt. Xem App\Services\IndexNow.
Route::get('/{key}.txt', function (string $key) {
    abort_unless(hash_equals(IndexNow::key(), $key), 404);

    return response($key, 200, ['Content-Type' => 'text/plain; charset=UTF-8']);
})->where('key', '[a-f0-9]{32}')->name('public.indexnow-key');


// app-ads.txt cho AdMob: xác thực app là của mình, chống gian lận mạo danh kho
// quảng cáo. Google thu thập file này tại domain khai trong store listing.
// Chưa cấu hình ADMOB_PUBLISHER_ID -> trả 404 thay vì file rỗng gây hiểu nhầm.
// /app: đích của mã QR ở chân trang (public/images/app-qr.svg, tạo bằng
// tools/qr/make_qr_svg.swift). 302 chứ không 301 để còn đổi được đích về sau.
//
// ?src=<nhãn> do script nhúng ở trang khác gắn vào (xem /embed/app-prompt.js). Có
// IOS_PROVIDER_TOKEN thì chuyển thành link chiến dịch của App Store (pt + ct), để App
// Store Connect > App Analytics đếm được lượt tải theo từng trang; chưa có thì bỏ qua.
//
// Android (chỉ khi config app.android_public bật): ?os=android, hoặc không có ?os mà máy
// là Android (quét mã QR) -> trang Google Play. src đi vào tham số referrer dưới dạng
// utm_source, để Play Console > Thu nạp người dùng đếm lượt cài theo từng trang nhúng.
// Chưa bật thì máy Android vẫn sang App Store như trước (trang Play còn 404).
Route::get('/app', function (Request $request) {
    // ct của Apple: tối đa 40 ký tự. Lọc ký tự để không ai nhét được gì lạ vào URL.
    // ?src[]=x cho ra mảng: không phải chuỗi thì coi như không có. Dùng chung cho Play.
    $src = $request->query('src');
    $ct = is_string($src) ? substr((string) preg_replace('/[^A-Za-z0-9._-]/', '', $src), 0, 40) : '';

    $os = $request->query('os');
    $android = config('app.android_public') && ($os === 'android'
        || (! is_string($os) && str_contains((string) $request->userAgent(), 'Android')));

    if ($android) {
        $query = ['id' => config('app.android_package')];
        if ($ct !== '') {
            // http_build_query mã hoá lần nữa: Play cần referrer là MỘT chuỗi đã mã hoá.
            $query['referrer'] = http_build_query(['utm_source' => $ct, 'utm_medium' => 'app-prompt']);
        }
        $url = 'https://play.google.com/store/apps/details?'.http_build_query($query);
    } else {
        $id = config('app.ios_app_id');
        $pt = config('app.ios_provider_token');
        // Đúng dạng link mà trình tạo chiến dịch của App Store Connect sinh ra.
        $url = filled($pt) && $ct !== ''
            ? "https://apps.apple.com/app/apple-store/id{$id}?".http_build_query(['pt' => $pt, 'ct' => $ct, 'mt' => 8])
            : "https://apps.apple.com/app/id{$id}";
    }

    // Đích đổi theo máy người bấm: báo cho mọi tầng cache biết, phòng sau này có Cache
    // Rule "cache everything" ở Cloudflare thì máy iPhone không nhận nhầm link Play.
    return redirect()->away($url, 302)->setVary('User-Agent');
})->name('public.app');

// Script mời tải app để DÁN VÀO TRANG WEB KHÁC (iPhone/iPad; thêm Android khi bật
// config app.android_public):
//   <script src="https://tunastory.com/embed/app-prompt.js" async></script>
// Nguồn ở resources/embed/ chứ không ở public/: để trong public/ thì nginx trả thẳng
// file, không qua PublicPageCache, và Cloudflare không có Cache-Control để theo.
// Công tắc Android được điền vào lúc trả file (dấu /*android_public*/ trong script), để
// bật/tắt bằng .env chứ không phải sửa script. Sau khi đổi, Cloudflare giữ bản cũ tối đa
// 10 phút (s-maxage của PublicPageCache), trình duyệt thêm 1 phút.
Route::get('/embed/app-prompt.js', fn () => response(
    str_replace(
        'false /*android_public*/',
        (config('app.android_public') ? 'true' : 'false').' /*android_public*/',
        (string) file_get_contents(resource_path('embed/app-prompt.js')),
    ),
    200,
    ['Content-Type' => 'application/javascript; charset=UTF-8'],
))->name('public.embed.app-prompt');

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

        Route::get('votes', [VoteController::class, 'index'])->name('votes.index');
        Route::delete('votes/{vote}', [VoteController::class, 'destroy'])->name('votes.destroy');

        Route::get('audio', [AudioRequestController::class, 'index'])->name('audio.index');

        Route::get('queue', [QueueController::class, 'index'])->name('queue.index');
        Route::delete('queue/{item}', [QueueController::class, 'destroy'])->name('queue.destroy');

        Route::get('settings', [SettingsController::class, 'index'])->name('settings.index');
        Route::put('settings', [SettingsController::class, 'update'])->name('settings.update');

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
