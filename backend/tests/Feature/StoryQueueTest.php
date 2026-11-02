<?php

declare(strict_types=1);

namespace Tests\Feature;

use App\Models\Category;
use App\Models\QueuedStory;
use App\Models\Story;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Storage;
use Tests\TestCase;

/** Kho truyện chờ đăng: xếp hàng cả lô, mỗi ngày đăng vài truyện đầu hàng. */
class StoryQueueTest extends TestCase
{
    use RefreshDatabase;

    private string $dir;

    protected function setUp(): void
    {
        parent::setUp();
        Storage::fake('local');
        Storage::fake('public');
        Category::create(['name' => 'Revenge', 'slug' => 'revenge']);
        $this->dir = sys_get_temp_dir().'/story-queue-test-'.uniqid();
        mkdir($this->dir.'/covers', 0777, true);
    }

    protected function tearDown(): void
    {
        array_map('unlink', glob($this->dir.'/covers/*') ?: []);
        array_map('unlink', glob($this->dir.'/*.json') ?: []);
        @rmdir($this->dir.'/covers');
        @rmdir($this->dir);
        parent::tearDown();
    }

    /** @param list<string> $categories */
    private function bundle(string $key, string $slug, array $categories = ['revenge']): array
    {
        return [
            'source_key' => $key,
            'story' => [
                'title' => ucfirst(str_replace('-', ' ', $slug)), 'slug' => $slug, 'author' => 'Tuan',
                'status' => 'completed', 'categories' => $categories, 'description' => 'A hook.',
            ],
            'chapters' => [
                ['number' => 1, 'title' => 'One', 'content' => 'First chapter.'],
                ['number' => 2, 'title' => 'Two', 'content' => 'Second chapter.'],
            ],
        ];
    }

    private function queueFile(array $bundles): string
    {
        foreach ($bundles as $b) {
            file_put_contents("{$this->dir}/covers/{$b['story']['slug']}.jpg", 'jpeg-bytes-'.$b['story']['slug']);
        }
        $path = "{$this->dir}/queue.json";
        file_put_contents($path, json_encode($bundles));

        return $path;
    }

    private function queue(array $bundles): void
    {
        $this->artisan('stories:queue-add', [
            'path' => $this->queueFile($bundles), '--source' => 'darkchapter',
            '--covers' => "{$this->dir}/covers", '--noindex' => true,
        ])->assertSuccessful();
    }

    public function test_xep_hang_khong_dang_ngay_va_giu_thu_tu(): void
    {
        $this->queue([$this->bundle('a', 'story-a'), $this->bundle('b', 'story-b')]);

        $this->assertSame(0, Story::count());
        $this->assertSame(['story-a', 'story-b'], QueuedStory::query()->pending()->get()
            ->map(fn ($q) => $q->payload['story']['slug'])->all());
        $this->assertTrue(Storage::disk('local')->exists(QueuedStory::first()->cover_path));
    }

    public function test_xep_hang_lai_khong_nhan_doi_va_khong_dong_vao_truyen_da_dang(): void
    {
        $this->queue([$this->bundle('a', 'story-a'), $this->bundle('b', 'story-b')]);
        $this->artisan('stories:release-queued', ['--count' => 1])->assertSuccessful();

        $changed = $this->bundle('b', 'story-b');
        $changed['story']['description'] = 'Better hook.';
        $this->queue([$this->bundle('a', 'story-a'), $changed]);

        $this->assertSame(2, QueuedStory::count());
        $this->assertSame('released', QueuedStory::where('source_key', 'a')->value('status'));
        $b = QueuedStory::where('source_key', 'b')->first();
        $this->assertSame('pending', $b->status);
        $this->assertSame('Better hook.', $b->payload['story']['description']);
        $this->assertSame(2, $b->position);
        $this->assertSame(1, Story::count());
    }

