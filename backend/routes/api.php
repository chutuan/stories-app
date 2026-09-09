<?php

use App\Http\Controllers\Api\CategoryController;
use App\Http\Controllers\Api\ChapterController;
use App\Http\Controllers\Api\HomeController;
use App\Http\Controllers\Api\IngestController;
use App\Http\Controllers\Api\StoryController;
use Illuminate\Support\Facades\Route;

Route::get('/home', [HomeController::class, 'index']);
Route::get('/categories', [CategoryController::class, 'index']);
Route::get('/stories', [StoryController::class, 'index']);
Route::get('/stories/{story}', [StoryController::class, 'show']);
Route::get('/stories/{story}/chapters/{number}', [ChapterController::class, 'show']);

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
