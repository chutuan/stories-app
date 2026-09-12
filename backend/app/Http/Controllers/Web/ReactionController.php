<?php

declare(strict_types=1);

namespace App\Http\Controllers\Web;

use App\Http\Controllers\Controller;
use App\Models\Chapter;
use App\Models\ChapterReaction;
use App\Models\Story;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Cookie;
use Illuminate\Support\Str;

/**
 * Thang hài lòng một-lần-bấm ở cuối mỗi chương (giận dữ -> cười tươi).
 *
 * Site không có tài khoản, nên danh tính người bấm là một mã ngẫu nhiên lưu trong
 * cookie dài hạn. Mã đó KHÔNG lưu thẳng vào cơ sở dữ liệu mà băm bằng khoá ứng
 * dụng trước: bảng phiếu vì thế không giữ thứ gì truy ngược được về người đọc, kể
 * cả khi dữ liệu lộ ra ngoài.
 *
 * Đây không phải chống gian lận tuyệt đối — xoá cookie là bấm lại được. Nhưng mục
 * đích của thang này là biết chương nào đắt, không phải xếp hạng công khai, nên
 * ngăn bấm trùng vô tình là đủ.
 */
class ReactionController extends Controller
{
    /** Tên cookie giữ mã người đọc. */
    private const COOKIE = 'sid';

    /** Giữ hai năm: người đọc quay lại sau vài tháng vẫn thấy phiếu cũ của mình. */
    private const COOKIE_MINUTES = 60 * 24 * 730;

    public function store(Request $request, Story $story, int $number): RedirectResponse|JsonResponse
    {
        $data = $request->validate([
            'score' => ['required', 'integer', 'between:1,5'],
        ]);

        $chapter = Chapter::query()
            ->where('story_id', $story->getKey())
            ->where('number', $number)
            ->firstOrFail();

        [$visitorId, $isNew] = $this->visitorId($request);

        ChapterReaction::query()->updateOrCreate(
            ['chapter_id' => $chapter->getKey(), 'visitor_hash' => $this->hash($visitorId)],
            ['score' => $data['score']],
        );

        // Gọi bằng fetch thì trả JSON gọn. Nếu trả redirect, fetch tự đi theo và
        // tải về nguyên trang chương ~12KB chỉ để biết phiếu đã lưu.
        $response = $request->expectsJson() || $request->ajax()
            ? response()->json(self::summary($chapter, $request, $visitorId) + ['ok' => true])
            : redirect()->to(route('public.chapter', [$story, $number]).'#reaction');

        // Cookie chỉ đặt khi vừa sinh mã mới, để không gia hạn hạn dùng mỗi lần bấm.
        return $isNew
            ? $response->withCookie(Cookie::make(self::COOKIE, $visitorId, self::COOKIE_MINUTES,
                null, null, true, true, false, 'lax'))
            : $response;
    }

    /**
     * Mã người đọc từ cookie, sinh mới nếu chưa có.
     *
     * @return array{0: string, 1: bool} [mã, có phải vừa sinh mới không]
     */
    public static function visitorId(Request $request): array
    {
        $existing = (string) $request->cookie(self::COOKIE, '');

        return $existing !== ''
            ? [$existing, false]
            : [Str::random(32), true];
    }

    /**
     * Băm mã người đọc bằng khoá ứng dụng.
     *
     * hash_hmac chứ không phải hash trần: mã trong cookie ngắn và do ta sinh ra,
     * nên nếu băm không khoá thì ai có bảng dữ liệu cũng dựng được bảng tra ngược.
     */
    public static function hash(string $visitorId): string
    {
        return hash_hmac('sha256', $visitorId, (string) config('app.key'));
    }

    /**
     * Tổng hợp phiếu của một chương, kèm phiếu của chính người đang xem.
     *
     * @return array{count: int, average: float|null, mine: int|null}
     */
    public static function summary(Chapter $chapter, Request $request, ?string $visitorId = null): array
    {
        $rows = ChapterReaction::query()
            ->where('chapter_id', $chapter->getKey())
            ->selectRaw('COUNT(*) AS c, AVG(score) AS a')
            ->first();

        $count = (int) ($rows->c ?? 0);

        // Người bấm lần đầu: cookie mới chỉ ĐANG được đặt trong response, request
        // hiện tại chưa có nó. Đọc lại từ request lúc này sẽ sinh ra một mã mới
        // khác và tra trượt chính phiếu vừa ghi, nên nơi gọi phải truyền mã vào.
        $visitorId ??= self::visitorId($request)[0];

        $mine = $count === 0 ? null : ChapterReaction::query()
            ->where('chapter_id', $chapter->getKey())
            ->where('visitor_hash', self::hash($visitorId))
            ->value('score');

        return [
            'count' => $count,
            // Trung bình chỉ hiện khi đã có từ 3 phiếu: một phiếu duy nhất không
            // nói lên điều gì, mà hiện "1,0/5" dưới một chương vừa đăng thì vừa
            // gây hiểu nhầm vừa làm nản người đọc tiếp theo.
            'average' => $count >= 3 ? round((float) $rows->a, 1) : null,
            'mine' => $mine ? (int) $mine : null,
        ];
    }
}
