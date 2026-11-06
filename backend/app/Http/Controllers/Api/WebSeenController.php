<?php

declare(strict_types=1);

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Models\Chapter;
use App\Models\Story;
use App\Support\ViewCounter;
use Illuminate\Http\Request;
use Illuminate\Http\Response;

/**
 * Lượt đọc của bản WEB, do chính trang truyện/chương gửi về sau khi người đọc đã thật sự
 * ở lại — xem resources/views/public/partials/view-beacon.blade.php.
 *
 * Vì sao không đếm lúc GET trang như trước: tuần 30/10–05/11 web ghi 3.559 "người đọc" trên
 * máy tính trong khi GA4 chỉ thấy ~82 người thật. Phần còn lại là máy cào nội dung đội lốt
 * Chrome, mỗi IP đọc đúng một chương rồi đổi IP, nên bộ lọc theo User-Agent không bắt được.
 * Chúng không chạy JavaScript (GA4 không thấy chúng), còn tín hiệu này chỉ đi khi trang được mở trong trình duyệt,
 * hiện trên màn hình đủ lâu và người xem đã cuộn hay chạm vào trang. Bảng story_views nuôi
 * hàng "Most read this week" của app và việc chọn truyện cho thông báo hằng ngày, nên số
 * rác ở đây đẩy truyện sai lên chỗ người mới nhìn thấy đầu tiên.
 *
 * Đếm phía trình duyệt còn đúng hơn ở một chỗ nữa: trang truyện được Cloudflare cache 10 phút,
 * nên lượt GET chỉ chạm tới server khi cache hết hạn — người đọc thật bị đếm thiếu.
 */
final class WebSeenController extends Controller
{
    public function __invoke(Request $request, Story $story): Response
    {
        abort_if($story->hidden_on_web, 404);

        // Trình duyệt luôn gửi Origin kèm POST (sendBeacon cũng thế). Có Origin mà không phải
        // site này thì là trang khác gọi hộ — bỏ qua. Không có Origin (trình duyệt rất cũ) thì nhận.
        $origin = $request->headers->get('Origin');
        if ($origin !== null && parse_url($origin, PHP_URL_HOST) !== $request->getHost()) {
            return $this->done(403);
        }

        $chapter = null;
        $number = $request->query('chapter');
        if ($number !== null) {
            if (! is_string($number) || ! ctype_digit($number)) {
                return $this->done(422);
            }
            $chapter = Chapter::query()
                ->where('story_id', $story->getKey())
                ->where('number', (int) $number)
                ->first(['id', 'story_id', 'number']);
            if ($chapter === null) {
                return $this->done(404);
            }
        }

        ViewCounter::record($request, $story, $chapter, ViewCounter::SOURCE_WEB);

        return $this->done(204);
    }

    private function done(int $status): Response
    {
        return response('', $status, ['Cache-Control' => 'no-store']);
    }
}
