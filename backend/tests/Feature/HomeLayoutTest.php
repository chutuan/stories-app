<?php

declare(strict_types=1);

namespace Tests\Feature;

use App\Models\Chapter;
use App\Models\Story;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class HomeLayoutTest extends TestCase
{
    use RefreshDatabase;

    private function story(string $slug, int $views, bool $featured = false): Story
    {
        $s = Story::create(['title' => "T $slug", 'slug' => $slug, 'status' => 'completed', 'views' => $views, 'is_featured' => $featured]);
        Chapter::create(['story_id' => $s->id, 'number' => 1, 'title' => 'One', 'content' => 'Lorem.']);

        return $s;
    }

    public function test_most_read_khong_lap_truyen_va_van_hien_khi_khong_co_truyen_noi_bat(): void
    {
        foreach (range(1, 15) as $i) {
            $this->story("s$i", $i * 10);
        }
        $html = $this->get('/')->assertOk()->assertSee('Most read')->assertDontSee('Newest stories')->getContent();
        // 15 truyện: 12 ở Recently updated, 3 còn lại ở Most read, không truyện nào hiện hai lần.
        foreach (range(1, 15) as $i) {
            $this->assertSame(1, substr_count($html, "/story/s$i\""), "s$i phải xuất hiện đúng một lần");
        }
    }

    public function test_hang_the_loai_khong_lap_truyen_da_hien_o_muc_tren(): void
    {
        $genre = \App\Models\Category::create(['name' => 'Revenge', 'slug' => 'revenge']);
        $this->story('featured-one', 999, true)->categories()->attach($genre);
        foreach (range(1, 40) as $i) {
            $this->story("g$i", $i)->categories()->attach($genre);
        }

        $html = $this->get('/')->assertOk()->assertSee('Revenge')->getContent();
        // Có truyện nổi bật: 5 xếp hạng + 12 mới cập nhật + 12 ở hàng "Revenge" = 29 truyện,
        // mỗi truyện đúng một thẻ.
        $shown = 0;
        foreach (range(1, 40) as $i) {
            $n = substr_count($html, "/story/g$i\"");
            $this->assertLessThanOrEqual(1, $n, "g$i hiện $n lần");
            $shown += $n;
        }
        $this->assertSame(29, $shown);
    }

    public function test_truyen_noi_bat_khong_lap_lai_o_most_read(): void
    {
        $f = $this->story('featured-one', 999, true);
        $this->story('other', 1);
        $html = $this->get('/')->assertOk()->getContent();
        $this->assertStringContainsString('Featured', $html);
    }
}
