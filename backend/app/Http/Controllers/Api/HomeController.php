<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Http\Resources\StoryCardResource;
use App\Http\Resources\StoryResource;
use App\Models\Story;
use App\Models\StoryView;
use Illuminate\Database\Eloquent\Collection;
use Illuminate\Http\JsonResponse;

class HomeController extends Controller
{
    /** Số ứng viên nổi bật gửi về app: app bỏ qua truyện người dùng đã đọc hết rồi lấy truyện kế. */
    private const FEATURED_POOL = 10;

    public function index(): JsonResponse
    {
        $pool = $this->weeklyTop();

        // Tuần chưa có lượt xem nào (DB mới, dev) thì quay về truyện admin đánh dấu nổi bật.
        if ($pool->isEmpty()) {
            $marked = Story::query()
                ->with('categories')
                ->withCount('chapters')
                ->withMax('chapters', 'number')
                ->where('is_featured', true)
                ->latest('updated_at')
                ->first();
            $pool = new Collection($marked ? [$marked] : []);
        }

        $updated = Story::query()
            ->with('categories')
            ->withCount('chapters')
            ->withMax('chapters', 'number')
            ->orderByDesc('updated_at')
            ->limit(10)
            ->get();

        $newest = Story::query()
            ->with('categories')
            ->withCount('chapters')
            ->withMax('chapters', 'number')
            ->orderByDesc('created_at')
            ->limit(10)
            ->get();

        return response()->json([
            // App cũ (≤ 1.0.2) chỉ đọc `featured`; app mới đọc `featured_pool`.
            'featured' => $pool->isNotEmpty() ? (new StoryResource($pool->first()))->resolve() : null,
            'featured_pool' => StoryResource::collection($pool)->resolve(),
            'updated' => StoryCardResource::collection($updated)->resolve(),
            'newest' => StoryCardResource::collection($newest)->resolve(),
        ]);
    }

    /**
     * Truyện nhiều lượt xem nhất 7 ngày qua (hôm nay và 6 ngày trước, mọi nguồn web lẫn app),
     * nhiều nhất trước. Hoà thì truyện có tổng `views` cao hơn đứng trước.
     */
    private function weeklyTop(): Collection
    {
        $weekly = StoryView::query()
            ->select('story_id')
            ->selectRaw('sum(hits) as week_hits')
            ->where('viewed_on', '>=', now()->subDays(6)->toDateString())
            ->groupBy('story_id');

        // select() phải đứng trước withCount/withMax, nếu không nó xoá mất các cột đếm.
        return Story::query()
            ->select('stories.*')
            ->joinSub($weekly, 'week', 'week.story_id', '=', 'stories.id')
            ->with('categories')
            ->withCount('chapters')
            ->withMax('chapters', 'number')
            ->orderByDesc('week.week_hits')
            ->orderByDesc('stories.views')
            ->orderByDesc('stories.id')
            ->limit(self::FEATURED_POOL)
            ->get();
    }
}
