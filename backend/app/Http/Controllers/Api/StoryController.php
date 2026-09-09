<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Http\Resources\StoryCardResource;
use App\Http\Resources\StoryResource;
use App\Models\Story;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

class StoryController extends Controller
{
    public function index(Request $request): JsonResponse
    {
        $sort = $request->query('sort', 'updated');

        $query = Story::query()
            ->with('categories')
            ->withCount('chapters')
            ->withMax('chapters', 'number');

        if ($search = trim((string) $request->query('search', ''))) {
            $query->where(function ($q) use ($search) {
                $q->where('title', 'like', "%{$search}%")
                    ->orWhere('author', 'like', "%{$search}%");
            });
        }

        if ($categorySlug = $request->query('category')) {
            $query->whereHas('categories', function ($q) use ($categorySlug) {
                $q->where('slug', $categorySlug);
            });
        }

        // free=1 -> chỉ truyện đọc miễn phí TOÀN BỘ: free_chapters >= tổng số chương.
        // Yêu cầu có ít nhất 1 chương (truyện rỗng không phải "đọc free toàn bộ").
        if ($request->boolean('free')) {
            $query->has('chapters')
                ->whereRaw('stories.free_chapters >= (select count(*) from chapters where chapters.story_id = stories.id)');
        }

        if ($sort === 'views') {
            // Tab "Xếp hạng": nhiều lượt xem nhất trước.
            $query->orderByDesc('views')->orderByDesc('updated_at');
        } elseif ($sort === 'newest') {
            $query->orderByDesc('created_at');
        } else {
            $query->orderByDesc('updated_at');
        }

        $stories = $query->paginate(20)->withQueryString();

        return response()->json([
            'data' => StoryCardResource::collection($stories)->resolve(),
            'current_page' => $stories->currentPage(),
            'last_page' => $stories->lastPage(),
            'per_page' => $stories->perPage(),
            'total' => $stories->total(),
        ]);
    }

    public function show(Story $story): StoryResource
    {
        $story->load([
            'categories',
            // Không nạp `content` của toàn bộ chương (payload rất nặng);
            // danh sách chương chỉ cần id/number/title + audio_path (-> has_audio).
            'chapters' => fn ($query) => $query->select('id', 'story_id', 'number', 'title', 'audio_path'),
        ])
            ->loadCount('chapters')
            ->loadMax('chapters', 'number');

        return new StoryResource($story);
    }
}
