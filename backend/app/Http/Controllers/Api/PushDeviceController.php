<?php

declare(strict_types=1);

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Models\PushDevice;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Http\Response;

/**
 * Đăng ký / huỷ thông báo "truyện mới mỗi ngày" của app di động.
 *
 * Không có đăng nhập: ai cầm token thì người đó quản lý token ấy. API không bao giờ
 * trả token ra ngoài, nên đăng ký hay xoá một token chỉ quyết định máy đó có nhận
 * truyện mới mỗi ngày hay không — và muốn xoá của người khác thì phải biết token của họ.
 */
class PushDeviceController extends Controller
{
    /** Định dạng token của Expo; chặn rác trước khi chạm DB. */
    private const TOKEN_PATTERN = '/^Expo(nent)?PushToken\[[A-Za-z0-9_\-]{10,}\]$/';

    public function store(Request $request): JsonResponse
    {
        $data = $request->validate([
            'token' => ['required', 'string', 'max:191', 'regex:'.self::TOKEN_PATTERN],
            'platform' => ['required', 'in:ios,android'],
            'timezone' => ['nullable', 'string', 'max:64'],
        ]);

        // Múi giờ lạ (hoặc máy không báo) thì gửi theo giờ New York — phần lớn người
        // đọc ở Mỹ — thay vì làm hỏng cả lượt đăng ký.
        $timezone = $data['timezone'] ?? '';
        if (! in_array($timezone, timezone_identifiers_list(), true)) {
            $timezone = 'America/New_York';
        }

        PushDevice::query()->updateOrCreate(
            ['token' => $data['token']],
            ['platform' => $data['platform'], 'timezone' => $timezone],
        );

        return response()->json(['ok' => true]);
    }

    public function destroy(Request $request): Response
    {
        $data = $request->validate(['token' => ['required', 'string', 'max:191']]);

        PushDevice::query()->where('token', $data['token'])->delete();

        return response()->noContent();
    }
}
