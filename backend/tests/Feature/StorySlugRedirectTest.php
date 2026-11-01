<?php

declare(strict_types=1);

namespace Tests\Feature;

use App\Models\Chapter;
use App\Models\Story;
use App\Services\StoryIngestor;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

/** Đổi slug theo tiêu đề, URL cũ 301 vĩnh viễn — xem stories:reslug. */
class StorySlugRedirectTest extends TestCase
{
    use RefreshDatabase;

    private function story(string $title, string $slug): Story
    {
        $s = Story::create(['title' => $title, 'slug' => $slug, 'status' => 'completed']);
        Chapter::create(['story_id' => $s->id, 'number' => 1, 'title' => 'One', 'content' => 'Lorem ipsum.']);
        Chapter::create(['story_id' => $s->id, 'number' => 2, 'title' => 'Two', 'content' => 'Lorem ipsum.']);

        return $s;
    }

    public function test_reslug_doi_slug_va_url_cu_chuyen_301(): void
    {
        $s = $this->story('The Spare Key', 'my-husband-gave-his-mistress-the-spare-key-to-our-house-and-i-said-nothing');
        $s->forceFill(['updated_at' => '2026-10-01 10:00:00'])->saveQuietly();

        $this->artisan('stories:reslug', ['--dry-run' => true])->assertSuccessful();
        $this->assertSame('my-husband-gave-his-mistress-the-spare-key-to-our-house-and-i-said-nothing', $s->fresh()->slug);

        $this->artisan('stories:reslug')->assertSuccessful();
        $s->refresh();
        $this->assertSame('the-spare-key', $s->slug);
        $this->assertSame('2026-10-01 10:00:00', $s->updated_at->toDateTimeString()); // không đẩy truyện lên đầu /browse

        $old = '/story/my-husband-gave-his-mistress-the-spare-key-to-our-house-and-i-said-nothing';
        $this->get($old)->assertStatus(301)->assertRedirect(route('public.story', 'the-spare-key'));
        $this->get($old.'/chapter/2?utm_source=x')->assertStatus(301)
            ->assertRedirect(route('public.chapter', ['the-spare-key', 2]).'?utm_source=x');
        $this->get('/story/the-spare-key')->assertOk();

        // POST chấm điểm từ trang cũ còn trong cache: nhận luôn, không chuyển hướng.
        $this->assertNotContains($this->post($old.'/chapter/1/react', ['score' => 5])->status(), [301, 404]);
    }

    public function test_slug_dai_cat_o_ranh_gioi_tu_va_khong_cuop_slug_cua_truyen_khac(): void
    {
        $this->story('Deferred', 'deferred');
        $long = $this->story('Deferred', 'old-one');
        $longer = $this->story(str_repeat('Word ', 30), 'x');

        $this->artisan('stories:reslug')->assertSuccessful();

        $this->assertSame('deferred-2', $long->fresh()->slug);
        $this->assertLessThanOrEqual(60, strlen($longer->fresh()->slug));
        $this->assertStringEndsNotWith('-', $longer->fresh()->slug);
    }

    public function test_admin_doi_tieu_de_cung_giu_url_cu(): void
    {
        $s = $this->story('Old Title', 'old-title');

        $s->update(['title' => 'New Title', 'slug' => 'new-title']);

        $this->get('/story/old-title')->assertRedirect(route('public.story', 'new-title'));
    }

    public function test_slug_cu_khong_cap_cho_truyen_moi_va_ingest_bang_slug_cu_la_cap_nhat(): void
    {
        $s = $this->story('The Spare Key', 'long-old-slug');
        $s->update(['slug' => 'the-spare-key']);

        $this->assertTrue(Story::slugTaken('long-old-slug'));
        $this->assertFalse(Story::slugTaken('long-old-slug', $s->id));

        [$story, $created] = app(StoryIngestor::class)->upsertStory(['title' => 'The Spare Key', 'slug' => 'long-old-slug']);

        $this->assertFalse($created);
        $this->assertSame($s->id, $story->id);
        $this->assertSame('the-spare-key', $story->fresh()->slug);
        $this->assertSame(1, Story::count());
    }

    public function test_truyen_lay_lai_slug_cu_cua_chinh_no(): void
    {
        $s = $this->story('A', 'a-old');
        $s->update(['slug' => 'a-new']);
        $s->update(['slug' => 'a-old']);

        $this->get('/story/a-old')->assertOk();
        $this->get('/story/a-new')->assertRedirect(route('public.story', 'a-old'));
    }
}
