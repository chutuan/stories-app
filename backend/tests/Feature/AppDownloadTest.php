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
        $this->get('/about')->assertOk()->assertSee('id="app-prompt"', false)->assertSee('Get the free app');
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
