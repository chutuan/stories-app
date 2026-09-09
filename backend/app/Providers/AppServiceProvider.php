<?php

namespace App\Providers;

use Illuminate\Cache\RateLimiting\Limit;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;
use Illuminate\Support\Facades\RateLimiter;
use Illuminate\Support\ServiceProvider;

class AppServiceProvider extends ServiceProvider
{
    /**
     * Register any application services.
     */
    public function register(): void
    {
        //
    }

    /**
     * Bootstrap any application services.
     */
    public function boot(): void
    {
        // API JSON shapes in SPEC.md use bare arrays / top-level objects,
        // so disable the default "data" wrapping for single resources and collections.
        JsonResource::withoutWrapping();

        $this->configureRateLimiting();
    }

    /**
     * Giới hạn tần suất cho nhóm route "api" (middleware throttle:api).
     *
     * Tính theo IP của client. Endpoint đọc nội dung chương được nới rộng hơn
     * vì người dùng lật chương liên tục khi đang đọc, nhưng vẫn có trần cứng.
     */
    private function configureRateLimiting(): void
    {
        RateLimiter::for('api', function (Request $request): Limit {
            $isChapterRead = $request->is('api/stories/*/chapters/*');

            $perMinute = (int) config(
                $isChapterRead ? 'api.rate_limit.read' : 'api.rate_limit.default',
                $isChapterRead ? 180 : 60
            );

            if ($perMinute < 1) {
                $perMinute = $isChapterRead ? 180 : 60;
            }

            // Tách "xô đếm" riêng cho hai nhóm: đọc chương nhiều không làm
            // khoá các endpoint còn lại và ngược lại.
            $bucket = $isChapterRead ? 'read' : 'default';

            return Limit::perMinute($perMinute)
                ->by($bucket.'|'.($request->ip() ?: 'unknown'))
                ->response(function (Request $request, array $headers) {
                    return response()->json([
                        'message' => 'Bạn đang gửi quá nhiều yêu cầu. Vui lòng thử lại sau ít phút.',
                    ], 429, $headers);
                });
        });
    }
}
