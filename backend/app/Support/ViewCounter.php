<?php

declare(strict_types=1);

namespace App\Support;

use App\Models\Chapter;
use App\Models\Story;
use App\Models\StoryView;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;

/**
 * Đếm lượt xem truyện, tách riêng ứng dụng di động và trình duyệt.
 *
 * KHÔNG DÙNG COOKIE, có chủ ý. Cookie đếm chính xác hơn, nhưng đặt cookie cho mọi
 * khách ghé qua là thêm một thứ phải khai trong chính sách riêng tư và phải xin
 * phép ở châu Âu — trả giá đó cho một con số thống kê nội bộ thì không đáng. Thay
 * vào đó lấy dấu IP + User-Agent + ngày rồi băm bằng khoá ứng dụng: bảng không giữ
 * địa chỉ IP của ai cả, và vì có ngày trong đầu vào nên dấu tự đổi mỗi ngày, không
 * lần được một người qua nhiều ngày kể cả khi dữ liệu lộ ra.
 *
 * Đánh đổi: mạng di động Việt Nam dùng NAT nhiều nên vài người chung một IP và
 * cùng loại máy sẽ bị đếm thành một. Với một con số để biết truyện nào được đọc
 * thì sai số đó chấp nhận được; muốn số liệu chuẩn thì đã có GA4.
 */
final class ViewCounter
{
    /**
     * Bot thì không phải người đọc.
     *
     * Googlebot bò khắp site nên nếu đếm cả nó, truyện nào cũng có lượt xem đều
     * nhau và con số mất hết ý nghĩa. nginx đã chặn đám bot xấu rồi, danh sách này
     * lọc nốt đám bot tử tế (và đám tự khai) còn lọt vào.
     */
    private const BOT = '/bot|crawl|spider|slurp|curl|wget|python|java|go-http|okhttp|headless|lighthouse|preview|scan|monitor|uptime|facebookexternalhit|whatsapp|telegram|Expo/i';

    /** Máy nào là điện thoại/máy tính bảng. */
    private const MOBILE = '/Android|iPhone|iPad|iPod|Mobile|Opera Mini|IEMobile|Silk/i';

    public const SOURCE_APP = 'app';
    public const SOURCE_WEB = 'web';

    /**
     * Ghi một lượt xem. KHÔNG BAO GIỜ được làm hỏng trang.
     *
     * Bọc trong try/catch vì đây là thống kê phụ: nếu DB nghẽn hay bảng chưa
     * migrate, người đọc vẫn phải đọc được truyện và app vẫn phải trả JSON. Lỗi
     * ghi vào log rồi đi tiếp.
     */
    public static function record(Request $request, Story $story, ?Chapter $chapter, string $source): void
    {
        try {
            $ua = (string) $request->userAgent();

            // Ứng dụng di động không bị lọc bot: nó gọi bằng thư viện mạng của hệ
            // điều hành, chuỗi User-Agent có cả "CFNetwork" lẫn "okhttp" nên sẽ
            // dính ngay chính danh sách trên nếu không bỏ qua.
            if ($source === self::SOURCE_WEB && ($ua === '' || preg_match(self::BOT, $ua))) {
                return;
            }

            $today = now()->toDateString();

            $keys = [
                'story_id' => $story->getKey(),
                'chapter_id' => $chapter?->getKey() ?? 0,
                'source' => $source,
                'visitor_hash' => hash_hmac(
                    'sha256',
                    $request->ip().'|'.$ua.'|'.$today,
                    (string) config('app.key'),
                ),
                'viewed_on' => $today,
            ];

            // Dòng đã có thì chỉ cộng hits — không dùng firstOrCreate rồi save,
            // vì hai request cùng lúc sẽ chèn trùng và vỡ khoá duy nhất.
            $updated = StoryView::query()->where($keys)->increment('hits');

            if ($updated === 0) {
                StoryView::query()->insertOrIgnore($keys + [
                    'device' => $source === self::SOURCE_APP || preg_match(self::MOBILE, $ua) ? 'mobile' : 'desktop',
                    'hits' => 1,
                    'created_at' => now(),
                    'updated_at' => now(),
                ]);
            }
        } catch (\Throwable $e) {
            Log::warning('Không ghi được lượt xem: '.$e->getMessage());
        }
    }

    /**
     * Tổng hợp lượt xem theo nguồn cho một tập truyện.
     *
     * Trả về [story_id => ['app'=>n, 'web_mobile'=>n, 'web_desktop'=>n, 'total'=>n, 'unique'=>n]].
     * Gom một truy vấn cho cả trang danh sách thay vì mỗi dòng một truy vấn.
     *
     * @param  array<int>  $storyIds
     * @return array<int, array<string, int>>
     */
    public static function summaryFor(array $storyIds): array
    {
        if ($storyIds === []) {
            return [];
        }

        $rows = StoryView::query()
            ->selectRaw('story_id, source, device, SUM(hits) AS total, COUNT(*) AS uniq')
            ->whereIn('story_id', $storyIds)
            ->groupBy('story_id', 'source', 'device')
            ->get();

        $out = [];
        foreach ($rows as $row) {
            $bucket = $row->source === self::SOURCE_APP ? 'app' : 'web_'.$row->device;
            $id = (int) $row->story_id;
            $out[$id] ??= ['app' => 0, 'web_mobile' => 0, 'web_desktop' => 0, 'total' => 0, 'unique' => 0];
            $out[$id][$bucket] += (int) $row->total;
            $out[$id]['total'] += (int) $row->total;
            $out[$id]['unique'] += (int) $row->uniq;
        }

        return $out;
    }
}
