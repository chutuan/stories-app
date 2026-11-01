<?php

declare(strict_types=1);

namespace App\Http\Middleware;

use Closure;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\Response;

/**
 * /browse/ → 301 → /browse.
 *
 * Laravel bỏ dấu "/" cuối khi khớp route, nên /story/x/ và /story/x cùng trả 200: hai URL
 * cho một trang. Canonical đã trỏ đúng bản không có "/", nhưng Google vẫn phải tải cả hai
 * mới biết — và link ngoài trỏ vào bản có "/" thì chia đôi tín hiệu. Apache có sẵn luật
 * này trong public/.htaccess; nginx thì không, nên làm ở đây (đổi cấu hình nginx trên
 * server là việc phải hỏi Tuấn, đổi code thì đi theo deploy).
 *
 * Chỉ GET/HEAD (chuyển hướng POST làm mất dữ liệu gửi lên) và không đụng /api: app gọi
 * API bằng URL tự dựng, một bước chuyển hướng thừa chỉ làm chậm.
 */
class StripTrailingSlash
{
    public function handle(Request $request, Closure $next): Response
    {
        $path = $request->getPathInfo();

        if ($path !== '/'
            && str_ends_with($path, '/')
            && ($request->isMethod('GET') || $request->isMethod('HEAD'))
            && ! str_starts_with($path, '/api/')) {
            $query = $request->getQueryString();

            return redirect(url(rtrim($path, '/')).($query ? '?'.$query : ''), 301);
        }

        return $next($request);
    }
}
