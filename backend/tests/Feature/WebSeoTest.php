<?php

declare(strict_types=1);

namespace Tests\Feature;

use App\Models\Category;
use App\Models\Chapter;
use App\Models\Story;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

/** Đợt sửa Google Search + AdSense 01/11/2026 — xem memory seo-adsense-ke-hoach. */
class WebSeoTest extends TestCase
{
    use RefreshDatabase;

    private function story(string $slug, array $attrs = [], int $chapters = 1): Story
    {
        $s = Story::create($attrs + ['title' => ucfirst($slug), 'slug' => $slug, 'status' => 'completed']);
        for ($n = 1; $n <= $chapters; $n++) {
            Chapter::create(['story_id' => $s->id, 'number' => $n, 'title' => "Part $n", 'content' => str_repeat('Lorem ipsum dolor sit amet, consectetur adipiscing elit. ', 4)]);
        }

        return $s;
    }

    public function test_phan_trang_khong_mat_truyen_khi_trung_gio_cap_nhat(): void
    {
        for ($i = 1; $i <= 30; $i++) {
            $this->story("s$i")->forceFill(['updated_at' => '2026-10-01 10:00:00'])->saveQuietly();
        }

        $seen = [];
        foreach ([1, 2] as $page) {
            preg_match_all('#/story/(s\d+)"#', $this->get("/browse?page=$page")->assertOk()->getContent(), $m);
            $seen = array_merge($seen, array_unique($m[1]));
        }

        $this->assertCount(30, array_unique($seen));
    }

    public function test_trang_noindex_khong_co_quang_cao(): void
    {
        config(['app.admob_publisher_id' => 'pub-123']);
        $this->story('trung', ['noindex' => true]);
        $this->story('rieng');

        $this->get('/story/trung')->assertOk()->assertDontSee('google-adsense-account', false)->assertDontSee('adsbygoogle.js', false);
        $this->get('/story/trung/chapter/1')->assertOk()->assertDontSee('google-adsense-account', false);
        $this->get('/story/rieng')->assertOk()->assertSee('google-adsense-account', false);
        $this->get('/story/rieng/chapter/1')->assertOk()->assertSee('google-adsense-account', false);
    }

    public function test_ten_chuong_part_n_khong_lap_lai_so_chuong(): void
    {
        $this->story('a', chapters: 2);

        $html = $this->get('/story/a/chapter/2')->assertOk()->getContent();

        $this->assertStringContainsString('<h1>Chapter 2</h1>', $html);
        $this->assertStringNotContainsString('Part 2', $html);
        $this->assertStringContainsString('<meta name="description" content="Chapter 2 of A. ', $html);
    }

    public function test_mo_ta_chuong_khong_ra_dau_cham_phay(): void
    {
        $s = $this->story('b');
        $s->chapters()->first()->update(['title' => 'She Said No.']);

        $this->get('/story/b/chapter/1')->assertOk()
            ->assertSee('content="She Said No, chapter 1 of B.', false);
    }

    public function test_canonical_bo_page_khong_phai_so(): void
    {
        $this->story('c');

        $this->get('/browse?page=abc')->assertOk()
            ->assertSee('<link rel="canonical" href="'.route('public.browse').'">', false);
    }

    public function test_byline_ten_that_va_json_ld_person(): void
    {
        config(['app.editor_name' => 'Tuan Chu']);
        $this->story('d', ['author' => 'Vivian Pryce']);

        $this->get('/story/d')->assertOk()
            ->assertSee('Edited by <a href="'.route('public.editor').'">Tuan Chu</a>', false)
            ->assertDontSee('Vivian Pryce')
            ->assertDontSee('Author names are house pen names')
            ->assertSee('"author":{"@type":"Person","name":"Tuan Chu","url":"'.route('public.editor').'"}', false);

        $this->get('/story/d/chapter/1')->assertOk()
            ->assertSee('<meta name="author" content="Tuan Chu">', false)
            ->assertSee('"@type":"Person","name":"Tuan Chu"', false);
    }

