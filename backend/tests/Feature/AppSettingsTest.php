<?php

declare(strict_types=1);

namespace Tests\Feature;

use App\Models\Setting;
use App\Models\User;
use App\Support\AppSettings;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Cache;
use Tests\TestCase;

class AppSettingsTest extends TestCase
{
    use RefreshDatabase;

    public function test_api_config_tra_mac_dinh_khi_chua_luu_gi(): void
    {
        $this->getJson('/api/config')
            ->assertOk()
            ->assertExactJson(['ad_fallback' => ['enabled' => true, 'daily_limit' => 2]]);
    }

    public function test_trang_cau_hinh_doi_dang_nhap(): void
    {
        $this->get('/admin/settings')->assertRedirect('/admin/login');
        $this->put('/admin/settings', ['ad_fallback_daily_limit' => 5])->assertRedirect('/admin/login');
        $this->assertSame(0, Setting::query()->count());
    }

    public function test_admin_luu_duoc_va_api_tra_gia_tri_moi_ngay(): void
    {
        $admin = User::factory()->create();
        // Nạp cache trước để chắc chắn lưu xong thì cache bị xoá.
        $this->getJson('/api/config')->assertJsonPath('ad_fallback.daily_limit', 2);

        $this->actingAs($admin)->get('/admin/settings')->assertOk()->assertSee('Cấu hình app');

        $this->actingAs($admin)
            ->put('/admin/settings', ['ad_fallback_enabled' => '0', 'ad_fallback_daily_limit' => '5'])
            ->assertRedirect('/admin/settings');

        $this->getJson('/api/config')
            ->assertExactJson(['ad_fallback' => ['enabled' => false, 'daily_limit' => 5]]);
    }

    public function test_bo_chon_checkbox_thi_luu_la_tat(): void
    {
        $admin = User::factory()->create();
        AppSettings::update(['ad_fallback_enabled' => true]);

        // Trình duyệt không gửi checkbox bỏ chọn; chỉ có input hidden "0".
        $this->actingAs($admin)
            ->put('/admin/settings', ['ad_fallback_enabled' => '0', 'ad_fallback_daily_limit' => '2'])
            ->assertRedirect();
        $this->assertFalse(AppSettings::get('ad_fallback_enabled'));

        $this->actingAs($admin)
            // Bật: form gửi cả hidden "0" lẫn checkbox "1", PHP giữ giá trị sau cùng.
            ->put('/admin/settings', ['ad_fallback_enabled' => '1', 'ad_fallback_daily_limit' => '2'])
            ->assertRedirect();
        $this->assertTrue(AppSettings::get('ad_fallback_enabled'));
    }

    public function test_tu_choi_gia_tri_ngoai_khoang(): void
    {
        $admin = User::factory()->create();

        foreach (['-1', '21', 'abc', ''] as $bad) {
            $this->actingAs($admin)
                ->from('/admin/settings')
                ->put('/admin/settings', ['ad_fallback_enabled' => '1', 'ad_fallback_daily_limit' => $bad])
                ->assertSessionHasErrors('ad_fallback_daily_limit');
        }
        $this->assertSame(0, Setting::query()->count());
    }

    public function test_gia_tri_hong_trong_db_roi_ve_mac_dinh(): void
    {
        Setting::query()->create(['key' => 'ad_fallback_daily_limit', 'value' => '999']);
        Setting::query()->create(['key' => 'khoa_la', 'value' => 'x']);
        Cache::flush();

        $this->getJson('/api/config')
            ->assertExactJson(['ad_fallback' => ['enabled' => true, 'daily_limit' => 2]]);
    }
}
