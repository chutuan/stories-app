<?php

declare(strict_types=1);

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Models\Chapter;
use App\Models\ChapterAudioRequest;
use App\Models\Story;
use Illuminate\Http\Request;
use Illuminate\View\View;

/**
 * Trang admin/audio: chương nào được bấm nghe nhiều nhất trong app, kèm nút tạo giọng đọc AI
 * cho TỪNG chương (Tuấn yêu cầu 10/11/2026 — anh tự chọn và bấm từng chương một, không tạo
 * hàng loạt vì mỗi chương là một lần trả tiền OpenAI).
 *
 * Nút "Tạo audio" dùng lại đúng route của trang chương (ChapterController::generateAudio):
 * đưa vào hàng đợi, một chương một file, chương đã có audio thì từ chối.
 */
class AudioRequestController extends Controller
{
    public function index(Request $request): View
    {
        $storyId = $request->integer('story') ?: null;
        $audio = in_array($request->query('audio'), ['missing', 'has'], true) ? $request->query('audio') : 'all';
        $sort = in_array($request->query('sort'), ['clicks', 'recent'], true) ? $request->query('sort') : 'people';

        // Gộp theo chương trong truy vấn con, rồi nối vào chapters: trả về model Chapter thật
        // nên dùng lại được has_audio / nhãn trạng thái tạo audio như trang chương.
        $perChapter = ChapterAudioRequest::query()
            ->selectRaw('chapter_id, COUNT(*) AS people, SUM(clicks) AS clicks, SUM(unlocks) AS unlocks, MAX(updated_at) AS last_request')
            ->groupBy('chapter_id');

        $rows = Chapter::query()
            ->joinSub($perChapter, 'req', 'req.chapter_id', '=', 'chapters.id')
            ->join('stories', 'stories.id', '=', 'chapters.story_id')
            ->select([
                'chapters.id', 'chapters.story_id', 'chapters.number', 'chapters.title',
                'chapters.audio_path', 'chapters.audio_status', 'chapters.audio_error',
                'stories.title as story_title', 'stories.free_chapters as story_free_chapters',
                'req.people', 'req.clicks', 'req.unlocks', 'req.last_request',
            ])
            ->when($storyId, fn ($q) => $q->where('chapters.story_id', $storyId))
            ->when($audio === 'missing', fn ($q) => $q->whereNull('chapters.audio_path'))
            ->when($audio === 'has', fn ($q) => $q->whereNotNull('chapters.audio_path'));

        $rows = match ($sort) {
            'clicks' => $rows->orderByDesc('req.clicks')->orderByDesc('req.people'),
            'recent' => $rows->orderByDesc('req.last_request'),
            default => $rows->orderByDesc('req.people')->orderByDesc('req.clicks'),
        };

        $chapters = $rows->orderBy('chapters.id')->paginate(50)->withQueryString();

        // Bức tranh chung — không theo bộ lọc.
        $totals = ChapterAudioRequest::query()
            ->selectRaw("
                COUNT(DISTINCT reader_hash) AS people,
                COUNT(DISTINCT CASE WHEN platform = 'ios' THEN reader_hash END) AS ios,
                COUNT(DISTINCT CASE WHEN platform = 'android' THEN reader_hash END) AS android,
                COALESCE(SUM(clicks), 0) AS clicks,
                COALESCE(SUM(unlocks), 0) AS unlocks,
                COUNT(DISTINCT chapter_id) AS chapters
            ")
            ->first();

        $missing = ChapterAudioRequest::query()
            ->join('chapters', 'chapters.id', '=', 'chapter_audio_requests.chapter_id')
            ->whereNull('chapters.audio_path')
            ->distinct()
            ->count('chapter_audio_requests.chapter_id');

        $stories = Story::query()
            ->whereIn('id', Chapter::query()->whereHas('audioRequests')->select('story_id'))
            ->orderBy('title')
            ->get(['id', 'title']);

        return view('admin.audio.index', [
            'chapters' => $chapters,
            'totals' => $totals,
            'missing' => $missing,
            'stories' => $stories,
            'storyId' => $storyId,
            'audio' => $audio,
            'sort' => $sort,
            'openAiReady' => (string) config('services.openai.key') !== '',
        ]);
    }
}
