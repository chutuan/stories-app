<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Http\Resources\ChapterResource;
use App\Models\Chapter;
use App\Models\Story;
use App\Support\ViewCounter;
use Illuminate\Support\Facades\DB;

class ChapterController extends Controller
{
    public function show(Story $story, int $number): ChapterResource
    {
        $chapter = Chapter::query()
            ->where('story_id', $story->id)
            ->where('number', $number)
            ->firstOrFail();

        // Tăng lượt xem khi đọc chương, KHÔNG chạm updated_at
        // (nếu không, việc đọc sẽ làm xáo trộn thứ tự danh sách "Cập nhật").
        // Dùng base query builder; Eloquent update() sẽ tự set updated_at nên không dùng.
        DB::table('stories')->where('id', $story->id)->update(['views' => DB::raw('views + 1')]);
        $story->views = (int) $story->views + 1;

        // Bộ đếm ở trên là cột `stories.views`, và app ĐANG hiển thị nó: có hẳn
        // tab "Xếp hạng" sắp theo nó, cộng dòng lượt xem ở màn hình truyện. Nên
        // giữ nguyên, không đổi ý nghĩa. Bảng story_views bên dưới chạy song song
        // và mới là chỗ tách được nguồn app / web cho trang quản trị.
        ViewCounter::record(request(), $story, $chapter, ViewCounter::SOURCE_APP);

        $prev = Chapter::query()
            ->where('story_id', $story->id)
            ->where('number', '<', $number)
            ->max('number');

        $next = Chapter::query()
            ->where('story_id', $story->id)
            ->where('number', '>', $number)
            ->min('number');

        $chapter->setRelation('story', $story);
        $chapter->prev = $prev !== null ? (int) $prev : null;
        $chapter->next = $next !== null ? (int) $next : null;

        return new ChapterResource($chapter);
    }
}
