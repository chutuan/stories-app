<?php

declare(strict_types=1);

namespace Tests\Feature;

use App\Models\Category;
use App\Models\Chapter;
use App\Models\Story;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

/**
 * Mấy chi tiết nhỏ quanh việc đọc trên web: mời theo dõi Facebook khi đọc hết truyện, tải
 * sẵn chương sau, và bìa hàng đầu của lưới không lazy.
 */
class WebReaderTouchesTest extends TestCase
{
    use RefreshDatabase;

    private function story(string $slug = 'test-story'): Story
    {
        $story = Story::create(['title' => 'Test Story '.$slug, 'slug' => $slug, 'description' => 'x', 'status' => 'completed']);
        foreach ([1, 2] as $n) {
            Chapter::create(['story_id' => $story->id, 'number' => $n, 'title' => 'Part '.$n, 'content' => "Lorem ipsum.\n\nDolor sit."]);
        }

        return $story;
    }

    public function test_chuong_cuoi_moi_theo_doi_page_facebook_chuong_giua_thi_khong(): void
    {
        config(['services.facebook.page_url' => 'https://www.facebook.com/tunastorycom']);
        $story = $this->story();

        $this->get("/story/{$story->slug}/chapter/2")->assertOk()
            ->assertSee('class="follow-card"', false)
            ->assertSee('href="https://www.facebook.com/tunastorycom"', false);

        $this->get("/story/{$story->slug}/chapter/1")->assertOk()
            ->assertDontSee('class="follow-card"', false);
    }

    public function test_khong_cau_hinh_page_thi_khong_hien_loi_moi(): void
    {
        config(['services.facebook.page_url' => null]);
        $story = $this->story();

        $this->get("/story/{$story->slug}/chapter/2")->assertOk()
            ->assertDontSee('class="follow-card"', false);
    }

    public function test_chuong_co_tai_san_chuong_sau_va_trang_truyen_tai_san_chuong_dau(): void
    {
        $story = $this->story();

        $this->get("/story/{$story->slug}/chapter/1")->assertOk()
            ->assertSee("supports('speculationrules')", false)
            ->assertSee('rel="next"', false);

        $this->get("/story/{$story->slug}")->assertOk()
            ->assertSee('<script type="speculationrules">', false)
            ->assertSee('#read-btn', false);
    }

    public function test_hai_bia_dau_cua_luoi_tai_ngay_con_lai_lazy(): void
    {
        $genre = Category::create(['name' => 'Drama', 'slug' => 'drama']);
        foreach (range(1, 4) as $i) {
            $s = $this->story('s'.$i);
            $s->forceFill(['thumbnail' => "covers/s{$i}.jpg"])->save();
            $s->categories()->attach($genre);
        }

        $html = $this->get('/browse')->assertOk()->getContent();
        preg_match('~<ul class="grid">(.*?)</ul>~s', $html, $m);
        $grid = $m[1] ?? '';

        $this->assertSame(4, substr_count($grid, '<img '), 'lưới có 4 bìa');
        $this->assertSame(2, substr_count($grid, 'loading="lazy"'), 'chỉ 2 bìa sau là lazy');
        $this->assertSame(2, substr_count($grid, 'fetchpriority="high"'), '2 bìa đầu tải ngay');
    }
}
