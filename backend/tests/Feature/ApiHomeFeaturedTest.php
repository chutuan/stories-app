<?php

declare(strict_types=1);

namespace Tests\Feature;

use App\Models\Chapter;
use App\Models\Story;
use App\Models\StoryView;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Carbon;
use Tests\TestCase;

class ApiHomeFeaturedTest extends TestCase
{
    use RefreshDatabase;

    protected function tearDown(): void
    {
        Carbon::setTestNow();
        parent::tearDown();
    }

    private function story(string $slug, int $views = 0, bool $featured = false): Story
    {
        $s = Story::create(['title' => "T $slug", 'slug' => $slug, 'status' => 'ongoing', 'views' => $views, 'is_featured' => $featured]);
        Chapter::create(['story_id' => $s->id, 'number' => 1, 'title' => 'One', 'content' => 'Lorem.']);

        return $s;
    }

    private function hits(Story $s, string $day, int $hits, string $visitor = 'a'): void
    {
        StoryView::create([
            'story_id' => $s->id, 'chapter_id' => 0, 'source' => 'app', 'device' => 'mobile',
            'visitor_hash' => str_repeat($visitor, 64), 'viewed_on' => $day, 'hits' => $hits,
        ]);
    }

    public function test_noi_bat_la_truyen_nhieu_luot_xem_nhat_7_ngay_qua(): void
    {
        Carbon::setTestNow(Carbon::parse('2026-11-01 10:00:00'));

        $old = $this->story('cu', 9999, featured: true);
        $this->hits($old, '2026-10-25', 500); // ngày thứ 8 trở về trước: không tính

        $week = $this->story('tuan');
        $this->hits($week, '2026-10-26', 30); // đúng 6 ngày trước: vẫn tính
        $this->hits($week, '2026-11-01', 10, 'b');

        $runnerUp = $this->story('nhi');
        $this->hits($runnerUp, '2026-10-31', 39);

        $this->getJson('/api/home')
            ->assertOk()
            ->assertJsonPath('featured.id', $week->id)
            ->assertJsonPath('featured.latest_chapter_number', 1)
            ->assertJsonPath('featured_pool.*.id', [$week->id, $runnerUp->id]);
    }

    public function test_tuan_chua_co_luot_xem_thi_dung_truyen_admin_danh_dau(): void
    {
        $marked = $this->story('danh-dau', featured: true);
        $this->story('thuong', 50);

        $this->getJson('/api/home')
            ->assertOk()
            ->assertJsonPath('featured.id', $marked->id)
            ->assertJsonPath('featured_pool.*.id', [$marked->id]);
    }
}
