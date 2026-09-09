<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Http\Resources\CategoryResource;
use App\Models\Category;
use App\Support\PublicFileUrl;
use Illuminate\Http\Resources\Json\AnonymousResourceCollection;
use Illuminate\Support\Facades\DB;

class CategoryController extends Controller
{
    public function index(): AnonymousResourceCollection
    {
        $categories = Category::query()
            ->withCount('stories')
            ->orderBy('name')
            ->get();

        $covers = $this->latestCoverPerCategory();

        $categories->each(function (Category $category) use ($covers) {
            // setAttribute (chứ không phải accessor) để CategoryResource biết là
            // field đã được nạp — kể cả khi giá trị là null.
            $category->setAttribute(
                'cover_url',
                PublicFileUrl::make($covers[$category->id] ?? null, requireExists: false)
            );
        });

        return CategoryResource::collection($categories);
    }

    /**
     * Ảnh bìa đại diện mỗi thể loại: truyện MỚI NHẤT (created_at desc) có thumbnail.
     * Gom bằng 1 query duy nhất thay vì N+1.
     *
     * @return array<int, string> category_id => thumbnail path
     */
    protected function latestCoverPerCategory(): array
    {
        $rows = DB::table('category_story')
            ->join('stories', 'stories.id', '=', 'category_story.story_id')
            ->whereNotNull('stories.thumbnail')
            ->where('stories.thumbnail', '<>', '')
            ->orderByDesc('stories.created_at')
            ->orderByDesc('stories.id')
            ->get(['category_story.category_id as category_id', 'stories.thumbnail as thumbnail']);

        $covers = [];

        foreach ($rows as $row) {
            // Hàng đầu tiên của mỗi thể loại chính là truyện mới nhất.
            $covers[(int) $row->category_id] ??= (string) $row->thumbnail;
        }

        return $covers;
    }
}
