<?php

declare(strict_types=1);

namespace App\Support;

use App\Models\Setting;
use Illuminate\Support\Facades\Cache;
use Throwable;

/**
 * Cấu hình chỉnh được từ /admin/settings, app di động đọc qua GET /api/config.
 *
 * CHỈ để chỉnh chính sách chung cho MỌI người dùng. Đừng thêm khoá kiểu "đang có
 * Apple duyệt thì bật cái này" — app chạy khác đi trong lúc duyệt là đúng thứ
 * Guideline 2.3.1 cấm (tính năng ẩn, hành vi khác với bản đã duyệt), và Apple đã
 * khoá tài khoản developer vì chuyện đó. Mỗi khoá ở đây phải là thứ mà mô tả trên
 * App Store và ghi chú gửi reviewer vẫn đúng dù nó đang ở giá trị nào.
 */
final class AppSettings
{
    private const CACHE_KEY = 'app_settings:v1';

    /**
     * Khoá hợp lệ -> [kiểu, mặc định, min, max]. Khoá chưa có trong bảng settings
     * thì dùng mặc định; giá trị lưu sai kiểu hoặc ngoài khoảng cũng rơi về mặc định.
     */
    public const DEFINITIONS = [
        // Không chiếu được quảng cáo thưởng (no-fill, chặn quảng cáo, lỗi mạng tới
        // Google) mà người đọc đang THIẾU xu để mở chương trước mặt: có cấp bù cho
        // đủ giá một chương không. Đây là thứ giữ cho không ai bị kẹt vì mạng quảng
        // cáo — đúng lỗi làm Apple từ chối bản 1.0(3) theo Guideline 2.1(a). Tắt thì
        // người thiếu xu mà không có quảng cáo chỉ còn điểm danh và mốc thời gian đọc.
        'ad_fallback_enabled' => ['bool', true, null, null],

        // Số lần cấp bù như trên, tối đa mỗi ngày trên mỗi máy. Mỗi lần chỉ cấp đủ
        // để mở MỘT chương (tối đa 30 xu), nên 2 lần/ngày = tối đa 2 chương không
        // cần quảng cáo. Đếm ở phía app nên người cố tình (đổi giờ máy) vẫn lách
        // được; mục đích là chặn việc bấm liên tục, không phải chống gian lận.
        'ad_fallback_daily_limit' => ['int', 2, 0, 20],
    ];

    /** @return array<string, bool|int> toàn bộ khoá, đã ép kiểu và điền mặc định */
    public static function all(): array
    {
        // Không dùng Cache::rememberForever: nó lưu cả kết quả của nhánh lỗi, và khi
        // đó một lần DB chập chờn sẽ khoá app vào giá trị mặc định cho tới lần lưu kế.
        $stored = Cache::get(self::CACHE_KEY);
        if (! is_array($stored)) {
            try {
                $stored = Setting::query()->pluck('value', 'key')->all();
                Cache::forever(self::CACHE_KEY, $stored);
            } catch (Throwable) {
                // Bảng chưa tạo (chưa chạy migrate) hoặc DB tạm lỗi: dùng mặc định
                // cho lần này, không cache.
                $stored = [];
            }
        }

        $values = [];
        foreach (self::DEFINITIONS as $key => [$type, $default, $min, $max]) {
            $values[$key] = self::cast($stored[$key] ?? null, $type, $default, $min, $max);
        }

        return $values;
    }

    public static function get(string $key): bool|int
    {
        return self::all()[$key];
    }

    /** @param array<string, mixed> $values chỉ ghi các khoá có trong DEFINITIONS */
    public static function update(array $values): void
    {
        foreach ($values as $key => $value) {
            if (! array_key_exists($key, self::DEFINITIONS)) {
                continue;
            }
            [$type] = self::DEFINITIONS[$key];
            $stored = $type === 'bool' ? ($value ? '1' : '0') : (string) (int) $value;
            Setting::query()->updateOrCreate(['key' => $key], ['value' => $stored]);
        }
        Cache::forget(self::CACHE_KEY);
    }

    /**
     * Dạng gửi cho app. Gom theo tính năng thay vì trả khoá phẳng, để app thêm
     * nhóm mới về sau mà không đụng tới nhóm cũ.
     *
     * @return array<string, array<string, bool|int>>
     */
    public static function forApp(): array
    {
        $s = self::all();

        return [
            'ad_fallback' => [
                'enabled' => $s['ad_fallback_enabled'],
                'daily_limit' => $s['ad_fallback_daily_limit'],
            ],
        ];
    }

    private static function cast(mixed $raw, string $type, bool|int $default, ?int $min, ?int $max): bool|int
    {
        if ($raw === null) {
            return $default;
        }
        if ($type === 'bool') {
            return in_array((string) $raw, ['1', 'true'], true);
        }
        if (! is_numeric($raw)) {
            return $default;
        }
        $n = (int) $raw;
        if (($min !== null && $n < $min) || ($max !== null && $n > $max)) {
            return $default;
        }

        return $n;
    }
}
