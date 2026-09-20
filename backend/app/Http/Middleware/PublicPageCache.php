<?php

declare(strict_types=1);

namespace App\Http\Middleware;

use Closure;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\Response;

/**
 * Cho phép cache các trang đọc công khai ở biên và ở trình duyệt.
 *
 * VẤN ĐỀ: Symfony mặc định gắn `Cache-Control: no-cache, private` cho mọi response
 * không tự khai gì. Hệ quả là Cloudflare trả `cf-cache-status: DYNAMIC` cho từng
 * lượt tải trang, nên MỌI request đều phải chạy hết chặng từ máy biên về origin ở
 * NYC. Đo thực tế: origin dựng trang trong 74-105ms, nhưng TTFB nhìn từ châu Á là
 * 880-1130ms — gần một giây chỉ là quãng đường.
 *
 * KHÔNG áp cho trang chương. Khối đánh giá cuối chương hiển thị khác nhau tuỳ
 * người đọc đã bỏ phiếu hay chưa, nên nếu đem cache dùng chung thì người này sẽ
 * thấy lời cảm ơn dành cho người khác. Trang chương cũng là trang duy nhất có
 * form POST, tức có token CSRF không được phép dùng chung.
 *
 * Cũng gỡ cookie phiên trên những trang này: Cloudflare bỏ qua cache khi response
 * có Set-Cookie, nên để nguyên thì phần trên vô nghĩa. Các trang này không dùng
 * phiên cho việc gì cả — chỉ có ô tìm kiếm, và nó là form GET.
 *
 * PHẢI ĐƯỢC PREPEND VÀO NHÓM `web`, không gắn ở tầng route. Middleware gắn theo
 * route nằm ở vòng TRONG CÙNG, nên trên đường ra nó chạy TRƯỚC — rồi StartSession
 * xử lý response sau đó và gắn lại cookie phiên, còn Symfony đặt lại
 * `no-cache, private`. Đã thử và đúng là bị ghi đè sạch. Prepend vào nhóm web thì
 * nó bọc ngoài cùng và là kẻ nói câu cuối.
 */
class PublicPageCache
{
    /**
     * 10 phút ở tầng CDN, 1 phút ở trình duyệt.
     *
     * Lệch nhau có chủ ý: sửa một truyện thì muốn thấy thay đổi sớm, mà purge
     * CDN thì dễ (một nút), còn cache trong trình duyệt người đọc thì không gọi
     * về được. stale-while-revalidate cho biên trả bản cũ ngay rồi làm mới ngầm,
     * nên không ai phải chờ lượt đi origin.
     */
    private const BROWSER = 60;

    private const CDN = 600;

    /**
     * Những trang được phép cache, theo tên route.
     *
     * Danh sách trắng chứ không phải danh sách đen: thêm một trang công khai mới
     * mà quên khai ở đây thì hậu quả là nó chậm, còn nếu làm ngược lại thì hậu quả
     * là một trang có nội dung riêng từng người bị đem dùng chung.
     */
    private const CACHEABLE = [
        'public.home',
        'public.browse',
        'public.category',
        'public.story',
        'public.about',
        'public.contact',
        'public.editorial',
        'public.privacy',
        'public.terms',
        'public.sitemap',
    ];

    public function handle(Request $request, Closure $next): Response
    {
        $response = $next($request);

        // isMethodCacheable() = GET hoặc HEAD, chứ KHÔNG phải isMethod('GET').
        // Trình duyệt và trình thu thập đều gửi HEAD để kiểm header trước khi tải;
        // loại HEAD ra thì chính những request đó lại nhận no-cache và Cloudflare
        // học sai. (Cũng chính chỗ này làm tôi tưởng middleware không chạy: curl -I
        // gửi HEAD nên luôn rơi vào nhánh thoát sớm.)
        if (! $request->isMethodCacheable() || $response->getStatusCode() !== 200) {
            return $response;
        }

        if (! in_array($request->route()?->getName(), self::CACHEABLE, true)) {
            return $response;
        }

        $response->headers->set(
            'Cache-Control',
            sprintf('public, max-age=%d, s-maxage=%d, stale-while-revalidate=86400', self::BROWSER, self::CDN),
        );

        // Gỡ CẢ HAI cookie. Cloudflare không cache response có Set-Cookie, nên còn
        // sót một cái là cả phần trên thành vô nghĩa. Những trang này không có form
        // POST nào nên không cần token CSRF trong trình duyệt; trang chương thì
        // không nằm trong danh sách trắng nên vẫn giữ nguyên cả hai.
        foreach ([(string) config('session.cookie'), 'XSRF-TOKEN'] as $cookie) {
            $response->headers->removeCookie($cookie);
            $response->headers->removeCookie($cookie, '/');
        }

        return $response;
    }
}
