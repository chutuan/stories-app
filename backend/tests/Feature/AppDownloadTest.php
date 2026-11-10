<?php

declare(strict_types=1);

namespace Tests\Feature;

use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class AppDownloadTest extends TestCase
{
    use RefreshDatabase;

    public function test_app_chuyen_toi_trang_app_store(): void
    {
        $this->get('/app')->assertStatus(302)
            ->assertRedirect('https://apps.apple.com/app/id6810302346');
    }

    public function test_app_co_src_nhung_chua_co_provider_token_thi_bo_qua_src(): void
    {
        config(['app.ios_provider_token' => null]);

        $this->get('/app?src=blog.example.com')->assertStatus(302)
            ->assertRedirect('https://apps.apple.com/app/id6810302346');
    }

    public function test_app_co_src_va_provider_token_thi_thanh_link_chien_dich(): void
    {
        config(['app.ios_provider_token' => '123456']);

        $this->get('/app?src=blog.example.com')->assertStatus(302)
            ->assertRedirect('https://apps.apple.com/app/apple-store/id6810302346?pt=123456&ct=blog.example.com&mt=8');
        // Ký tự lạ bị lọc, dài quá 40 ký tự bị cắt; src dạng mảng coi như không có.
        $this->get('/app?src='.urlencode('<x>"&a=b'.str_repeat('z', 60)))
            ->assertRedirect('https://apps.apple.com/app/apple-store/id6810302346?pt=123456&ct=xab'.str_repeat('z', 37).'&mt=8');
        $this->get('/app?src[]=x')->assertRedirect('https://apps.apple.com/app/id6810302346');
        $this->get('/app')->assertRedirect('https://apps.apple.com/app/id6810302346');
    }

    private const ANDROID_UA = 'Mozilla/5.0 (Linux; Android 14; Pixel 8) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/130.0 Mobile Safari/537.36';

    public function test_android_chua_bat_thi_van_sang_app_store(): void
    {
        config(['app.android_public' => false]);

        $this->withHeaders(['User-Agent' => self::ANDROID_UA])->get('/app')
            ->assertRedirect('https://apps.apple.com/app/id6810302346');
        $this->get('/app?os=android&src=blog.example.com')
            ->assertRedirect('https://apps.apple.com/app/id6810302346');
    }

    public function test_android_da_bat_thi_sang_google_play(): void
    {
        config(['app.android_public' => true, 'app.ios_provider_token' => null]);

        // Quét mã QR bằng máy Android: không có ?os, đoán theo User-Agent.
        $this->withHeaders(['User-Agent' => self::ANDROID_UA])->get('/app')
            ->assertRedirect('https://play.google.com/store/apps/details?id=com.chutuan.stories')
            ->assertHeader('Vary', 'User-Agent');
        // Script nhúng nói rõ os; src thành utm_source trong referrer (mã hoá hai lần).
        $this->get('/app?os=android&src=blog.example.com')
            ->assertRedirect('https://play.google.com/store/apps/details?id=com.chutuan.stories'
                .'&referrer=utm_source%3Dblog.example.com%26utm_medium%3Dapp-prompt');
        // ?os=ios thắng User-Agent; máy không phải Android vẫn sang App Store.
        $this->withHeaders(['User-Agent' => self::ANDROID_UA])->get('/app?os=ios')
            ->assertRedirect('https://apps.apple.com/app/id6810302346');
        $this->withHeaders(['User-Agent' => 'Mozilla/5.0 (iPhone; CPU iPhone OS 18_0 like Mac OS X)'])->get('/app')
            ->assertRedirect('https://apps.apple.com/app/id6810302346');
    }

    public function test_script_nhung_dien_cong_tac_android_tu_config(): void
    {
        config(['app.android_public' => false]);
        $this->assertStringContainsString('var ANDROID = false /*android_public*/;', $this->get('/embed/app-prompt.js')->getContent());

        config(['app.android_public' => true]);
        $body = $this->get('/embed/app-prompt.js')->getContent();
        $this->assertStringContainsString('var ANDROID = true /*android_public*/;', $body);
        $this->assertStringContainsString('/images/google-play-badge.png', $body);
        $this->assertFileExists(public_path('images/google-play-badge.png'));
    }

    public function test_modal_chi_co_badge_google_play_khi_da_bat(): void
    {
        config(['app.android_public' => false]);
        $this->get('/about')->assertOk()->assertSee('id="app-prompt"', false)
            ->assertDontSee('alt="Get it on Google Play"', false)->assertDontSee('/app?os=android', false)
            ->assertSee('var ANDROID = false', false);

        config(['app.android_public' => true]);
        $this->get('/about')->assertOk()
            ->assertSee('images/google-play-badge.png', false)->assertSee('alt="Get it on Google Play"', false)
            ->assertSee('/app?os=android', false)->assertSee('var ANDROID = true', false);
    }

    public function test_script_nhung_tra_javascript_cache_duoc_o_bien_va_khong_co_cookie(): void
    {
        $response = $this->get('/embed/app-prompt.js')->assertOk();

        $this->assertStringStartsWith('application/javascript', (string) $response->headers->get('Content-Type'));
        $this->assertStringContainsString('public', (string) $response->headers->get('Cache-Control'));
        $this->assertStringContainsString('s-maxage=', (string) $response->headers->get('Cache-Control'));
        $this->assertEmpty($response->headers->getCookies(), 'Cloudflare không cache response có Set-Cookie');
        $body = file_get_contents(resource_path('embed/app-prompt.js'));
        $this->assertStringContainsString('iPhone|iPad|iPod', $body);
        $this->assertStringContainsString("'/app'", $body);
    }

    public function test_trang_thuong_co_ma_qr_va_smart_banner(): void
    {
        $this->get('/about')->assertOk()
            ->assertSee('images/app-qr.svg', false)
            ->assertSee('<meta name="apple-itunes-app" content="app-id=6810302346">', false)
            ->assertSee('Read Stories on iPhone');
    }

    public function test_trang_phap_ly_khong_quang_ba_app(): void
    {
        foreach (['/privacy', '/terms', '/contact'] as $url) {
            $this->get($url)->assertOk()
                ->assertDontSee('apple-itunes-app', false)
                ->assertDontSee('app-qr.svg', false);
        }
    }

    public function test_modal_moi_tai_app_co_tren_trang_thuong_khong_co_tren_trang_phap_ly(): void
    {
        $this->get('/about')->assertOk()->assertSee('id="app-prompt"', false)
            ->assertSee('images/app-store-badge.svg', false)->assertSee('alt="Download on the App Store"', false);
        foreach (['/privacy', '/terms', '/contact'] as $url) {
            $this->get($url)->assertOk()->assertDontSee('id="app-prompt"', false);
        }
    }

    public function test_file_qr_co_that(): void
    {
        $this->assertFileExists(public_path('images/app-qr.svg'));
        $this->assertStringContainsString('https://tunastory.com/app', file_get_contents(public_path('images/app-qr.svg')));
    }
}
