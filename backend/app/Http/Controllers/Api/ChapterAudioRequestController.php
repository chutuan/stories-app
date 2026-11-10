<?php

declare(strict_types=1);

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Http\Controllers\Web\ReactionController;
use App\Models\Chapter;
use App\Models\Story;
use Illuminate\Http\Request;
use Illuminate\Http\Response;
use Illuminate\Support\Facades\DB;

/**
 * App báo một lượt bấm nghe audio (`event=click`) hoặc một lần xem hết quảng cáo để mở audio
 * chương (`event=unlock`). Ghi vào chapter_audio_requests — mỗi máy một dòng mỗi chương, cộng
 * dồn bộ đếm. Trang admin/audio đọc bảng này.
 *
 * Không trả gì (204): app bắn rồi quên, lỗi mạng cũng không chặn người nghe.
 */
final class ChapterAudioRequestController extends Controller
{
    public function store(Request $request, Story $story, int $number): Response
    {
        $data = $request->validate([
            'event' => ['required', 'in:click,unlock'],
            'reader_id' => ['required', 'string', 'min:16', 'max:64', 'regex:/^[A-Za-z0-9_-]+$/'],
            'platform' => ['nullable', 'in:ios,android'],
        ]);

        $chapterId = Chapter::query()
            ->where('story_id', $story->getKey())
            ->where('number', $number)
            ->value('id');
        abort_if($chapterId === null, 404);

        $column = $data['event'] === 'unlock' ? 'unlocks' : 'clicks';
        $now = now();

        // Một câu lệnh, không đọc-rồi-ghi: hai lượt bấm sát nhau (bấm đúp) không giẫm lên nhau.
        DB::table('chapter_audio_requests')->upsert(
            [[
                'chapter_id' => $chapterId,
                'reader_hash' => ReactionController::hash('app:'.$data['reader_id']),
                'platform' => $data['platform'] ?? null,
                'clicks' => $column === 'clicks' ? 1 : 0,
                'unlocks' => $column === 'unlocks' ? 1 : 0,
                'created_at' => $now,
                'updated_at' => $now,
            ]],
            ['chapter_id', 'reader_hash'],
            [$column => DB::raw("{$column} + 1"), 'updated_at' => $now],
        );

        return response()->noContent();
    }
}
