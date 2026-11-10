<?php

declare(strict_types=1);

namespace Tests\Feature;

use App\Models\User;
use App\Support\Totp;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\RateLimiter;
use Tests\TestCase;

class AdminTwoFactorTest extends TestCase
{
    use RefreshDatabase;

    private function currentCode(User $user, int $offset = 0): string
    {
        return Totp::code($user->two_factor_secret, Totp::timestep() + $offset);
    }

    private function login(User $user, string $password = 'password', bool $remember = false)
    {
        return $this->post('/admin/login', ['email' => $user->email, 'password' => $password] + ($remember ? ['remember' => '1'] : []));
    }

    public function test_dung_mat_khau_chua_dang_nhap_ma_chuyen_sang_nhap_ma(): void
    {
        $user = User::factory()->create();

        $this->login($user)->assertRedirect('/admin/two-factor');
        $this->assertGuest();
        $this->assertTrue(session()->has('admin.2fa_pending'));

        // Chưa nhập mã thì trang admin vẫn đòi đăng nhập.
        $this->get('/admin')->assertRedirect('/admin/login');
        $this->get('/admin/two-factor')->assertOk()->assertSee('Xác thực 2 bước');
    }

    public function test_nhap_dung_ma_thi_vao_duoc_va_ma_do_khong_dung_lai_duoc(): void
    {
        $user = User::factory()->create();
        $code = $this->currentCode($user);

        $this->login($user);
        $this->post('/admin/two-factor', ['code' => $code])->assertRedirect('/admin');
        $this->assertAuthenticatedAs($user);
        $this->assertFalse(session()->has('admin.2fa_pending'));
        $this->get('/admin')->assertOk();

        // Kẻ nhìn trộm mã đó, đăng nhập lại ngay trong 30 giây: bị từ chối.
        $this->post('/admin/logout');
        $this->login($user);
        $this->post('/admin/two-factor', ['code' => $code])->assertSessionHasErrors('code');
        $this->assertGuest();
    }

    public function test_sai_ma_5_lan_thi_phai_dang_nhap_lai(): void
    {
        $user = User::factory()->create();
        $this->login($user);

        for ($i = 0; $i < 4; $i++) {
            $this->post('/admin/two-factor', ['code' => '000000'])->assertSessionHasErrors('code');
        }
        $this->post('/admin/two-factor', ['code' => '000000'])->assertRedirect('/admin/login');
        $this->assertFalse(session()->has('admin.2fa_pending'));

        // Lúc này dù có mã đúng cũng không vào được — phải qua mật khẩu lại.
        $this->post('/admin/two-factor', ['code' => $this->currentCode($user)])->assertRedirect('/admin/login');
        $this->assertGuest();
    }

    public function test_phien_cho_ma_het_han_sau_5_phut(): void
    {
        $user = User::factory()->create();
        $this->login($user);

        $this->travel(6)->minutes();
        $this->post('/admin/two-factor', ['code' => $this->currentCode($user)])->assertRedirect('/admin/login');
        $this->assertGuest();
    }

    public function test_ma_khoi_phuc_dung_mot_lan(): void
    {
        $user = User::factory()->create();
        $codes = $user->issueRecoveryCodes();
        $user->save();

        $this->login($user);
        $this->post('/admin/two-factor', ['recovery_code' => strtoupper(str_replace('-', ' ', $codes[0]))])
            ->assertRedirect('/admin')
            ->assertSessionHas('warning');
        $this->assertAuthenticatedAs($user);
        $this->assertSame(User::RECOVERY_CODE_COUNT - 1, $user->fresh()->recoveryCodesLeft());

        $this->post('/admin/logout');
        $this->login($user);
        $this->post('/admin/two-factor', ['recovery_code' => $codes[0]])->assertSessionHasErrors('recovery_code');
        $this->assertGuest();
    }

    public function test_sai_mat_khau_bi_gioi_han_so_lan(): void
    {
        $user = User::factory()->create();

        for ($i = 0; $i < 5; $i++) {
            $this->login($user, 'sai-roi')->assertSessionHasErrors('email');
        }

        // Lần thứ 6, kể cả đúng mật khẩu, vẫn bị chặn.
        $this->login($user)->assertSessionHasErrors('email');
        $this->assertFalse(session()->has('admin.2fa_pending'));
        $this->assertStringContainsString('Thử lại sau', session('errors')->first('email'));

        RateLimiter::clear('admin-login|'.strtolower($user->email).'|127.0.0.1');
        $this->login($user)->assertRedirect('/admin/two-factor');
    }