    public function test_trang_nguoi_bien_tap(): void
    {
        config(['app.editor_name' => 'Tuan Chu']);
        $this->story('e');

        $this->get('/editor')->assertOk()
            ->assertSee('<h1>Tuan Chu</h1>', false)
            ->assertSee('AI writing tools')
            ->assertSee('"@type":"ProfilePage"', false);
        $this->get('/sitemap.xml')->assertSee(route('public.editor').'</loc>', false);
        $this->get('/about')->assertOk()->assertSee('href="'.route('public.editor').'"', false);
    }

    public function test_breadcrumb_json_ld_khop_duong_dan_hien_thi(): void
    {
        $cat = Category::create(['name' => 'Revenge', 'slug' => 'revenge']);
        $this->story('f')->categories()->attach($cat);

        $this->get('/story/f')->assertOk()
            ->assertSee('"position":2,"name":"Stories","item":"'.route('public.browse').'"', false)
            ->assertSee('"position":3,"name":"Revenge","item":"'.route('public.category', $cat).'"', false);
    }

    public function test_trang_sau_co_so_trang_va_link_trang_1_tran(): void
    {
        for ($i = 1; $i <= 30; $i++) {
            $this->story("p$i");
        }

        $this->get('/browse?page=2')->assertOk()
            ->assertSee('<title>Browse all stories — page 2 — Stories</title>', false)
            ->assertSee('Page 2 of 2.')
            ->assertSee('<a href="'.route('public.browse').'" rel="prev">', false)
            ->assertDontSee('page=1"', false)
            ->assertSee('"url":"'.route('public.browse').'?page=2"', false);
    }

    public function test_the_loai_co_doan_gioi_thieu_o_trang_1(): void
    {
        $cat = Category::create(['name' => 'Revenge', 'slug' => 'revenge']);
        for ($i = 1; $i <= 5; $i++) {
            $this->story("r$i")->categories()->attach($cat);
        }

        $this->get('/genre/revenge')->assertOk()->assertSee('<p class="intro">Revenge stories follow', false);
    }

    public function test_trang_404_mang_giao_dien_site(): void
    {
        $this->get('/story/khong-co')->assertNotFound()
            ->assertSee('Page not found')
            ->assertSee('<meta name="robots" content="noindex, follow">', false)
            ->assertSee('href="'.route('public.browse').'"', false);
    }

    public function test_url_co_gach_cheo_cuoi_chuyen_301(): void
    {
        $this->story('k');

        // $this->get() tự cắt "/" cuối của URI, nên phải gửi thẳng qua kernel mới thử được.
        $send = fn (string $uri, string $method = 'GET') => $this->app->make(\Illuminate\Contracts\Http\Kernel::class)
            ->handle(\Illuminate\Http\Request::create($uri, $method));

        $r = $send('/browse/');
        $this->assertSame(301, $r->getStatusCode());
        $this->assertSame(route('public.browse'), $r->headers->get('Location'));

        $r = $send('/story/k/chapter/1/?utm_source=x');
        $this->assertSame(301, $r->getStatusCode());
        $this->assertSame(route('public.chapter', ['k', 1]).'?utm_source=x', $r->headers->get('Location'));

        $this->assertSame(200, $send('/')->getStatusCode());
        $this->assertNotSame(301, $send('/api/home/')->getStatusCode()); // /api không bị đụng tới
        $this->assertNotSame(301, $send('/story/k/chapter/1/react/', 'POST')->getStatusCode());
    }

    public function test_chuong_cuoi_cua_truyen_dang_viet_khong_ghi_the_end(): void
    {
        $this->story('dang-viet', ['status' => 'ongoing'], chapters: 2);
        $this->story('xong', chapters: 1);

        $this->get('/story/dang-viet/chapter/2')->assertOk()->assertSee('More chapters coming')->assertDontSee('The end');
        $this->get('/story/xong/chapter/1')->assertOk()->assertSee('The end');
        $this->get('/about')->assertOk()->assertSee('The 1 still being written');
    }

