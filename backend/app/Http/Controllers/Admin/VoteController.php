<?php

declare(strict_types=1);

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Models\ChapterReaction;
use App\Models\Story;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\View\View;

/**
 * Danh sách phiếu hài lòng cuối chương.
 *
 * Mục đích của thang điểm này (xem Web\ReactionController) là biết CHƯƠNG NÀO
 * ĐẮT, không phải xếp hạng công khai. Nên bảng chính ở đây gộp theo chương chứ
 * không liệt kê từng phiếu một: một danh sách phiếu thô dài vô tận không nói cho
 * ai biết chương 7 đang làm người đọc bỏ cuộc. Danh sách phiếu thô vẫn có, nhưng
 * ở dưới và chỉ 50 dòng gần nhất.
 */
class VoteController extends Controller
{
    /** Nhãn tiếng Việt cho năm mức, khớp thứ tự với năm khuôn mặt ngoài web. */
    public const LABELS = [
        1 => 'Rất tệ',
        2 => 'Không hợp',
        3 => 'Tạm ổn',
        4 => 'Thích',
        5 => 'Rất thích',
    ];

    public function index(Request $request): View
    {
        $storyId = $request->integer('story') ?: null;
        $sort = $request->query('sort', 'recent');

        // Một truy vấn gộp cho cả bảng: đếm, trung bình, và phân bố 1..5.
        // Làm ở SQL chứ không kéo hết phiếu về PHP rồi gộp, vì bảng này chỉ có
        // tăng chứ không giảm.
        $rows = ChapterReaction::query()
            ->join('chapters', 'chapters.id', '=', 'chapter_reactions.chapter_id')
            ->join('stories', 'stories.id', '=', 'chapters.story_id')
            ->when($storyId, fn ($q) => $q->where('stories.id', $storyId))
            ->groupBy('chapter_reactions.chapter_id', 'chapters.number', 'chapters.title', 'stories.id', 'stories.title', 'stories.slug')
            ->selectRaw('
                chapter_reactions.chapter_id,
                chapters.number  AS chapter_number,
                chapters.title   AS chapter_title,
                stories.id       AS story_id,
                stories.title    AS story_title,
                stories.slug     AS story_slug,
                COUNT(*)         AS votes,
                AVG(score)       AS average,
                MAX(chapter_reactions.created_at) AS last_vote,
                SUM(score = 1)   AS s1,
                SUM(score = 2)   AS s2,
                SUM(score = 3)   AS s3,
                SUM(score = 4)   AS s4,
                SUM(score = 5)   AS s5
            ');

        $rows = match ($sort) {
            'low' => $rows->orderBy('average')->orderByDesc('votes'),
            'high' => $rows->orderByDesc('average')->orderByDesc('votes'),
            'votes' => $rows->orderByDesc('votes'),
            default => $rows->orderByDesc('last_vote'),
        };

        $chapters = $rows->paginate(25)->withQueryString();

        // Tổng thể — tính trên TOÀN BỘ phiếu, không theo bộ lọc, để con số ở đầu
        // trang luôn là bức tranh chung.
        $totals = ChapterReaction::query()
            ->selectRaw('COUNT(*) AS votes, AVG(score) AS average, COUNT(DISTINCT chapter_id) AS chapters, COUNT(DISTINCT visitor_hash) AS voters')
            ->first();

        $spread = ChapterReaction::query()
            ->selectRaw('score, COUNT(*) AS n')
            ->groupBy('score')
            ->pluck('n', 'score')
            ->all();

        $recent = ChapterReaction::query()
            ->with(['chapter:id,story_id,number,title', 'chapter.story:id,title,slug'])
            ->when($storyId, fn ($q) => $q->whereHas('chapter', fn ($c) => $c->where('story_id', $storyId)))
            ->latest('created_at')
            ->limit(50)
            ->get();

        return view('admin.votes.index', [
            'chapters' => $chapters,
            'totals' => $totals,
            'spread' => $spread,
            'recent' => $recent,
            'stories' => Story::orderBy('title')->get(['id', 'title']),
            'storyId' => $storyId,
            'sort' => $sort,
            'labels' => self::LABELS,
        ]);
    }

    /**
     * Xoá một phiếu.
     *
     * Có thật là cần: phiếu thử lúc dựng tính năng, hoặc phiếu bấm nhầm, để lại
     * sẽ làm lệch trung bình của chương — mà ngưỡng hiện phải đủ 3 phiếu mới hiện
     * trung bình ra ngoài, nên một phiếu rác trong ba phiếu là lệch một phần ba.
     */
    public function destroy(ChapterReaction $vote): RedirectResponse
    {
        $vote->delete();

        return back()->with('status', 'Đã xoá phiếu.');
    }
}