    public function test_tai_khoan_chua_bat_2fa_bi_dua_toi_trang_bat(): void
    {
        $user = User::factory()->withoutTwoFactor()->create();

        $this->login($user)->assertRedirect('/admin');
        $this->assertAuthenticatedAs($user);

        $this->get('/admin')->assertRedirect('/admin/security');
        $this->get('/admin/stories')->assertRedirect('/admin/security');
        $this->get('/admin/security')->assertOk()->assertSee('Quét mã QR')->assertSee('<svg', false);
    }

    public function test_bat_2fa_lan_dau(): void
    {
        $user = User::factory()->withoutTwoFactor()->create();
        $this->actingAs($user)->get('/admin/security')->assertOk();

        $secret = decrypt(session('admin.2fa_setup'), false);
        $this->assertSame(32, strlen($secret));

        $this->post('/admin/security/confirm', ['code' => '000000'])->assertSessionHasErrors('code');
        $this->assertFalse($user->fresh()->hasTwoFactorEnabled());

        $this->post('/admin/security/confirm', ['code' => Totp::code($secret, Totp::timestep())])
            ->assertRedirect('/admin/security')
            ->assertSessionHas('admin.recovery_codes');

        $fresh = $user->fresh();
        $this->assertTrue($fresh->hasTwoFactorEnabled());
        $this->assertSame($secret, $fresh->two_factor_secret);
        $this->assertSame(User::RECOVERY_CODE_COUNT, $fresh->recoveryCodesLeft());
        $this->assertFalse(session()->has('admin.2fa_setup'));

        // Mã khôi phục hiện đúng một lần.
        $codes = session('admin.recovery_codes');
        $this->get('/admin/security')->assertOk()->assertSee($codes[0]);
        $this->get('/admin/security')->assertOk()->assertDontSee($codes[0]);

        $this->get('/admin')->assertOk();

        // Khoá được mã hoá trong DB, mã khôi phục không lưu dạng gốc.
        $raw = \DB::table('users')->where('id', $user->id)->first();
        $this->assertStringNotContainsString($secret, (string) $raw->two_factor_secret);
        $this->assertStringNotContainsString($codes[0], (string) $raw->two_factor_recovery_codes);
    }

    public function test_doi_dien_thoai_can_mat_khau_va_khoa_cu_chay_toi_khi_xac_nhan(): void
    {
        $user = User::factory()->create();
        $oldSecret = $user->two_factor_secret;

        $this->actingAs($user)->get('/admin/security')->assertOk()->assertDontSee('Quét mã QR');
        $this->assertFalse(session()->has('admin.2fa_setup'));

        $this->post('/admin/security/setup', ['password' => 'sai'])->assertSessionHasErrors('password');
        $this->post('/admin/security/setup', ['password' => 'password'])->assertRedirect('/admin/security');
        $this->get('/admin/security')->assertSee('Quét mã QR');

        // Bỏ dở: khoá cũ vẫn nguyên, trang admin vẫn vào được.
        $this->assertSame($oldSecret, $user->fresh()->two_factor_secret);
        $this->get('/admin')->assertOk();

        $newSecret = decrypt(session('admin.2fa_setup'), false);
        $this->post('/admin/security/confirm', ['code' => Totp::code($newSecret, Totp::timestep())])
            ->assertRedirect('/admin/security');
        $this->assertSame($newSecret, $user->fresh()->two_factor_secret);
    }

    public function test_tao_lai_ma_khoi_phuc_lam_bo_cu_het_hieu_luc(): void
    {
        $user = User::factory()->create();
        $old = $user->issueRecoveryCodes();
        $user->save();

        $this->actingAs($user)->post('/admin/security/recovery-codes', ['password' => 'sai'])
            ->assertSessionHasErrors('password');
        $this->post('/admin/security/recovery-codes', ['password' => 'password'])
            ->assertSessionHas('admin.recovery_codes');

        $this->assertFalse($user->fresh()->consumeRecoveryCode($old[0]));
        $this->assertTrue($user->fresh()->consumeRecoveryCode(session('admin.recovery_codes')[0]));
    }

    public function test_lenh_go_2fa(): void
    {
        $user = User::factory()->create();

        $this->artisan('admin:2fa-reset', ['email' => $user->email])->assertSuccessful();
        $this->assertFalse($user->fresh()->hasTwoFactorEnabled());

        $this->artisan('admin:2fa-reset', ['email' => 'khong-co@x.test'])->assertFailed();
    }
}
