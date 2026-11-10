<?php

declare(strict_types=1);

namespace Tests\Unit;

use App\Support\Totp;
use PHPUnit\Framework\TestCase;

class TotpTest extends TestCase
{
    /** Khoá ASCII "12345678901234567890" của bộ số kiểm RFC 6238 (phụ lục B), dạng base32. */
    private const RFC_SECRET = 'GEZDGNBVGY3TQOJQGEZDGNBVGY3TQOJQ';

    public function test_base32_dung_chuan(): void
    {
        $this->assertSame(self::RFC_SECRET, Totp::base32Encode('12345678901234567890'));
        $this->assertSame('12345678901234567890', Totp::base32Decode(self::RFC_SECRET));
        $this->assertSame('12345678901234567890', Totp::base32Decode('gezd gnbv gy3t qojq gezd gnbv gy3t qojq'));
    }

    /** RFC 6238 cho mã 8 số; 6 số là 6 chữ số cuối. */
    public function test_khop_bo_so_kiem_rfc_6238(): void
    {
        $vectors = [
            59 => '287082',
            1111111109 => '081804',
            1111111111 => '050471',
            1234567890 => '005924',
            2000000000 => '279037',
        ];

        foreach ($vectors as $time => $code) {
            $this->assertSame($code, Totp::code(self::RFC_SECRET, Totp::timestep($time)), "T={$time}");
        }
    }

    public function test_verify_nhan_lech_mot_buoc_va_chan_dung_lai(): void
    {
        $secret = Totp::generateSecret();
        $now = 1_800_000_000;
        $step = Totp::timestep($now);

        $this->assertSame($step, Totp::verify($secret, Totp::code($secret, $step), null, $now));
        $this->assertSame($step - 1, Totp::verify($secret, Totp::code($secret, $step - 1), null, $now));
        $this->assertSame($step + 1, Totp::verify($secret, Totp::code($secret, $step + 1), null, $now));
        $this->assertNull(Totp::verify($secret, Totp::code($secret, $step - 3), null, $now));

        // Gõ có dấu cách vẫn nhận.
        $code = Totp::code($secret, $step);
        $this->assertSame($step, Totp::verify($secret, substr($code, 0, 3).' '.substr($code, 3), null, $now));

        // Đã dùng mã của bước này rồi thì mã cùng bước (hoặc cũ hơn) bị từ chối.
        $this->assertNull(Totp::verify($secret, $code, $step, $now));
        $this->assertNull(Totp::verify($secret, Totp::code($secret, $step - 1), $step, $now));

        $this->assertNull(Totp::verify($secret, '12345', null, $now));
        $this->assertNull(Totp::verify($secret, 'abcdef', null, $now));
    }

    public function test_khoa_moi_du_dai_va_uri_dung_dinh_dang(): void
    {
        $secret = Totp::generateSecret();
        $this->assertSame(32, strlen($secret));
        $this->assertSame(20, strlen(Totp::base32Decode($secret)));

        $uri = Totp::uri($secret, 'a@b.test', 'TunaStory Admin');
        $this->assertStringStartsWith('otpauth://totp/TunaStory%20Admin:a%40b.test?secret='.$secret, $uri);
        $this->assertStringContainsString('issuer=TunaStory%20Admin', $uri);
        $this->assertStringContainsString('digits=6', $uri);
    }
}
