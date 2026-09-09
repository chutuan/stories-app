<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Http\Resources\StoryCardResource;
use App\Http\Resources\StoryResource;
use App\Models\Story;
use Illuminate\Http\JsonResponse;

class HomeController extends Controller
{
    public function index(): JsonResponse
    {
        $featured = Story::query()
            ->with('categories')
            ->withCount('chapters')
            ->withMax('chapters', 'number')
            ->where('is_featured', true)
            ->latest('updated_at')
            ->first();

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
            'featured' => $featured ? (new StoryResource($featured))->resolve() : null,
            'updated' => StoryCardResource::collection($updated)->resolve(),
            'newest' => StoryCardResource::collection($newest)->resolve(),
        ]);
    }
}
