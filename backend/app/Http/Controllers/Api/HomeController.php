<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Http\Resources\StoryCardResource;
use App\Http\Resources\StoryResource;
use App\Models\Story;
use Illuminate\Database\Eloquent\Collection;
use Illuminate\Http\JsonResponse;

class HomeController extends Controller
{
    /**
     * Số ứng viên nổi bật gửi về app. App ≥ 1.0.4 bỏ qua truyện người dùng đã MỞ (dù chỉ một
     * chương) rồi lấy truyện kế; bản cũ chỉ bỏ truyện đã đọc hết. 20 để người đọc nhiều vẫn
     * còn truyện mới trên dải nổi bật.
     */
    private const FEATURED_POOL = 20;

    /** Số truyện mỗi hàng "Most read this week"/"Newest" của app. */
    private const SHELF_SIZE = 14;

    /**
     * Hàng "Most read this week" gửi dư: app bỏ truyện đang nằm ở dải nổi bật và truyện người dùng
     * đã đọc hết, rồi mới cắt còn SHELF_SIZE.
     */
    private const WEEKLY_SIZE = self::SHELF_SIZE + self::FEATURED_POOL;

    public function index(): JsonResponse
    {
        $weekly = $this->weeklyTop(self::WEEKLY_SIZE);
        $pool = $weekly->take(self::FEATURED_POOL)->values();

        // Tuần chưa có lượt xem nào (DB mới, dev) thì quay về truyện admin đánh dấu nổi bật.
        if ($pool->isEmpty()) {
            $marked = Story::query()
                ->with('categories')
                ->withCount('chapters')
                ->withMax('chapters', 'number')
                ->visibleOnWeb()
                ->where('is_featured', true)
                ->latest('updated_at')
                ->first();
            $pool = new Collection($marked ? [$marked] : []);
        }

        // SHELF_SIZE 14 = 7 cột x 2 dòng: hàng lấp kín iPad xoay ngang; iPhone vẫn cuộn ngang.
        // `updated` chỉ còn cho app ≤ 1.0.2; từ 1.0.3 app đọc `weekly` (rỗng thì quay về `updated`).
        $updated = Story::query()
            ->with('categories')
            ->withCount('chapters')
            ->withMax('chapters', 'number')
            ->orderByDesc('updated_at')
            ->limit(self::SHELF_SIZE)
            ->get();

        $newest = Story::query()
            ->with('categories')
            ->withCount('chapters')
            ->withMax('chapters', 'number')
            ->orderByDesc('created_at')
            ->limit(self::SHELF_SIZE)
            ->get();

        return response()->json([
            // App cũ (≤ 1.0.2) chỉ đọc `featured`; app mới đọc `featured_pool`.
            'featured' => $pool->isNotEmpty() ? (new StoryResource($pool->first()))->resolve() : null,
            'featured_pool' => StoryResource::collection($pool)->resolve(),
            'updated' => StoryCardResource::collection($updated)->resolve(),
            'newest' => StoryCardResource::collection($newest)->resolve(),
            'weekly' => StoryCardResource::collection($weekly)->resolve(),
        ]);
    }

    /**
     * Truyện nhiều lượt xem nhất 7 ngày qua — cùng định nghĩa với web (Story::scopeTopThisWeek).
     * Truyện ẩn trên web (nội dung nhà quảng cáo chặn) vẫn đọc được trong app nhưng không được
     * đặt lên dải nổi bật hay hàng "Most read this week" ngay dưới: đó là thứ đầu tiên người mới
     * cài — và người duyệt App Store — nhìn thấy.
     */
    private function weeklyTop(int $limit): Collection
    {
        return Story::query()
            ->with('categories')
            ->withCount('chapters')
            ->withMax('chapters', 'number')
            ->visibleOnWeb()
            ->topThisWeek()
            ->limit($limit)
            ->get();
    }
}
