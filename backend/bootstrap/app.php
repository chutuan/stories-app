<?php

use Illuminate\Foundation\Application;
use Illuminate\Foundation\Configuration\Exceptions;
use Illuminate\Foundation\Configuration\Middleware;

return Application::configure(basePath: dirname(__DIR__))
    ->withRouting(
        web: __DIR__.'/../routes/web.php',
        api: __DIR__.'/../routes/api.php',
        apiPrefix: 'api',
        commands: __DIR__.'/../routes/console.php',
        health: '/up',
    )
    ->withMiddleware(function (Middleware $middleware): void {
        $middleware->redirectGuestsTo(fn () => route('admin.login'));

        // Giới hạn tần suất cho toàn bộ API công khai /api.
        // Ngưỡng được định nghĩa ở App\Providers\AppServiceProvider::configureRateLimiting()
        // (RateLimiter tên 'api') và cấu hình trong config/api.php.
        $middleware->api(append: [
            'throttle:api',
        ]);
    })
    ->withExceptions(function (Exceptions $exceptions): void {
        //
    })->create();
