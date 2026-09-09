<?php

declare(strict_types=1);

namespace App\Http\Middleware;

use Closure;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\Response;

/**
 * Chốt chặn cho nhóm route /api/ingest — nơi một AI khác đăng truyện lên.
 *
 * Dùng token dùng chung (`INGEST_TOKEN` trong .env) thay vì tài khoản người dùng:
 * client ở đây là MÁY, không có phiên đăng nhập, và toàn bộ nhóm route này chỉ
 * phục vụ đúng một client do chính chủ dự án cầm token.
 *
 * KHÔNG cấu hình INGEST_TOKEN thì mọi route ingest trả 503 — mặc định đóng, để
 * không bao giờ có chuyện mở toang API ghi chỉ vì quên điền biến môi trường.
 */
class VerifyIngestToken
{
    public function handle(Request $request, Closure $next): Response
    {
        $expected = (string) config('services.ingest.token');

        if ($expected === '') {
            return response()->json([
                'message' => 'Ingest API chưa được bật. Hãy đặt INGEST_TOKEN trong .env.',
            ], 503);
        }

        // Chấp nhận cả "Authorization: Bearer <token>" lẫn header riêng X-Ingest-Token.
        $given = (string) ($request->bearerToken() ?? $request->header('X-Ingest-Token', ''));

        // So sánh theo thời gian hằng số: tránh rò rỉ token qua đo thời gian phản hồi.
        if ($given === '' || ! hash_equals($expected, $given)) {
            return response()->json(['message' => 'Token không hợp lệ.'], 401);
        }

        return $next($request);
    }
}