    public function test_privacy_co_cau_bat_buoc_cua_adsense(): void
    {
        $this->get('/privacy')->assertOk()
            ->assertSee('Third-party vendors, including Google, use cookies to serve ads based on your prior visits')
            ->assertSee('https://adssettings.google.com/', false);
    }

    public function test_chuong_cuoi_goi_y_truyen_khac(): void
    {
        $cat = Category::create(['name' => 'Drama', 'slug' => 'drama']);
        $this->story('g', chapters: 2)->categories()->attach($cat);
        $this->story('h')->categories()->attach($cat);

        $this->get('/story/g/chapter/1')->assertOk()->assertDontSee('Read next');
        $this->get('/story/g/chapter/2')->assertOk()
            ->assertSee('Read next')
            ->assertSee('href="'.route('public.story', 'h').'"', false);
    }

    public function test_cau_moi_hien_duoi_tua_va_noi_vao_title(): void
    {
        $this->story('office', ['title' => 'The Office My Mother Kept', 'hook' => 'Twelve hours after my husband slapped me in a crowded restaurant to defend his first love…']);
        $this->story('suit', ['title' => 'The Suit', 'hook' => 'My boss fired me after five years.']);
        $this->story('plain', ['title' => 'Plain']);

        $this->get('/story/office')->assertOk()
            ->assertSee('<title>The Office My Mother Kept — Twelve hours after my husband slapped me in a crowded restaurant to defend his first love… — Stories</title>', false)
            ->assertSee('<p class="sh-hook">Twelve hours after my husband slapped me', false)
            ->assertSee('<h1>The Office My Mother Kept</h1>', false)
            ->assertSee('"alternateName":"Twelve hours after', false);
        // Dấu chấm cuối câu không lọt vào <title>.
        $this->get('/story/suit')->assertOk()->assertSee('<title>The Suit — My boss fired me after five years — Stories</title>', false);
        $this->get('/story/plain')->assertOk()
            ->assertSee('<title>Plain — Stories</title>', false)
            ->assertDontSee('<p class="sh-hook">', false)
            ->assertDontSee('alternateName', false);
        // Trang chương giữ tựa ngắn.
        $this->get('/story/office/chapter/1')->assertOk()->assertSee('<title>The Office My Mother Kept · Chapter 1 — Stories</title>', false);
    }

    public function test_admin_sua_cau_moi_khong_doi_slug(): void
    {
        $title = 'A title long enough that its full slug runs well past the sixty character cap';
        $s = $this->story('a-title-long-enough-that-its-full-slug-runs-well-past-the', ['title' => $title]);

        $this->actingAs(User::factory()->create())
            ->put("/admin/stories/{$s->id}", ['title' => $title, 'hook' => 'New hook.', 'status' => 'completed', 'free_chapters' => 1])
            ->assertRedirect();

        $s->refresh();
        $this->assertSame('New hook.', $s->hook);
        $this->assertSame('a-title-long-enough-that-its-full-slug-runs-well-past-the', $s->slug);
    }

    public function test_truyen_index_dung_truoc_truyen_noindex(): void
    {
        $cat = Category::create(['name' => 'Revenge', 'slug' => 'revenge']);
        $this->story('cu-index', ['views' => 1])->categories()->attach($cat);
        $moi = $this->story('moi-noindex', ['noindex' => true, 'views' => 999]);
        $moi->categories()->attach($cat);
        $moi->forceFill(['updated_at' => now()->addDay()])->saveQuietly();

        foreach (['/browse', '/genre/revenge'] as $url) {
            $html = $this->get($url)->assertOk()->getContent();
            $this->assertLessThan(strpos($html, '/story/moi-noindex"'), strpos($html, '/story/cu-index"'), $url);
        }

        // Trang chủ: xếp hạng chỉ có truyện index; "mới cập nhật" vẫn hiện truyện noindex mới nhất.
        $home = $this->get('/')->assertOk()->getContent();
        $this->assertSame(1, substr_count($home, '/story/moi-noindex"'));
        $this->assertStringContainsString('/story/cu-index"', $home);
    }

