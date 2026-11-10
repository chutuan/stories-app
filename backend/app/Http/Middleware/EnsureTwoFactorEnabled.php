<?php

declare(strict_types=1);

namespace App\Http\Middleware;

use Closure;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\Response;

/**
 * 2FA là BẮT BUỘC cho admin: tài khoản chưa bật (lần đầu sau khi deploy, hoặc sau
 * `admin:2fa-reset`) chỉ vào được trang Bảo mật để bật, mọi trang khác bị đưa về đó.
 *
 * Gắn alias `admin.2fa` cho nhóm route admin, NGOÀI trang Bảo mật và Đăng xuất.
 */
class EnsureTwoFactorEnabled
{
    public function handle(Request $request, Closure $next): Response
    {
        $user = $request->user();

        if ($user && ! $user->hasTwoFactorEnabled()) {
            if ($request->expectsJson()) {
                abort(403, 'Cần bật xác thực 2 bước.');
            }

            return redirect()->route('admin.security')
                ->with('warning', 'Bật xác thực 2 bước trước khi dùng trang quản trị.');
        }

        return $next($request);
    }
}
