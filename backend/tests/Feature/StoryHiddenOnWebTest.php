<?php

declare(strict_types=1);

namespace Tests\Feature;

use App\Models\Category;
use App\Models\Chapter;
use App\Models\Story;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

/**
 * Truyện ẩn trên web (nội dung nhà quảng cáo chặn): web coi như không tồn tại, app
 * vẫn hiện. Và thể loại mỏng không lên menu/sitemap của web.
 */
class StoryHiddenOnWebTest extends TestCase
{
    use RefreshDatabase;

    private function story(string $slug, bool $hidden = false, ?Category $category = null): Story
    {
        $s = Story::create(['title' => ucfirst($slug), 'slug' => $slug, 'status' => 'completed', 'hidden_on_web' => $hidden]);
        Chapter::create(['story_id' => $s->id, 'number' => 1, 'title' => 'Chapter 1: Start', 'content' => 'Lorem ipsum.']);
        if ($category) {
            $s->categories()->attach($category);
        }

        return $s;
    }

    public function test_truyen_an_tra_404_tren_web(): void
    {
        $this->story('an', true);

        $this->get('/story/an')->assertNotFound();
        $this->get('/story/an/chapter/1')->assertNotFound();
        $this->get('/og/an.jpg')->assertNotFound();
        $this->post('/story/an/chapter/1/react', ['score' => 5])->assertNotFound();
    }

    public function test_truyen_an_vang_mat_khoi_luoi_tim_kiem_va_sitemap(): void
    {
        $this->story('an-giau', true);
        $this->story('hien-ro', false);

        foreach (['/', '/browse', '/search?q=giau', '/sitemap.xml'] as $url) {
            $this->get($url)->assertOk()->assertDontSee('/story/an-giau', false);
        }
        $this->get('/browse')->assertSee('/story/hien-ro', false);
    }

    public function test_app_van_thay_truyen_an_tren_web(): void
    {
        $story = $this->story('an', true);

        $this->getJson("/api/stories/{$story->id}")->assertOk()->assertJsonPath('id', $story->id);
        $this->getJson("/api/stories/{$story->id}/chapters/1")->assertOk();
    }

    public function test_the_loai_mong_khong_len_menu_sitemap_va_noindex(): void
    {
        $thin = Category::create(['name' => 'Mecha', 'slug' => 'mecha']);
        $full = Category::create(['name' => 'Revenge', 'slug' => 'revenge']);
        $this->story('mecha-1', false, $thin);
        for ($i = 1; $i <= Category::WEB_MIN_STORIES; $i++) {
            $this->story("revenge-{$i}", false, $full);
        }
        // Truyện ẩn trên web không được tính để đẩy một thể loại qua ngưỡng.
        for ($i = 1; $i < Category::WEB_MIN_STORIES; $i++) {
            $this->story("mecha-an-{$i}", true, $thin);
        }

        $this->get('/browse')->assertOk()
            ->assertSee('/genre/revenge', false)
            ->assertDontSee('/genre/mecha"', false);
        $this->get('/sitemap.xml')->assertOk()
            ->assertSee('/genre/revenge</loc>', false)
            ->assertDontSee('/genre/mecha</loc>', false);
        // Vẫn mở được (thẻ truyện có link tới), nhưng không cho lập chỉ mục.
        $this->get('/genre/mecha')->assertOk()
            ->assertSee('<meta name="robots" content="noindex, follow">', false);
        $this->get('/genre/revenge')->assertOk()
            ->assertSee('<meta name="robots" content="index, follow">', false);
    }

    public function test_admin_bat_tat_an_tren_web(): void
    {
        $story = $this->story('an', false);
        $admin = User::factory()->create();
        $form = ['title' => 'An', 'status' => 'completed', 'free_chapters' => 1];

        $this->actingAs($admin)->put("/admin/stories/{$story->id}", $form + ['hidden_on_web' => '1'])->assertRedirect();
        $this->assertTrue($story->fresh()->hidden_on_web);

        $this->actingAs($admin)->put("/admin/stories/{$story->id}", $form + ['hidden_on_web' => '0'])->assertRedirect();
        $this->assertFalse($story->fresh()->hidden_on_web);
    }
}
