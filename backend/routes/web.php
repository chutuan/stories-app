<?php

use App\Http\Controllers\Admin\AuthController;
use App\Http\Controllers\Admin\CategoryController;
use App\Http\Controllers\Admin\ChapterController;
use App\Http\Controllers\Admin\DashboardController;
use App\Http\Controllers\Admin\StoryController;
use Illuminate\Support\Facades\Route;

Route::get('/', fn () => redirect('/admin'));

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
