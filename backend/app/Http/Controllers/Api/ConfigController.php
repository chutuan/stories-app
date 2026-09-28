<?php

declare(strict_types=1);

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Support\AppSettings;
use Illuminate\Http\JsonResponse;

/**
 * GET /api/config — cấu hình app di động đọc lúc mở và khi quay lại app.
 * App có sẵn giá trị mặc định và bản lưu lần trước, nên endpoint này lỗi thì app
 * vẫn chạy bình thường.
 */
class ConfigController extends Controller
{
    public function show(): JsonResponse
    {
        return response()->json(AppSettings::forApp());
    }
}
