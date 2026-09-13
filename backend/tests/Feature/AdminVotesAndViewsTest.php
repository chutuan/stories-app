<?php

declare(strict_types=1);

namespace Tests\Feature;

use App\Models\Chapter;
use App\Models\ChapterReaction;
use App\Models\Story;
use App\Models\StoryView;
use App\Models\User;
use App\Support\ViewCounter;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class AdminVotesAndViewsTest extends TestCase
{
    use RefreshDatabase;

    private function story(): Story
    {
        $story = Story::create(['title' => 'Test Story', 'slug' => 'test-story', 'description' => 'x', 'status' => 'ongoing']);
        Chapter::create(['story_id' => $story->id, 'number' => 1, 'title' => 'One', 'content' => 'Lorem ipsum.']);
        Chapter::create(['story_id' => $story->id, 'number' => 2, 'title' => 'Two', 'content' => 'Lorem ipsum.']);

        return $story;
    }

    public function test_trang_phieu_hien_duoc_va_gop_dung_theo_chuong(): void
    {
        $story = $this->story();
        $ch = $story->chapters()->where('number', 1)->first();

        foreach ([5, 5, 3] as $i => $score) {
            ChapterReaction::create(['chapter_id' => $ch->id, 'visitor_hash' => str_repeat((string) $i, 64), 'score' => $score]);
        }

        $res = $this->actingAs(User::factory()->create())->get('/admin/votes');

        $res->assertOk();
        $res->assertSee('Test Story');
        $res->assertSee('4.3/5');          // (5+5+3)/3 = 4.33
        $res->assertSee('Rất thích');
    }

    public function test_xoa_duoc_mot_phieu(): void
    {
        $story = $this->story();
        $ch = $story->chapters()->first();
        $vote = ChapterReaction::create(['chapter_id' => $ch->id, 'visitor_hash' => str_repeat('a', 64), 'score' => 1]);

        $this->actingAs(User::factory()->create())
            ->delete("/admin/votes/{$vote->id}")
            ->assertRedirect();

        $this->assertDatabaseCount('chapter_reactions', 0);
    }

    public function test_luot_xem_web_va_app_duoc_dem_tach_nhau(): void
    {
        $story = $this->story();

        // Web: trình duyệt desktop
        $this->withHeader('User-Agent', 'Mozilla/5.0 (Macintosh; Intel Mac OS X 10_15_7) AppleWebKit/537.36 Chrome/153.0 Safari/537.36')
            ->get("/story/{$story->slug}")->assertOk();

        // App: gọi API
        $this->withHeader('User-Agent', 'Stories/3 CFNetwork/1494.0.7 Darwin/23.4.0')
            ->getJson("/api/stories/{$story->id}")->assertOk();

        $sum = ViewCounter::summaryFor([$story->id])[$story->id];

        $this->assertSame(1, $sum['app'], 'lượt app');
        $this->assertSame(1, $sum['web_desktop'], 'lượt web desktop');
        $this->assertSame(2, $sum['total']);
    }

    public function test_bot_khong_duoc_tinh_la_luot_xem_web(): void
    {
        $story = $this->story();

        $this->withHeader('User-Agent', 'Mozilla/5.0 (compatible; Googlebot/2.1; +http://www.google.com/bot.html)')
            ->get("/story/{$story->slug}")->assertOk();

        $this->assertDatabaseCount('story_views', 0);
    }

    public function test_xem_lai_trong_ngay_cong_hits_chu_khong_them_dong(): void
    {
        $story = $this->story();
        $ua = 'Mozilla/5.0 (iPhone; CPU iPhone OS 18_0 like Mac OS X) AppleWebKit/605.1.15 Mobile/15E148 Safari/604.1';

        foreach (range(1, 3) as $ignored) {
            $this->withHeader('User-Agent', $ua)->get("/story/{$story->slug}")->assertOk();
        }

        $this->assertDatabaseCount('story_views', 1);
        $this->assertSame(3, (int) StoryView::first()->hits);
        $this->assertSame(3, ViewCounter::summaryFor([$story->id])[$story->id]['web_mobile']);
        $this->assertSame(1, ViewCounter::summaryFor([$story->id])[$story->id]['unique']);
    }

    public function test_trang_doc_cong_khai_cho_phep_cache_va_khong_dat_cookie(): void
    {
        $story = $this->story();

        foreach (['/', '/browse', "/story/{$story->slug}"] as $url) {
            $res = $this->get($url);

            $res->assertOk();
            $this->assertStringContainsString('s-maxage=600', $res->headers->get('Cache-Control'), $url);
            $this->assertStringContainsString('public', $res->headers->get('Cache-Control'), $url);

            // Cloudflare không cache response có Set-Cookie — còn sót một cái là
            // cả phần header ở trên thành vô nghĩa.
            $this->assertSame([], $res->headers->getCookies(), "{$url} không được đặt cookie");
        }
    }

    public function test_trang_chuong_KHONG_duoc_cache_vi_noi_dung_rieng_tung_nguoi(): void
    {
        $story = $this->story();

        $res = $this->get("/story/{$story->slug}/chapter/1");

        $res->assertOk();
        // Khối đánh giá hiện khác nhau tuỳ người đọc đã bỏ phiếu hay chưa; đem
        // cache dùng chung là người này thấy lời cảm ơn của người khác.
        $this->assertStringNotContainsString('s-maxage', (string) $res->headers->get('Cache-Control'));
    }

    public function test_trang_danh_sach_truyen_hien_cot_luot_xem(): void
    {
        $story = $this->story();
        $this->withHeader('User-Agent', 'Mozilla/5.0 (Macintosh) Chrome/153.0 Safari/537.36')
            ->get("/story/{$story->slug}")->assertOk();

        $this->actingAs(User::factory()->create())
            ->get('/admin/stories')
            ->assertOk()
            ->assertSee('Lượt xem');
    }
}
