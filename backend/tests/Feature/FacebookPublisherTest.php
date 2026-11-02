<?php

declare(strict_types=1);

namespace Tests\Feature;

use App\Models\Chapter;
use App\Models\FacebookPost;
use App\Models\Story;
use App\Services\FacebookPublisher;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Http\Client\Request;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Storage;
use Tests\TestCase;

/** Tự đăng truyện lên Page Facebook TunaStory (02/11/2026) — xem App\Services\FacebookPublisher. */
class FacebookPublisherTest extends TestCase
{
    use RefreshDatabase;

    private const OPENING = "Lena found the receipt in her husband's coat on a Tuesday.\n\nIt was for a ring she had never seen.";

    protected function setUp(): void
    {
        parent::setUp();

        config([
            'services.facebook.enabled' => true,
            'services.facebook.page_id' => '123',
            'services.facebook.page_token' => 'token',
            'services.facebook.exclude' => [],
            'services.indexnow.site' => 'https://tunastory.com',
        ]);
        Storage::fake('public');
    }

    private function story(string $slug, array $attrs = [], bool $chapter = true): Story
    {
        $s = Story::create($attrs + [
            'title' => ucfirst($slug),
            'slug' => $slug,
            'status' => 'completed',
            'hook' => 'My husband bought a ring for someone else',
            'thumbnail' => "covers/$slug.jpg",
        ]);
        if ($chapter) {
            Chapter::create(['story_id' => $s->id, 'number' => 1, 'title' => 'Part 1', 'content' => self::OPENING]);
        }
        Storage::disk('public')->put("covers/$slug.jpg", 'jpeg');

        return $s;
    }

    private function fakeGraph(bool $firstCommentFails = false): void
    {
        $comments = Http::sequence();
        if ($firstCommentFails) {
            $comments->push(['error' => ['message' => 'nope']], 500);
        }

        Http::fake([
            'graph.facebook.com/*/photos' => Http::response(['id' => '9', 'post_id' => '123_9']),
            'graph.facebook.com/*/comments' => $comments->push(['id' => '123_9_1']),
        ]);
    }

    private static function part(Request $request, string $name): ?string
    {
        $part = collect($request->data())->firstWhere('name', $name);

        return $part['contents'] ?? null;
    }

    public function test_chon_truyen_nhieu_luot_xem_nhat_chua_dang(): void
    {
        $this->story('it-xem', ['views' => 5]);
        $this->story('nhieu-xem', ['views' => 50]);
        $this->story('noindex', ['views' => 900, 'noindex' => true]);
        $this->story('an-web', ['views' => 900, 'hidden_on_web' => true]);
        $this->story('khong-hook', ['views' => 900, 'hook' => null]);
        $this->story('khong-bia', ['views' => 900, 'thumbnail' => null]);
        $this->story('khong-chuong', ['views' => 900], chapter: false);
        $loai = $this->story('bi-loai', ['views' => 900]);
        $daDang = $this->story('da-dang', ['views' => 900]);
        FacebookPost::create(['story_id' => $daDang->id, 'post_id' => 'x']);
        config(['services.facebook.exclude' => [(string) $loai->id]]);

        $this->assertSame('nhieu-xem', app(FacebookPublisher::class)->next()?->slug);
    }

    public function test_noi_dung_bai_khong_co_link_va_co_cau_moi_binh_luan(): void
    {
        $caption = app(FacebookPublisher::class)->caption($this->story('a'), 0);

        $this->assertStringStartsWith('My husband bought a ring for someone else', $caption);
        $this->assertStringContainsString("It was for a ring she had never seen.\n\nDo you want to keep reading? Comment below 👇", $caption);
        $this->assertStringEndsWith('📖 Full story in the comments', $caption);
        $this->assertStringNotContainsString('http', $caption);
        $this->assertStringNotContainsString('tunastory.com', $caption);
    }

    public function test_cau_moi_trung_doan_mo_dau_thi_khong_nhac_lai(): void
    {
        $story = $this->story('a', ['hook' => "Lena found the receipt in her husband's coat"]);

        $caption = app(FacebookPublisher::class)->caption($story, 1);

        $this->assertStringStartsWith("Lena found the receipt in her husband's coat on a Tuesday.", $caption);
        $this->assertSame(1, substr_count($caption, 'Lena found the receipt'));
        $this->assertStringContainsString('Want to know what happens next?', $caption);
    }