    public function test_trang_truyen_co_doan_mo_dau_chuong_1(): void
    {
        $s = $this->story('mo-dau', chapters: 2);
        $para = fn (int $i) => "Paragraph $i ".str_repeat('word ', 48).'end.';
        $s->chapters()->where('number', 1)->update(['content' => implode("\n\n", array_map($para, range(1, 6)))]);

        $html = $this->get('/story/mo-dau')->assertOk()
            ->assertSee('Read the opening')
            ->assertSee('<p>Paragraph 1 word', false)
            ->assertSee('<p>Paragraph 3 word', false)
            ->assertSee('href="'.route('public.chapter', ['mo-dau', 1]).'">Keep reading chapter 1', false)
            ->getContent();
        // Dừng khi đủ ~120 từ (mỗi đoạn ~51 từ → 3 đoạn), không chép cả chương.
        $this->assertStringNotContainsString('Paragraph 4', $html);
    }

    public function test_doan_mo_dau_dai_qua_thi_cat_o_het_cau(): void
    {
        $s = $this->story('dai');
        $sentence = 'This sentence has exactly ten words in it, all right. ';
        $s->chapters()->first()->update(['content' => str_repeat($sentence, 40)]);

        $html = $this->get('/story/dai')->assertOk()->getContent();
        preg_match('#<div class="synopsis opening">\s*<p>(.*?)</p>#s', $html, $m);
        $this->assertNotEmpty($m);
        $this->assertStringEndsWith('all right.', $m[1]);
        $this->assertLessThanOrEqual(220, str_word_count($m[1]));
    }

    public function test_tua_trang_the_loai(): void
    {
        $cat = Category::create(['name' => 'CEO', 'slug' => 'ceo']);
        for ($i = 1; $i <= 5; $i++) {
            $this->story("c$i")->categories()->attach($cat);
        }

        $this->get('/genre/ceo')->assertOk()->assertSee('<title>CEO stories — free to read online — Stories</title>', false);
    }

    // --- Đợt 02/11/2026: Discover, RSS, IndexNow, nút chia sẻ ---

    public function test_robots_cho_google_dung_anh_lon(): void
    {
        $this->story('anh');

        $this->get('/story/anh')->assertOk()
            ->assertSee('<meta name="robots" content="index, follow, max-image-preview:large">', false);
    }

    public function test_json_ld_book_co_ngay_dang_va_ngay_sua(): void
    {
        $s = $this->story('ngay');
        $s->forceFill(['created_at' => '2026-10-01 08:00:00', 'updated_at' => '2026-10-05 09:30:00'])->saveQuietly();

        $this->get('/story/ngay')->assertOk()
            ->assertSee('"datePublished":"2026-10-01T08:00:00', false)
            ->assertSee('"dateModified":"2026-10-05T09:30:00', false);
    }

    public function test_feed_rss_chi_co_truyen_index_moi_nhat_truoc(): void
    {
        $cat = Category::create(['name' => 'Revenge', 'slug' => 'revenge']);
        $this->story('cu', ['hook' => 'She kept the receipt.', 'description' => 'Old one.'])
            ->forceFill(['created_at' => '2026-10-01 08:00:00'])->saveQuietly();
        $this->story('moi', ['description' => 'New & shiny.'])->categories()->attach($cat);
        $this->story('trung', ['noindex' => true]);
        $this->story('an', ['hidden_on_web' => true]);

        $res = $this->get('/feed.xml')->assertOk();
        $this->assertStringStartsWith('application/rss+xml', $res->headers->get('Content-Type'));

        $xml = simplexml_load_string($res->getContent());
        $this->assertNotFalse($xml, 'feed phải là XML hợp lệ');
        $links = array_map('strval', $xml->xpath('//item/link'));
        $this->assertSame([route('public.story', 'moi'), route('public.story', 'cu')], $links);

        $res->assertSee('<category>Revenge</category>', false)
            ->assertSee('New &amp; shiny.', false)
            ->assertSee("She kept the receipt.\n\nOld one.", false)
            ->assertSee('<atom:link href="'.route('public.feed').'" rel="self"', false);

        $this->get('/story/moi')->assertSee('type="application/rss+xml"', false);
    }

