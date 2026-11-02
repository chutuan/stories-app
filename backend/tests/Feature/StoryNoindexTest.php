<?php

declare(strict_types=1);

namespace Tests\Feature;

use App\Models\Chapter;
use App\Models\Story;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

/** Truyện đã đăng ở site khác: vẫn đọc được, nhưng Google không lập chỉ mục. */
class StoryNoindexTest extends TestCase
{
    use RefreshDatabase;

    private function story(string $slug, bool $noindex): Story
    {
        $s = Story::create(['title' => ucfirst($slug), 'slug' => $slug, 'status' => 'completed', 'noindex' => $noindex]);
        Chapter::create(['story_id' => $s->id, 'number' => 1, 'title' => 'Chapter 1: Start', 'content' => 'Lorem ipsum.']);

        return $s;
    }

    public function test_trang_truyen_va_chuong_co_the_noindex(): void
    {
        $this->story('trung', true);

        $this->get('/story/trung')->assertOk()
            ->assertSee('<meta name="robots" content="noindex, follow">', false);
        $this->get('/story/trung/chapter/1')->assertOk()
            ->assertSee('<meta name="robots" content="noindex, follow">', false);
    }

    public function test_truyen_thuong_van_index(): void
    {
        $this->story('rieng', false);

        $this->get('/story/rieng')->assertOk()
            ->assertSee('<meta name="robots" content="index, follow, max-image-preview:large">', false);
        $this->get('/story/rieng/chapter/1')->assertOk()
            ->assertSee('<meta name="robots" content="index, follow, max-image-preview:large">', false);
    }

    public function test_sitemap_bo_truyen_noindex_ca_cac_chuong(): void
    {
        $this->story('trung', true);
        $this->story('rieng', false);

        $this->get('/sitemap.xml')->assertOk()
            ->assertSee('/story/rieng</loc>', false)
            ->assertSee('/story/rieng/chapter/1</loc>', false)
            ->assertDontSee('/story/trung', false);
    }

    public function test_admin_bat_tat_noindex(): void
    {
        $story = $this->story('trung', false);
        $admin = User::factory()->create();

        $form = ['title' => 'Trung', 'status' => 'completed', 'free_chapters' => 1];

        $this->actingAs($admin)->put("/admin/stories/{$story->id}", $form + ['noindex' => '1'])->assertRedirect();
        $this->assertTrue($story->fresh()->noindex);

        // Bỏ tick: form gửi ô ẩn noindex=0.
        $this->actingAs($admin)->put("/admin/stories/{$story->id}", $form + ['noindex' => '0'])->assertRedirect();
        $this->assertFalse($story->fresh()->noindex);
    }
}
