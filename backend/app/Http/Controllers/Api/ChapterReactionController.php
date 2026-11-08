<?php

declare(strict_types=1);

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Http\Controllers\Web\ReactionController;
use App\Models\Chapter;
use App\Models\ChapterReaction;
use App\Models\Story;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

/**
 * Năm khuôn mặt dưới mỗi chương trong APP — cùng thang và cùng bảng với web
 * (Web\ReactionController), để trang quản trị Votes thấy chung một con số.
 *
 * App không có tài khoản: nó tự sinh một mã ngẫu nhiên lúc bấm lần đầu, giữ trong máy và
 * gửi kèm mỗi phiếu (`reader_id`). Server chỉ lưu bản băm HMAC của "app:<mã>", nên bảng
 * phiếu không giữ thứ gì truy ngược được về máy. Gỡ app cài lại là bấm lại được — như xoá
 * cookie trên web; mục đích là biết chương nào đắt, không phải chống gian lận tuyệt đối.
 */
final class ChapterReactionController extends Controller
{
    public function store(Request $request, Story $story, int $number): JsonResponse
    {
        $data = $request->validate([
            'score' => ['required', 'integer', 'between:1,5'],
            'reader_id' => ['required', 'string', 'min:16', 'max:64', 'regex:/^[A-Za-z0-9_-]+$/'],
        ]);

        $chapter = Chapter::query()
            ->where('story_id', $story->getKey())
            ->where('number', $number)
            ->firstOrFail();

        ChapterReaction::query()->updateOrCreate(
            ['chapter_id' => $chapter->getKey(), 'visitor_hash' => ReactionController::hash('app:'.$data['reader_id'])],
            ['score' => (int) $data['score']],
        );

        return response()->json(ReactionController::totals($chapter) + ['mine' => (int) $data['score']]);
    }
}