    public function test_file_khoa_indexnow(): void
    {
        $key = \App\Services\IndexNow::key();
        $this->assertMatchesRegularExpression('/^[a-f0-9]{32}$/', $key);

        $this->get("/{$key}.txt")->assertOk()->assertSeeText($key);
        $this->get('/'.str_repeat('a', 32).'.txt')->assertNotFound();
    }

    public function test_lenh_indexnow_gui_url_tren_ten_mien_web(): void
    {
        config(['services.indexnow.enabled' => true, 'services.indexnow.site' => 'https://tunastory.com']);
        \Illuminate\Support\Facades\Http::fake(['api.indexnow.org/*' => \Illuminate\Support\Facades\Http::response('', 200)]);

        $this->story('gui', chapters: 2);
        $this->story('khong-gui', ['noindex' => true]);
        $this->story('cu-qua')->forceFill(['updated_at' => now()->subDays(3)])->saveQuietly();
        \App\Models\Chapter::query()->whereHas('story', fn ($q) => $q->where('slug', 'cu-qua'))
            ->update(['updated_at' => now()->subDays(3)]);

        // Mốc so sánh tính tới giây và gồm cả giây đó (gửi trùng thì vô hại, sót mới hại),
        // nên tách lượt chạy khỏi giây tạo truyện để lượt sau thật sự không còn gì mới.
        $this->travel(1)->minutes();
        $this->artisan('seo:indexnow')->assertSuccessful();

        \Illuminate\Support\Facades\Http::assertSent(function ($request) {
            $key = \App\Services\IndexNow::key();

            return $request['host'] === 'tunastory.com'
                && $request['key'] === $key
                && $request['keyLocation'] === "https://tunastory.com/{$key}.txt"
                && $request['urlList'] === [
                    'https://tunastory.com/story/gui',
                    'https://tunastory.com/story/gui/chapter/1',
                    'https://tunastory.com/story/gui/chapter/2',
                ];
        });

        // Lần sau chỉ gửi những gì sửa từ lần trước — không có gì mới thì không gọi API.
        $this->travel(1)->hours();
        $this->artisan('seo:indexnow')->assertSuccessful();
        \Illuminate\Support\Facades\Http::assertSentCount(1);
    }

    public function test_lenh_indexnow_tat_thi_khong_gui(): void
    {
        config(['services.indexnow.enabled' => false]);
        \Illuminate\Support\Facades\Http::fake();
        $this->story('tat');

        $this->artisan('seo:indexnow')->assertSuccessful();
        \Illuminate\Support\Facades\Http::assertNothingSent();
    }

    public function test_nut_chia_se_tro_ve_trang_truyen(): void
    {
        $this->story('chia-se', ['hook' => 'He sold the house.']);
        $story = route('public.story', 'chia-se');

        $this->get('/story/chia-se')->assertOk()
            ->assertSee('https://www.facebook.com/sharer/sharer.php?u='.urlencode($story), false)
            ->assertSee('https://www.pinterest.com/pin/create/button/?url='.urlencode($story), false);

        // Cuối chương vẫn chia sẻ trang truyện, không phải trang chương.
        $this->get('/story/chia-se/chapter/1')->assertOk()
            ->assertSee('sharer.php?u='.urlencode($story).'"', false)
            ->assertSee('data-share-url="'.$story.'"', false);
    }
}
