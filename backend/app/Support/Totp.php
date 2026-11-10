<?php

declare(strict_types=1);

namespace App\Support;

use BaconQrCode\Renderer\Image\SvgImageBackEnd;
use BaconQrCode\Renderer\ImageRenderer;
use BaconQrCode\Renderer\RendererStyle\RendererStyle;
use BaconQrCode\Writer;

/**
 * Mã 6 số theo thời gian (TOTP, RFC 6238) — loại mà Google Authenticator, 1Password, Authy…
 * đều đọc được: HMAC-SHA1, bước 30 giây, 6 chữ số.
 *
 * Tự viết thay vì kéo thêm gói: thuật toán chỉ vài dòng, có bộ số kiểm của RFC để thử
 * (tests/Unit/TotpTest.php), và bớt một gói là bớt một chỗ phải theo dõi lỗ hổng.
 */
final class Totp
{
    public const DIGITS = 6;

    public const PERIOD = 30;

    /**
     * Nhận mã của bước liền trước/liền sau. Điện thoại lệch giờ vài chục giây vẫn đăng nhập
     * được, còn cửa sổ đoán mò chỉ là 3 mã trong 1 triệu mỗi lần thử.
     */
    public const WINDOW = 1;

    private const BASE32 = 'ABCDEFGHIJKLMNOPQRSTUVWXYZ234567';

    /** Khoá 160 bit — đúng độ dài RFC 4226 khuyên dùng cho HMAC-SHA1. */
    public static function generateSecret(): string
    {
        return self::base32Encode(random_bytes(20));
    }

    public static function timestep(?int $time = null): int
    {
        return intdiv($time ?? time(), self::PERIOD);
    }

    /** Mã của một bước thời gian cụ thể. */
    public static function code(string $secret, int $timestep): string
    {
        $hash = hash_hmac('sha1', pack('J', $timestep), self::base32Decode($secret), true);
        $offset = ord($hash[19]) & 0x0F;
        $value = ((ord($hash[$offset]) & 0x7F) << 24)
            | (ord($hash[$offset + 1]) << 16)
            | (ord($hash[$offset + 2]) << 8)
            | ord($hash[$offset + 3]);

        return str_pad((string) ($value % (10 ** self::DIGITS)), self::DIGITS, '0', STR_PAD_LEFT);
    }

    /**
     * Kiểm mã người dùng gõ. Trả về bước thời gian khớp (để lưu lại chống dùng lại), null nếu sai.
     *
     * @param  int|null  $lastUsedStep  bước của mã đã dùng lần trước: mọi bước <= nó bị từ chối
     */
    public static function verify(string $secret, string $input, ?int $lastUsedStep = null, ?int $time = null): ?int
    {
        $input = preg_replace('/\D/', '', $input) ?? '';

        if (strlen($input) !== self::DIGITS) {
            return null;
        }

        $now = self::timestep($time);

        for ($step = $now - self::WINDOW; $step <= $now + self::WINDOW; $step++) {
            if ($lastUsedStep !== null && $step <= $lastUsedStep) {
                continue;
            }

            if (hash_equals(self::code($secret, $step), $input)) {
                return $step;
            }
        }

        return null;
    }

    /** Đường dẫn otpauth:// mà mã QR chứa. */
    public static function uri(string $secret, string $account, string $issuer): string
    {
        return sprintf(
            'otpauth://totp/%s:%s?%s',
            rawurlencode($issuer),
            rawurlencode($account),
            http_build_query([
                'secret' => $secret,
                'issuer' => $issuer,
                'algorithm' => 'SHA1',
                'digits' => self::DIGITS,
                'period' => self::PERIOD,
            ], '', '&', PHP_QUERY_RFC3986),
        );
    }

    /** Mã QR dạng SVG nhúng thẳng vào trang (không gọi dịch vụ ngoài nào — khoá không rời server). */
    public static function qrSvg(string $uri, int $size = 200): string
    {
        $svg = (new Writer(new ImageRenderer(new RendererStyle($size, 1), new SvgImageBackEnd)))->writeString($uri);

        // Bỏ dòng khai báo XML ở đầu để chèn được vào HTML.
        return trim(substr($svg, strpos($svg, "\n") + 1));
    }

    /** Khoá tách nhóm 4 ký tự cho dễ gõ tay khi không quét được QR. */
    public static function formatSecret(string $secret): string
    {
        return trim(chunk_split($secret, 4, ' '));
    }

    public static function base32Encode(string $bytes): string
    {
        $bits = '';
        foreach (str_split($bytes) as $byte) {
            $bits .= str_pad(decbin(ord($byte)), 8, '0', STR_PAD_LEFT);
        }

        $out = '';
        foreach (str_split($bits, 5) as $chunk) {
            $out .= self::BASE32[bindec(str_pad($chunk, 5, '0'))];
        }

        return $out;
    }

    public static function base32Decode(string $base32): string
    {
        $base32 = strtoupper((string) preg_replace('/[\s=-]/', '', $base32));

        $bits = '';
        foreach (str_split($base32) as $char) {
            $pos = strpos(self::BASE32, $char);
            if ($pos === false) {
                throw new \InvalidArgumentException('Khoá base32 không hợp lệ.');
            }
            $bits .= str_pad(decbin($pos), 5, '0', STR_PAD_LEFT);
        }

        $out = '';
        foreach (str_split($bits, 8) as $chunk) {
            if (strlen($chunk) === 8) {
                $out .= chr(bindec($chunk));
            }
        }

        return $out;
    }
}
