<?php

declare(strict_types=1);

namespace Tests\Feature;

use App\Models\Story;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Storage;
use Tests\TestCase;

/** Các thay đổi sau báo cáo PageSpeed 01/11/2026 (LCP di động 5,7 giây). */
class WebPerformanceTest extends TestCase
{
    use RefreshDatabase;

    public function test_adsense_va_ga4_chi_nap_sau_su_kien_load(): void
    {
        config(['app.admob_publisher_id' => 'pub-123', 'app.ga_measurement_id' => 'G-TEST']);

        $html = $this->get('/')->assertOk()->getContent();

        $this->assertStringContainsString('<meta name="google-adsense-account" content="ca-pub-123">', $html);
        $this->assertStringContainsString("addEventListener('load'", $html);
        $this->assertStringContainsString('adsbygoogle.js?client=ca-pub-123', $html);
        $this->assertStringContainsString('gtag/js?id=G-TEST', $html);
        // Không còn thẻ <script src> nạp ngay trong head.
        $this->assertDoesNotMatchRegularExpression('/<script[^>]+src="https:\/\/(pagead2|www\.googletagmanager)/', $html);

        // Trang pháp lý app mở trong web view: không quảng cáo, không GA4.
        $legal = $this->get('/privacy')->assertOk()->getContent();
        $this->assertStringNotContainsString('adsbygoogle', $legal);
        $this->assertStringNotContainsString('googletagmanager', $legal);
        $this->assertStringNotContainsString('google-adsense-account', $legal);
    }

    public function test_sinh_anh_nen_mo_32w_va_ban_480w(): void
    {
        Storage::fake('public');
        $image = imagecreatetruecolor(600, 800);
        imagefilledrectangle($image, 0, 0, 599, 799, (int) imagecolorallocate($image, 200, 90, 40));
        ob_start();
        imagejpeg($image);
        Storage::disk('public')->put('stories/bia.jpg', (string) ob_get_clean());

        $story = Story::create(['title' => 'Bìa', 'slug' => 'bia', 'status' => 'completed', 'thumbnail' => 'stories/bia.jpg']);

        // Chưa sinh bản nào: nền mờ rơi về ảnh gốc, không vỡ trang.
        $this->assertStringContainsString('stories/bia.jpg', (string) $story->coverBlurUrl());

        $this->artisan('covers:derive')->assertSuccessful();

        foreach ([32, 320, 480, 640] as $w) {
            Storage::disk('public')->assertExists("stories/derived/bia-{$w}.webp");
        }
        $this->assertLessThan(1024, Storage::disk('public')->size('stories/derived/bia-32.webp'));
        $this->assertStringContainsString('bia-32.webp', (string) $story->coverBlurUrl());
        $this->assertMatchesRegularExpression('/bia-320\.webp\?v=\d+ 320w, .*bia-480\.webp\?v=\d+ 480w, .*bia-640\.webp\?v=\d+ 640w/', (string) $story->coverSrcset());
    }
}
