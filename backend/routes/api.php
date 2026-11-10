<?php

use App\Http\Controllers\Api\CategoryController;
use App\Http\Controllers\Api\ChapterAudioRequestController;
use App\Http\Controllers\Api\ChapterController;
use App\Http\Controllers\Api\ChapterReactionController;
use App\Http\Controllers\Api\ConfigController;
use App\Http\Controllers\Api\HomeController;
use App\Http\Controllers\Api\IngestController;
use App\Http\Controllers\Api\PushDeviceController;
use App\Http\Controllers\Api\StoryController;
use App\Http\Controllers\Api\WebSeenController;
use Illuminate\Support\Facades\Route;

Route::get('/home', [HomeController::class, 'index']);
Route::get('/config', [ConfigController::class, 'show']);
Route::get('/categories', [CategoryController::class, 'index']);
Route::get('/stories', [StoryController::class, 'index']);
Route::get('/stories/{story}', [StoryController::class, 'show']);
Route::get('/stories/{story}/chapters/{number}', [ChapterController::class, 'show']);
// Năm khuôn mặt dưới mỗi chương trong app (từ 1.0.4). throttle như bản web: không có tài khoản
// nên đây là lớp chặn bấm hàng loạt bằng script. Xem ChapterReactionController.
Route::post('/stories/{story}/chapters/{number}/reaction', [ChapterReactionController::class, 'store'])
    ->whereNumber('number')
    ->middleware('throttle:20,1');
// Lượt bấm nghe audio / lần xem quảng cáo mở audio của app (Tuấn yêu cầu 10/11/2026) — trang
// admin/audio xếp chương theo số người muốn nghe. Xem ChapterAudioRequestController.
Route::post('/stories/{story}/chapters/{number}/audio-request', [ChapterAudioRequestController::class, 'store'])
    ->whereNumber('number')
    ->middleware('throttle:30,1');

// Lượt đọc của bản WEB (tunastory.com gọi cùng tên miền, đường dẫn tương đối): trang gửi tín hiệu
// khi người đọc đã thật sự ở lại, thay cho việc đếm lúc GET trang. Xem WebSeenController.
Route::post('/stories/{story}/seen', WebSeenController::class)->name('api.seen');

// Thông báo "truyện mới hôm nay" của app di động (bật/tắt trong app). Xem DailyStoryNotifier.
Route::post('/push-devices', [PushDeviceController::class, 'store']);
Route::delete('/push-devices', [PushDeviceController::class, 'destroy']);

/*
 * API GHI cho máy — dành cho AI viết truyện tự đăng bài.
 * Bảo vệ bằng token dùng chung (INGEST_TOKEN), xem App\Http\Middleware\VerifyIngestToken.
 * Hướng dẫn gọi từng bước: INGEST.md ở gốc kho mã.
 */
Route::middleware('ingest')->prefix('ingest')->group(function () {
    Route::get('/categories', [IngestController::class, 'categories']);
    Route::post('/categories', [IngestController::class, 'storeCategory']);
    Route::get('/stories', [IngestController::class, 'stories']);
    Route::post('/stories', [IngestController::class, 'storeStory']);
    Route::get('/stories/{story}/status', [IngestController::class, 'status']);
    Route::post('/stories/{story}/chapters', [IngestController::class, 'storeChapter']);
    Route::post('/stories/{story}/cover', [IngestController::class, 'generateCover']);
    Route::post('/stories/{story}/audio', [IngestController::class, 'generateAudio']);
    Route::post('/stories/{story}/chapters/{number}/audio', [IngestController::class, 'generateAudio']);
});
