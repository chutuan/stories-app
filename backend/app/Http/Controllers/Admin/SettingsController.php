<?php

declare(strict_types=1);

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Support\AppSettings;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\View\View;

/**
 * Trang Cấu hình: chỉnh chính sách của app di động mà không cần build lại.
 * App đọc qua GET /api/config lúc mở và khi quay lại app (tối đa 10 phút/lần).
 */
class SettingsController extends Controller
{
    public function index(): View
    {
        return view('admin.settings.index', [
            'settings' => AppSettings::all(),
            'limitMax' => AppSettings::DEFINITIONS['ad_fallback_daily_limit'][3],
        ]);
    }

    public function update(Request $request): RedirectResponse
    {
        $max = AppSettings::DEFINITIONS['ad_fallback_daily_limit'][3];
        $data = $request->validate([
            'ad_fallback_daily_limit' => ['required', 'integer', 'min:0', "max:{$max}"],
        ], [], [
            'ad_fallback_daily_limit' => 'số lần cấp bù mỗi ngày',
        ]);

        AppSettings::update([
            // Checkbox bỏ chọn thì trình duyệt không gửi gì -> boolean() ra false.
            'ad_fallback_enabled' => $request->boolean('ad_fallback_enabled'),
            'ad_fallback_daily_limit' => (int) $data['ad_fallback_daily_limit'],
        ]);

        return redirect()->route('admin.settings.index')
            ->with('status', 'Đã lưu cấu hình. App sẽ nhận trong vòng 10 phút, hoặc ngay khi mở lại.');
    }
}
