<?php

declare(strict_types=1);

namespace Tests\Feature;

use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

/**
 * Trang Contact và Editorial standards.
 *
 * Hai trang này tồn tại vì AdSense đánh trượt site với lý do "nội dung có giá trị
 * thấp": người soát tìm kênh liên hệ và quy tắc biên tập trong điều hướng. Vì vậy
 * bài test không chỉ kiểm 200 — nó kiểm rằng LINK TỚI CHÚNG có mặt ở chân mọi
 * trang. Xoá link đi là trang vẫn sống nhưng không ai (kể cả người soát) tìm thấy.
 */
class PublicInfoPagesTest extends TestCase
{
    use RefreshDatabase;

    public function test_contact_page_shows_the_support_address(): void
    {
        $this->get('/contact')
            ->assertOk()
            ->assertSee('Contact')
            ->assertSee(config('app.support_email'))
            ->assertSee(config('app.publisher_name'));
    }

    public function test_editorial_page_states_how_stories_are_made(): void
    {
        $this->get('/editorial')
            ->assertOk()
            ->assertSee('Editorial standards')
            ->assertSee('AI writing tools')
            ->assertSee('house pen names');
    }

    public function test_every_page_links_to_contact_and_editorial(): void
    {
        foreach (['/', '/browse', '/about', '/contact', '/editorial'] as $path) {
            $this->get($path)
                ->assertOk()
                ->assertSee('href="'.route('public.contact').'"', false)
                ->assertSee('href="'.route('public.editorial').'"', false);
        }
    }

    public function test_sitemap_lists_both_pages(): void
    {
        $this->get('/sitemap.xml')
            ->assertOk()
            ->assertSee(route('public.contact'))
            ->assertSee(route('public.editorial'));
    }

    /**
     * Địa điểm bỏ trống thì KHÔNG in khối đó ra. Đây là lựa chọn có chủ ý: khai
     * sai nơi đặt trụ sở là lỗi chính sách, còn không khai thì không.
     */
    public function test_publisher_location_is_omitted_when_not_configured(): void
    {
        config(['app.publisher_location' => null]);

        $this->get('/contact')
            ->assertOk()
            ->assertDontSee('based in');
    }
}
