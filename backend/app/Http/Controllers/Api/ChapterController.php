<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Http\Resources\ChapterResource;
use App\Models\Chapter;
use App\Models\Story;
use Illuminate\Support\Facades\DB;

class ChapterController extends Controller
{
    public function show(Story $story, int $number): ChapterResource
    {
        // Chương chưa tới giờ đăng phải trả 404 y như chương không tồn tại — nếu không,
        // đoán số chương là đọc trước được toàn bộ truyện.
        $chapter = Chapter::query()
            ->where('story_id', $story->id)
            ->where('number', $number)
            ->published()
            ->firstOrFail();

        // Tăng lượt xem khi đọc chương, KHÔNG chạm updated_at
        // (nếu không, việc đọc sẽ làm xáo trộn thứ tự danh sách "Cập nhật").
        // Dùng base query builder; Eloquent update() sẽ tự set updated_at nên không dùng.
        DB::table('stories')->where('id', $story->id)->update(['views' => DB::raw('views + 1')]);
        $story->views = (int) $story->views + 1;

        // prev/next cũng chỉ nhảy giữa các chương ĐÃ đăng, nếu không nút "Chương sau"
        // sẽ dẫn thẳng vào 404 ở chương đang hẹn giờ.
        $prev = Chapter::query()
            ->where('story_id', $story->id)
            ->where('number', '<', $number)
            ->published()
            ->max('number');

        $next = Chapter::query()
            ->where('story_id', $story->id)
            ->where('number', '>', $number)
            ->published()
            ->min('number');

        $chapter->setRelation('story', $story);
        $chapter->prev = $prev !== null ? (int) $prev : null;
        $chapter->next = $next !== null ? (int) $next : null;

        return new ChapterResource($chapter);
    }
}
