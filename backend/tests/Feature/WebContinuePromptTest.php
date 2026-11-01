<?php

declare(strict_types=1);

namespace Tests\Feature;

use App\Models\Category;
use App\Models\Chapter;
use App\Models\Story;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

/** Bottom sheet "Pick up where you left off" trên web, tương đương modal đọc tiếp của app. */
class WebContinuePromptTest extends TestCase
{
    use RefreshDatabase;

    private function story(): Story
    {
        $s = Story::create(['title' => 'Truyện', 'slug' => 'truyen', 'status' => 'ongoing']);
        Chapter::create(['story_id' => $s->id, 'number' => 1, 'title' => 'Chapter 1: The Start', 'content' => 'Lorem.']);

        return $s;
    }

    public function test_chi_moi_o_cac_trang_tuong_duong_tab_cua_app(): void
    {
        $story = $this->story();
        $genre = Category::create(['name' => 'Romance', 'slug' => 'romance']);
        $story->categories()->attach($genre);

        foreach (['/', '/browse', '/genre/romance', '/search?q=truyen'] as $url) {
            $this->get($url)->assertOk()
                ->assertSee('id="continue-prompt"', false)
                ->assertSee('ELIGIBLE = true, READING = false', false);
        }

        // Trang truyện đã có nút Continue; trang chương thì đang đọc rồi (và xoá lời mời).
        $this->get('/story/truyen')->assertOk()->assertSee('ELIGIBLE = false, READING = false', false);
        $this->get('/story/truyen/chapter/1')->assertOk()->assertSee('ELIGIBLE = false, READING = true', false);
    }

    public function test_trang_phap_ly_khong_co_loi_moi(): void
    {
        foreach (['/privacy', '/terms'] as $url) {
            $this->get($url)->assertOk()->assertDontSee('id="continue-prompt"', false);
        }
    }

    public function test_lich_su_luu_ten_chuong_va_chuong_xa_nhat(): void
    {
        $this->story();

        $this->get('/story/truyen/chapter/1')->assertOk()
            ->assertSee('chTitle: "The Start"', false) // đã bỏ tiền tố "Chapter 1:" (Chapter::cleanTitle)
            ->assertSee('max: Math.max(1, prevRead', false);
    }

    public function test_loi_moi_tai_app_nhuong_trang_da_hien_loi_moi_doc_tiep(): void
    {
        $this->get('/about')->assertOk()->assertSee('if (window.StoriesSheetShown) return;', false);
    }
}