    public function test_dang_dung_so_luong_theo_thu_tu_kem_bia_va_noindex(): void
    {
        $this->queue([$this->bundle('a', 'story-a'), $this->bundle('b', 'story-b'), $this->bundle('c', 'story-c')]);

        $this->artisan('stories:release-queued', ['--count' => 2])->assertSuccessful();

        $this->assertSame(['story-a', 'story-b'], Story::orderBy('id')->pluck('slug')->all());
        $story = Story::where('slug', 'story-a')->first();
        $this->assertTrue($story->noindex);
        $this->assertSame(1, $story->free_chapters);
        $this->assertSame(2, $story->chapters()->count());
        $this->assertSame(['revenge'], $story->categories()->pluck('slug')->all());
        $this->assertSame('stories/story-a.jpg', $story->thumbnail);
        Storage::disk('public')->assertExists('stories/story-a.jpg');
        $item = QueuedStory::where('source_key', 'a')->first();
        $this->assertSame('released', $item->status);
        $this->assertSame($story->id, $item->story_id);
        Storage::disk('local')->assertMissing($item->cover_path);
        $this->assertSame(1, QueuedStory::where('status', 'pending')->count());
    }

    public function test_cau_moi_trong_goi_duoc_dang_cung_truyen(): void
    {
        $withHook = $this->bundle('a', 'story-a');
        $withHook['story']['hook'] = 'He brought his mistress to our anniversary dinner…';
        $this->queue([$withHook, $this->bundle('b', 'story-b')]);

        $this->artisan('stories:release-queued', ['--count' => 2])->assertSuccessful();

        $this->assertSame('He brought his mistress to our anniversary dinner…', Story::where('slug', 'story-a')->value('hook'));
        $this->assertNull(Story::where('slug', 'story-b')->value('hook'));
    }

    public function test_slug_da_bi_chiem_thi_doi_slug_khong_ghi_de_truyen_cu(): void
    {
        $old = Story::create(['title' => 'Old', 'slug' => 'story-a', 'status' => 'completed']);
        $this->queue([$this->bundle('a', 'story-a')]);

        $this->artisan('stories:release-queued', ['--count' => 1])->assertSuccessful();

        $this->assertSame('Old', $old->fresh()->title);
        $new = Story::where('slug', 'story-a-2')->first();
        $this->assertNotNull($new);
        $this->assertSame('stories/story-a-2.jpg', $new->thumbnail);
    }

    public function test_goi_hong_bi_danh_dau_loi_va_khong_chan_hang_sau(): void
    {
        $this->queue([$this->bundle('a', 'story-a'), $this->bundle('b', 'story-b'), $this->bundle('c', 'story-c')]);
        // Thể loại bị xoá trong lúc truyện nằm chờ.
        $item = QueuedStory::where('source_key', 'a')->first();
        $payload = $item->payload;
        $payload['story']['categories'] = ['da-xoa'];
        $item->update(['payload' => $payload]);

        $this->artisan('stories:release-queued', ['--count' => 2])->assertFailed();

        $this->assertSame('failed', $item->fresh()->status);
        $this->assertStringContainsString('da-xoa', (string) $item->fresh()->error);
        $this->assertSame(['story-b', 'story-c'], Story::orderBy('id')->pluck('slug')->all());
    }

    public function test_admin_bo_truyen_khoi_kho(): void
    {
        $this->queue([$this->bundle('a', 'story-a'), $this->bundle('b', 'story-b')]);
        $item = QueuedStory::where('source_key', 'a')->first();
        $admin = User::factory()->create();

        $this->actingAs($admin)->get('/admin/queue')->assertOk()->assertSee('Story a')->assertSee('Story b');
        $this->actingAs($admin)->delete("/admin/queue/{$item->id}")->assertRedirect('/admin/queue');

        $this->assertSame('skipped', $item->fresh()->status);
        $this->artisan('stories:release-queued', ['--count' => 10])->assertSuccessful();
        $this->assertSame(['story-b'], Story::pluck('slug')->all());

        // Xếp hàng lại cả lô cũng không đưa truyện đã bỏ trở lại.
        $this->queue([$this->bundle('a', 'story-a')]);
        $this->assertSame('skipped', $item->fresh()->status);
    }

    public function test_lich_chay_moi_ngay(): void
    {
        $this->artisan('schedule:list')->expectsOutputToContain('stories:release-queued')->assertSuccessful();
    }
}
