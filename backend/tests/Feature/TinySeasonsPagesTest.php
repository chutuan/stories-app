<?php

namespace Tests\Feature;

use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

/**
 * Trang hỗ trợ và chính sách quyền riêng tư của game Tiny Seasons. App Store Connect của game
 * trỏ về đúng hai đường dẫn này, nên chúng phải luôn trả 200 và không mang quảng cáo,
 * GA4 hay lời mời tải app Stories.
 */
class TinySeasonsPagesTest extends TestCase
{
    use RefreshDatabase;

    public function test_hai_trang_mo_duoc_va_tro_nhau(): void
    {
        $this->get('/tiny-seasons')->assertOk()
            ->assertSee('Tiny Seasons')
            ->assertSee(route('public.tiny-seasons.privacy'), false)
            ->assertSee('mailto:'.config('app.support_email'), false);

        $this->get('/tiny-seasons/privacy')->assertOk()
            ->assertSee('Tiny Seasons Privacy Policy')
            ->assertSee('Google Mobile Ads SDK')
            ->assertSee('non-personalised');
    }

    public function test_khong_quang_cao_khong_ga_khong_moi_tai_app_stories(): void
    {
        config(['app.admob_publisher_id' => 'pub-123', 'app.ga_measurement_id' => 'G-TEST']);

        foreach (['/tiny-seasons', '/tiny-seasons/privacy'] as $url) {
            $html = $this->get($url)->assertOk()->getContent();
            $this->assertStringNotContainsString('adsbygoogle', $html);
            $this->assertStringNotContainsString('googletagmanager', $html);
            $this->assertStringNotContainsString('apple-itunes-app', $html);
            $this->assertStringNotContainsString('app-qr.svg', $html);
            $this->assertStringNotContainsString('id="app-prompt"', $html);
        }
    }

    public function test_duoc_cache_o_bien(): void
    {
        foreach (['/tiny-seasons', '/tiny-seasons/privacy'] as $url) {
            $this->get($url)->assertOk()->assertHeader('Cache-Control');
            $this->assertStringContainsString('s-maxage', (string) $this->get($url)->headers->get('Cache-Control'));
        }
    }
}