    public function test_dang_anh_bia_roi_binh_luan_link(): void
    {
        $this->fakeGraph();
        $story = $this->story('a');

        $this->artisan('facebook:post-next')->assertSuccessful();

        $record = FacebookPost::query()->sole();
        $this->assertSame($story->id, $record->story_id);
        $this->assertSame('123_9', $record->post_id);
        $this->assertSame('123_9_1', $record->comment_id);
        $this->assertNotNull($record->posted_at);

        Http::assertSent(fn (Request $r) => str_ends_with($r->url(), '/v23.0/123/photos')
            && $r->hasFile('source', 'jpeg', 'a.jpg')
            && str_contains((string) self::part($r, 'message'), 'Full story in the comments')
            && ! str_contains((string) self::part($r, 'message'), 'http'));
        Http::assertSent(fn (Request $r) => str_ends_with($r->url(), '/v23.0/123_9/comments')
            && $r['message'] === '📖 Read the full story free here: https://tunastory.com/story/a');

        // Lượt sau không đăng lại truyện đã đăng.
        $this->artisan('facebook:post-next')->expectsOutput('Không còn truyện nào để đăng.')->assertSuccessful();
        Http::assertSentCount(2);
    }

    public function test_binh_luan_hong_thi_luot_sau_gui_bu(): void
    {
        $this->fakeGraph(firstCommentFails: true);
        $this->story('a');

        $this->artisan('facebook:post-next')->assertSuccessful();
        $record = FacebookPost::query()->sole();
        $this->assertNull($record->comment_id);
        $this->assertStringStartsWith('comment:', (string) $record->error);

        // Hết truyện để đăng vẫn phải gửi bù bình luận cho bài cuối.
        $this->artisan('facebook:post-next')->expectsOutput('Không còn truyện nào để đăng.')->assertSuccessful();

        $record->refresh();
        $this->assertSame('123_9_1', $record->comment_id);
        $this->assertNull($record->error);
        Http::assertSentCount(3);
    }

    public function test_chua_co_token_thi_khong_goi_facebook(): void
    {
        Http::fake();
        config(['services.facebook.page_token' => null]);
        $this->story('a');

        $this->artisan('facebook:post-next')->assertSuccessful();

        Http::assertNothingSent();
        $this->assertSame(0, FacebookPost::query()->count());
    }

    public function test_luu_page_token_vao_env(): void
    {
        $dir = sys_get_temp_dir().'/fb-env-'.uniqid();
        mkdir($dir);
        file_put_contents("$dir/.env", "APP_NAME=Stories\nFACEBOOK_PAGE_ID=old\n");
        $this->app->useEnvironmentPath($dir);
        Http::fake([
            'graph.facebook.com/*/me*' => Http::response(['id' => '615', 'name' => 'TunaStory', 'category' => 'Media']),
            'graph.facebook.com/*/debug_token*' => Http::response(['data' => [
                'type' => 'PAGE', 'expires_at' => 0, 'scopes' => ['pages_manage_posts', 'pages_manage_engagement'],
            ]]),
        ]);

        $this->artisan('facebook:set-token')
            ->expectsQuestion('Dán Page access token (gõ/dán sẽ không hiện ra)', ' EAAtoken ')
            ->expectsOutputToContain('Token không hết hạn.')
            ->assertSuccessful();

        $this->assertSame(
            "APP_NAME=Stories\nFACEBOOK_PAGE_ID=615\nFACEBOOK_ENABLED=true\nFACEBOOK_PAGE_TOKEN=EAAtoken\n",
            file_get_contents("$dir/.env"),
        );
        Http::assertSent(fn (Request $r) => str_contains($r->url(), '/me?') && $r['access_token'] === 'EAAtoken');
    }

    public function test_token_nguoi_dung_thi_khong_ghi(): void
    {
        $dir = sys_get_temp_dir().'/fb-env-'.uniqid();
        mkdir($dir);
        file_put_contents("$dir/.env", "APP_NAME=Stories\n");
        $this->app->useEnvironmentPath($dir);
        Http::fake(['graph.facebook.com/*' => Http::response(['error' => ['message' => 'Tried accessing nonexisting field (category) on node type (User)']], 400)]);

        $this->artisan('facebook:set-token')
            ->expectsQuestion('Dán Page access token (gõ/dán sẽ không hiện ra)', 'EAAuser')
            ->assertFailed();

        $this->assertSame("APP_NAME=Stories\n", file_get_contents("$dir/.env"));
    }

    public function test_dry_chi_in_noi_dung(): void
    {
        Http::fake();
        $this->story('a');

        $this->artisan('facebook:post-next --dry')
            ->expectsOutputToContain('Read the full story free here: https://tunastory.com/story/a')
            ->assertSuccessful();

        Http::assertNothingSent();
    }
}
