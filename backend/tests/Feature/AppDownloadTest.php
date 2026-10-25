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

    public function test_file_qr_co_that(): void
    {
        $this->assertFileExists(public_path('images/app-qr.svg'));
        $this->assertStringContainsString('https://tunastory.com/app', file_get_contents(public_path('images/app-qr.svg')));
    }
}
