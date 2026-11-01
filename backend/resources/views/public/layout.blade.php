{{--
  Layout chung cho toàn bộ trang công khai: trang đọc truyện, trang giới thiệu,
  privacy, terms.

  Ba thứ layout này chịu trách nhiệm và từng trang KHÔNG nên tự làm lại:
   - thẻ SEO (title/description/canonical/OpenGraph/Twitter) — xem @section bên dưới
   - favicon
   - thẻ AdSense

  Trang con điều khiển bằng:
   - @section('title')        tiêu đề, KHÔNG kèm "— Stories" (layout tự nối)
   - @section('description')  mô tả cho <meta description> và OpenGraph
   - @section('og_image')     ảnh chia sẻ mạng xã hội (mặc định: icon app)
   - @section('og_type')      'website' (mặc định) hoặc 'article' cho trang chương
   - @section('robots')       ví dụ 'noindex' cho trang kết quả tìm kiếm
   - @push('head')            JSON-LD hoặc thẻ riêng của trang
   - @section('wide')         đặt bất kỳ giá trị nào để dùng khung rộng (lưới truyện)
--}}
@php
    /*
     | Blade ĐÃ escape sẵn: dạng hai tham số `@section('description', $x)` gọi
     | e($x) bên trong startSection(). Nếu ở đây in bằng {{ }} thì escape lần
     | hai và dấu nháy trong truyện ra thành "Ethan&amp;#039;s" ngay giữa thẻ
     | <meta description> — Google hiển thị đúng cái chuỗi rác đó trên kết quả
     | tìm kiếm.
     |
     | Giải mã một lần rồi escape một lần: đúng cho cả dạng hai tham số lẫn dạng
     | khối @section...@endsection (không được escape sẵn), và chạy lại nhiều lần
     | vẫn ra cùng kết quả.
     */
    $seo = static fn (string $v): string => e(html_entity_decode($v, ENT_QUOTES | ENT_HTML5, 'UTF-8'));

    $seoTitle = trim($__env->yieldContent('title', 'Stories'));
    $seoDescription = trim($__env->yieldContent(
        'description',
        'Short serialised fiction you can finish in one sitting — hidden billionaires, secret identities and long-overdue revenge.'
    ));
    // Mặc định là ảnh 1200×630 dựng sẵn, KHÔNG phải icon vuông 512: icon vuông
    // đưa vào ô chia sẻ 1,91:1 sẽ bị viền hai bên hoặc bị hạ xuống ô nhỏ.
    $seoImage = trim($__env->yieldContent('og_image', asset('icons/og-default.jpg')));
    // Kích thước ảnh chia sẻ. Facebook và LinkedIn cần biết TRƯỚC khi tải ảnh về
    // để dựng ô xem trước; thiếu hai thẻ này thì lần chia sẻ đầu tiên hay ra ô
    // không có ảnh, tới lần thứ hai mới hiện.
    $seoImageW = trim($__env->yieldContent('og_image_w', '1200'));
    $seoImageH = trim($__env->yieldContent('og_image_h', '630'));
    $seoImageAlt = trim($__env->yieldContent('og_image_alt', 'Stories — free short serialised fiction'));
    $seoType = trim($__env->yieldContent('og_type', 'website'));
    $seoRobots = trim($__env->yieldContent('robots', 'index, follow'));
    $isWide = trim($__env->yieldContent('wide', '')) !== '';
@endphp
<!DOCTYPE html>
<html lang="en">
<head>
<meta charset="utf-8">
<meta name="viewport" content="width=device-width, initial-scale=1">

<title>{!! $seo($seoTitle) !!} — Stories</title>
<meta name="description" content="{!! $seo($seoDescription) !!}">
<meta name="robots" content="{{ $seoRobots }}">
{{-- Canonical bỏ chuỗi truy vấn: trang danh sách có ?page= vẫn phải trỏ về
     chính nó, nhưng ?utm_* hay tham số rác thì không được sinh ra bản sao. --}}
<link rel="canonical" href="{{ url()->current() }}{{ request()->query('page') > 1 ? '?page='.(int) request()->query('page') : '' }}">

<meta property="og:site_name" content="Stories">
<meta property="og:type" content="{{ $seoType }}">
<meta property="og:title" content="{!! $seo($seoTitle) !!}">
<meta property="og:description" content="{!! $seo($seoDescription) !!}">
<meta property="og:url" content="{{ url()->current() }}">
<meta property="og:image" content="{!! $seo($seoImage) !!}">
<meta property="og:image:width" content="{{ $seoImageW }}">
<meta property="og:image:height" content="{{ $seoImageH }}">
<meta property="og:image:alt" content="{!! $seo($seoImageAlt) !!}">
<meta property="og:locale" content="en_US">
{{-- summary_large_image chỉ hợp khi ảnh là khổ NGANG. Trang nào còn dùng icon
     vuông thì khai 'summary' để Twitter dựng ô nhỏ đúng cách thay vì kéo giãn. --}}
<meta name="twitter:card" content="{{ $seoImageW >= 600 ? 'summary_large_image' : 'summary' }}">
<meta name="twitter:title" content="{!! $seo($seoTitle) !!}">
<meta name="twitter:description" content="{!! $seo($seoDescription) !!}">
<meta name="twitter:image" content="{!! $seo($seoImage) !!}">
<meta name="twitter:image:alt" content="{!! $seo($seoImageAlt) !!}">
@stack('meta')

<link rel="icon" href="/favicon.ico" sizes="any">
<link rel="icon" type="image/png" sizes="32x32" href="{{ asset('icons/icon-32.png') }}">
<link rel="icon" type="image/png" sizes="16x16" href="{{ asset('icons/icon-16.png') }}">
<link rel="apple-touch-icon" href="{{ asset('icons/icon-180.png') }}">
{{-- Thanh trình duyệt trên điện thoại cùng màu với đầu trang thay vì một dải cam
     đặc: trang trông liền một khối, và khớp cả khi máy đang ở chế độ tối. --}}
<meta name="theme-color" content="#FFFFFF" media="(prefers-color-scheme: light)">
<meta name="theme-color" content="#1D191B" media="(prefers-color-scheme: dark)">

{{-- Google AdSense. Cùng publisher ID với AdMob của app (pub-9892355907152840),
     nhưng là HAI sản phẩm riêng: AdSense phục vụ website, AdMob phục vụ app, và
     mỗi bên đòi một file xác thực riêng (/ads.txt cho AdSense, /app-ads.txt cho
     AdMob — xem routes/web.php).

     Chỉ nhúng khi ADMOB_PUBLISHER_ID có giá trị, cùng lý do với route /ads.txt:
     máy dev không nên gọi ra Google, và thẻ script mang client rỗng thì AdSense
     báo lỗi thay vì im lặng bỏ qua.

     KHÔNG nhúng trên /privacy, /terms, /contact: app iOS mở ba trang này trong
     trình duyệt trong app (About links, build 7). Apple coi web view phục vụ chức
     năng của app như chính app, nên quảng cáo cá nhân hoá hay GA4 ở đây sẽ trái
     với lời khai "không tracking, quảng cáo không cá nhân hoá" và câu "analytics
     never present in the app" trong Privacy Policy. Áp dụng cho cả khối GA4 dưới. --}}
{{-- Dùng dạng khối (php … endphp), KHÔNG dùng dạng một dòng php(...): Blade hiểu nhầm
     dạng một dòng là mở khối khi phía sau file còn một khối php khác, và in nguyên văn
     cả đoạn giữa hai cái ra trang. (Không viết dấu @ trước "php" trong comment này:
     Blade tách khối php TRƯỚC khi bỏ comment, nên chữ trong comment cũng bị bắt.) --}}
@php $legalPage = request()->routeIs('public.privacy', 'public.terms', 'public.contact'); @endphp
{{-- Smart App Banner: Safari trên iPhone tự hiện thanh "Get/Open" của app ở đầu trang.
     Không gắn trên ba trang pháp lý: app mở chúng trong trình duyệt trong app. --}}
@unless ($legalPage)
<meta name="apple-itunes-app" content="app-id={{ config('app.ios_app_id') }}">
@endunless
@if (filled(config('app.admob_publisher_id')) && ! $legalPage)
<script async src="https://pagead2.googlesyndication.com/pagead/js/adsbygoogle.js?client=ca-{{ config('app.admob_publisher_id') }}"
     crossorigin="anonymous"></script>
@endif

{{-- Google Analytics 4 — chỉ cho WEBSITE. App không có SDK phân tích nào, và thư
     gửi Apple khai đúng như vậy; đừng đem thẻ này sang app. Cùng quy tắc với
     AdSense: thiếu ID thì không in gì, để máy dev không bơm dữ liệu giả vào báo
     cáo và mỗi lần chạy test không bị tính thành một phiên truy cập. --}}
@if (filled(config('app.ga_measurement_id')) && ! $legalPage)
<script async src="https://www.googletagmanager.com/gtag/js?id={{ config('app.ga_measurement_id') }}"></script>
<script>
  window.dataLayer = window.dataLayer || [];
  function gtag(){dataLayer.push(arguments);}
  gtag('js', new Date());
  gtag('config', '{{ config('app.ga_measurement_id') }}');
</script>
@endif
{{-- Giao diện sáng / tối / sepia. Đặt data-theme TRƯỚC khi trang vẽ, không thì người
     chọn nền tối sẽ thấy trang chớp trắng mỗi lần chuyển trang. Trang công khai được
     Cloudflare cache chung cho mọi người, nên lựa chọn này chỉ được sống ở trình duyệt
     (localStorage), không bao giờ ở server. Chưa chọn gì thì theo cài đặt hệ điều hành.
     Cỡ chữ và phông của màn đọc cũng nạp ở đây, cùng lý do: tránh chữ nhảy cỡ sau khi hiện. --}}
<script>
(function () {
  var d = document.documentElement;
  try {
    var t = localStorage.getItem('stories.theme');
    if (t === 'light' || t === 'dark' || t === 'sepia') d.setAttribute('data-theme', t);
    var r = JSON.parse(localStorage.getItem('stories.reader') || '{}');
    if (r.size >= 15 && r.size <= 28) d.style.setProperty('--rs', r.size + 'px');
    if (r.font === 'sans') d.setAttribute('data-reader-font', 'sans');
  } catch (e) {}
})();

// Lịch sử đọc cho mục "Continue reading" (trang chủ) và nút "Continue" (trang truyện).
// Chỉ ở localStorage của người đọc: trang được cache chung nên server không biết ai đọc gì.
window.StoriesHistory = {
  KEY: 'stories.history',
  all: function () {
    try { var a = JSON.parse(localStorage.getItem(this.KEY) || '[]'); return Array.isArray(a) ? a : []; }
    catch (e) { return []; }
  },
  get: function (slug) {
    return this.all().filter(function (h) { return h.slug === slug; })[0] || null;
  },
  save: function (entry) {
    var list = this.all().filter(function (h) { return h.slug !== entry.slug; });
    list.unshift(entry);
    try { localStorage.setItem(this.KEY, JSON.stringify(list.slice(0, 12))); } catch (e) {}
  },
};
</script>
<style>
  /* ---------- Màu ----------
     Ba bộ màu dùng chung một bộ tên biến; mọi chỗ trong trang (và trong các partial
     như app-prompt) chỉ được dùng biến, không viết mã màu thẳng — có một mã màu cứng
     là chế độ tối lộ ngay một mảng trắng. Riêng hai dải đầu trang có ảnh bìa mờ
     (.spotlight, .story-hero) cố ý luôn tối ở mọi chế độ, như poster phim. */
  :root{
    --bg:#FBF7F3;--surface:#fff;--surface-2:#F4ECE5;
    --text:#1A1614;--text-2:#3B332F;--muted:#776B63;
    --accent:#FF9052;--accent-deep:#F2703A;--btn:#EC6228;
    /* Chữ màu cam trên nền kem: #F2703A chỉ đạt tương phản ~2,9:1, đọc không rõ link.
       --accent-ink đậm hơn cho mọi CHỮ màu cam; nền nút vẫn giữ cam tươi của thương hiệu. */
    --accent-ink:#C24E1A;--accent-soft:#FFEFE4;
    --border:rgba(26,22,20,.09);--border-strong:rgba(26,22,20,.17);
    --shadow:0 1px 2px rgba(26,22,20,.05),0 6px 18px rgba(26,22,20,.06);
    --shadow-lift:0 16px 34px rgba(26,22,20,.17);
    --head-bg:rgba(255,255,255,.88);
    color-scheme:light;
  }
  @media (prefers-color-scheme:dark){
    :root:not([data-theme]){
      --bg:#131012;--surface:#1D191B;--surface-2:#282326;
      --text:#F2EBE6;--text-2:#D8CEC7;--muted:#A0948C;
      --accent-ink:#FF9C66;--accent-soft:rgba(255,144,82,.14);
      --border:rgba(255,255,255,.08);--border-strong:rgba(255,255,255,.17);
      --shadow:0 1px 2px rgba(0,0,0,.35),0 8px 22px rgba(0,0,0,.28);
      --shadow-lift:0 16px 34px rgba(0,0,0,.5);
      --head-bg:rgba(29,25,27,.88);
      color-scheme:dark;
    }
  }
  :root[data-theme="dark"]{
    --bg:#131012;--surface:#1D191B;--surface-2:#282326;
    --text:#F2EBE6;--text-2:#D8CEC7;--muted:#A0948C;
    --accent-ink:#FF9C66;--accent-soft:rgba(255,144,82,.14);
    --border:rgba(255,255,255,.08);--border-strong:rgba(255,255,255,.17);
    --shadow:0 1px 2px rgba(0,0,0,.35),0 8px 22px rgba(0,0,0,.28);
    --shadow-lift:0 16px 34px rgba(0,0,0,.5);
    --head-bg:rgba(29,25,27,.88);
    color-scheme:dark;
  }
  /* Sepia: nền giấy ngả vàng cho người đọc lâu buổi tối mà không muốn nền đen. */
  :root[data-theme="sepia"]{
    --bg:#F3EAD7;--surface:#FAF4E6;--surface-2:#EADFC8;
    --text:#3A2E21;--text-2:#4A3B2B;--muted:#7C6851;
    --accent-ink:#A9471A;--accent-soft:#F5DEC6;
    --border:rgba(58,46,33,.13);--border-strong:rgba(58,46,33,.22);
    --head-bg:rgba(250,244,230,.9);
  }

  *{box-sizing:border-box}
  html{-webkit-text-size-adjust:100%}
  body{margin:0;background:var(--bg);color:var(--text);
       font:16px/1.65 -apple-system,BlinkMacSystemFont,"Segoe UI",Roboto,Helvetica,Arial,sans-serif;
       -webkit-font-smoothing:antialiased}
  a{color:var(--accent-ink)}
  .wrap{max-width:760px;margin:0 auto;padding:0 20px}
  .wrap.wide{max-width:1200px}
  main{padding:32px 0 72px}
  h1{font-size:32px;line-height:1.2;margin:0 0 8px;letter-spacing:-.022em}
  h2{font-size:20px;line-height:1.3;margin:36px 0 12px;letter-spacing:-.012em}
  .updated{color:var(--muted);font-size:14.5px;margin:0 0 24px}
  p,li{color:var(--text-2)}
  ul{padding-left:20px}
  li{margin:6px 0}
  .meta{color:var(--muted)}
  .card{background:var(--surface);border:1px solid var(--border);border-radius:18px;padding:22px 24px;
        margin:22px 0;box-shadow:var(--shadow)}
  .empty{text-align:center;color:var(--muted);padding:44px 0}
  .crumbs{font-size:13px;color:var(--muted);margin:0 0 18px}
  .crumbs a{color:var(--muted)}
  .narrow-only{display:none}
  .visually-hidden{position:absolute!important;width:1px;height:1px;overflow:hidden;clip:rect(0 0 0 0);white-space:nowrap}

  /* ---------- Đầu trang ---------- */
  .site-head{position:sticky;top:0;z-index:50;background:var(--head-bg);border-bottom:1px solid var(--border);
             -webkit-backdrop-filter:saturate(1.5) blur(14px);backdrop-filter:saturate(1.5) blur(14px)}
  .head-row{display:flex;align-items:center;gap:14px;height:64px}
  .logo{display:flex;align-items:center;gap:9px;font-size:21px;font-weight:800;letter-spacing:-.025em;
        color:var(--text);text-decoration:none;white-space:nowrap;flex:none}
  .logo img{width:30px;height:30px;border-radius:8px;display:block}
  .logo span{color:var(--accent-deep)}
  .main-nav{display:flex;align-items:center;gap:2px;margin-left:10px}
  .main-nav > a,.main-nav summary{display:block;padding:8px 13px;border-radius:999px;color:var(--text-2);
        font-size:14.5px;font-weight:560;text-decoration:none;cursor:pointer;list-style:none;white-space:nowrap}
  .main-nav > a:hover,.main-nav summary:hover,.main-nav details[open] summary{background:var(--surface-2);color:var(--text)}
  .main-nav > a[aria-current="page"]{color:var(--accent-ink);background:var(--accent-soft)}
  summary::-webkit-details-marker{display:none}
  .main-nav summary::after{content:"";display:inline-block;width:6px;height:6px;margin:0 0 3px 7px;
        border-right:1.6px solid currentColor;border-bottom:1.6px solid currentColor;transform:rotate(45deg)}
  .genres{position:relative}
  .genres-pop{position:absolute;top:calc(100% + 10px);left:0;width:560px;max-width:80vw;padding:12px;
        background:var(--surface);border:1px solid var(--border);border-radius:18px;box-shadow:var(--shadow-lift);
        display:grid;grid-template-columns:repeat(3,minmax(0,1fr));gap:2px}
  .genres-pop a{display:flex;justify-content:space-between;gap:8px;padding:8px 11px;border-radius:10px;
        font-size:14px;color:var(--text-2);text-decoration:none}
  .genres-pop a:hover{background:var(--surface-2);color:var(--accent-ink)}
  .genres-pop small{color:var(--muted);font-variant-numeric:tabular-nums}
  .searchbox{position:relative;margin-left:auto;flex:0 1 300px;min-width:0}
  .searchbox input{width:100%;font:inherit;font-size:14.5px;padding:9px 14px 9px 38px;border-radius:999px;
        border:1px solid var(--border);background:var(--surface-2);color:var(--text);-webkit-appearance:none}
  .searchbox input::placeholder{color:var(--muted)}
  .searchbox input:focus{outline:none;border-color:var(--accent);background:var(--surface);box-shadow:0 0 0 3px var(--accent-soft)}
  .searchbox button{position:absolute;left:4px;top:50%;transform:translateY(-50%);width:32px;height:32px;
        border:0;background:none;color:var(--muted);display:grid;place-items:center;cursor:pointer;padding:0}
  .searchbox svg{width:17px;height:17px}
  .icon-btn{width:40px;height:40px;border-radius:999px;border:1px solid var(--border);background:var(--surface);
        color:var(--text-2);display:grid;place-items:center;cursor:pointer;flex:none;padding:0;list-style:none}
  .icon-btn:hover{color:var(--accent-ink);border-color:var(--border-strong)}
  .icon-btn svg{width:19px;height:19px}
  /* Biểu tượng của nút đổi nền: đang sáng thì hiện mặt trăng (bấm để sang tối) và ngược lại. */
  .theme-toggle .i-sun{display:none}
  :root[data-theme="dark"] .theme-toggle .i-sun{display:block}
  :root[data-theme="dark"] .theme-toggle .i-moon{display:none}
  @media (prefers-color-scheme:dark){
    :root:not([data-theme]) .theme-toggle .i-sun{display:block}
    :root:not([data-theme]) .theme-toggle .i-moon{display:none}
  }
  .menu{display:none}
  .menu-pop{position:fixed;left:0;right:0;top:57px;max-height:calc(100vh - 57px);overflow:auto;
        background:var(--surface);border-bottom:1px solid var(--border);box-shadow:var(--shadow-lift);padding:10px 16px 20px}
  .menu-pop > a{display:block;padding:12px 4px;border-bottom:1px solid var(--border);color:var(--text);
        text-decoration:none;font-weight:600}
  .menu-pop h3{font-size:12px;letter-spacing:.09em;text-transform:uppercase;color:var(--muted);margin:18px 4px 10px}

  /* ---------- Chân trang ---------- */
  .site-foot{background:var(--surface);border-top:1px solid var(--border);padding:44px 0 28px;font-size:14px;color:var(--muted)}
  .foot-grid{display:grid;grid-template-columns:minmax(0,1.7fr) repeat(3,minmax(0,1fr));gap:28px}
  .foot-grid h4{margin:2px 0 12px;font-size:12px;text-transform:uppercase;letter-spacing:.09em;color:var(--text)}
  .foot-grid a{display:block;color:var(--muted);text-decoration:none;padding:3px 0;overflow-wrap:anywhere}
  .foot-grid a:hover{color:var(--accent-ink)}
  .foot-grid a.logo{display:flex;margin-bottom:10px;color:var(--text)}
  .foot-brand p{color:var(--muted);margin:0 0 18px;max-width:38ch}
  .foot-bottom{border-top:1px solid var(--border);margin-top:30px;padding-top:6px}
  /* Khối tải app ở chân trang. Mã QR chỉ có ích trên máy tính (quét bằng điện thoại);
     trên điện thoại/máy cảm ứng chỉ hiện link. Nền trắng + đệm = vùng lề yên tĩnh của
     mã QR, để vẫn quét được khi nền trang tối. */
  .app-promo{display:flex;align-items:center;gap:14px;margin:0}
  .app-promo .qr{flex:none;line-height:0;margin:0;padding:0}
  .app-promo .qr img{width:88px;height:88px;padding:6px;background:#fff;border:1px solid var(--border);border-radius:10px}
  .app-promo strong{display:block;color:var(--text);font-size:15px;margin-bottom:2px}
  .app-promo div > a{display:inline;padding:0;color:var(--accent-ink)}
  @media (max-width:600px), (hover:none) and (pointer:coarse){
    .app-promo .qr, .app-promo .qr-hint{display:none}
    /* Mất câu dẫn "Scan…, or" thì link đứng đầu câu: viết hoa chữ đầu. */
    .app-promo div > a{display:inline-block}
    .app-promo div > a::first-letter{text-transform:uppercase}
  }
  .disclosure{margin:14px 0 0;font-size:13px;line-height:1.6;color:var(--muted);max-width:80ch}
  .disclosure a{color:var(--muted);text-decoration:underline;margin:0;display:inline;padding:0}
  /* Trên trang truyện và trang đọc, dòng khai đứng riêng trong khung nhạt */
  .disclosure.inline{margin:30px 0 0;padding:13px 16px;background:var(--surface);
    border:1px solid var(--border);border-radius:12px;font-size:12.5px}

  /* ---------- Nút & nhãn ---------- */
  .btn{display:inline-flex;align-items:center;justify-content:center;gap:8px;padding:12px 22px;border-radius:999px;
       background:var(--btn);color:#fff;text-decoration:none;font-weight:700;font-size:15px;line-height:1.2;border:0;
       cursor:pointer;box-shadow:0 6px 16px rgba(236,98,40,.28);transition:transform .15s,box-shadow .15s}
  .btn:hover{transform:translateY(-1px);box-shadow:0 10px 22px rgba(236,98,40,.36)}
  .btn.soft{background:var(--accent-soft);color:var(--accent-ink);box-shadow:none}
  /* Nút phụ trên dải ảnh bìa tối */
  .btn.glass{background:rgba(255,255,255,.12);color:#fff;box-shadow:none;border:1px solid rgba(255,255,255,.24)}
  .btn.glass:hover{background:rgba(255,255,255,.2)}
  .actions{display:flex;gap:10px;flex-wrap:wrap}
  .chips{display:flex;flex-wrap:wrap;gap:8px;margin:0;padding:0;list-style:none}
  .chips li{margin:0}
  .chip{display:inline-flex;align-items:center;gap:7px;padding:7px 14px;border-radius:999px;background:var(--surface);
        border:1px solid var(--border);color:var(--text-2);font-size:14px;font-weight:560;text-decoration:none;
        white-space:nowrap;transition:border-color .15s,color .15s,background .15s}
  .chip small{color:var(--muted);font-weight:500;font-size:12px;font-variant-numeric:tabular-nums}
  .chip:hover{border-color:var(--accent);color:var(--accent-ink)}
  .chip.on{background:var(--text);border-color:var(--text);color:var(--bg)}
  .chip.on small{color:inherit;opacity:.7}
  .chip.dark{background:rgba(255,255,255,.1);border-color:rgba(255,255,255,.2);color:#fff;font-size:13px;padding:5px 12px}
  .chip.dark:hover{background:rgba(255,255,255,.18)}

  /* ---------- Tiêu đề mục ---------- */
  .sec{margin:48px 0 0}
  .sec-head{display:flex;align-items:center;justify-content:space-between;gap:12px;margin:0 0 16px}
  .sec-head h2{margin:0;font-size:22px;display:flex;align-items:center;gap:10px}
  .sec-head h2::before{content:"";width:4px;height:20px;border-radius:4px;flex:none;
        background:linear-gradient(var(--accent),var(--accent-deep))}
  .sec-head a{font-size:14px;font-weight:650;text-decoration:none;white-space:nowrap}
  .sec-head a:hover{text-decoration:underline}

  /* ---------- Thẻ truyện ---------- */
  /* Số cột đặt TƯỜNG MINH theo bề ngang, không để auto-fill tự chia: bìa là thứ bán
     truyện, xuống dưới ~170px thì không còn đọc được mặt nhân vật. Số cột ở mọi khổ
     (6/4/3/2) đều chia hết 12 truyện mỗi mục trang chủ và 24 truyện mỗi trang Browse. */
  .grid{display:grid;gap:30px 22px;grid-template-columns:repeat(4,minmax(0,1fr));margin:0;padding:0;list-style:none}
  .grid li{margin:0}
  .scard{display:block;text-decoration:none;color:inherit}
  .scover{position:relative;border-radius:12px;overflow:hidden;background:var(--surface-2);
          box-shadow:var(--shadow);transition:transform .2s ease,box-shadow .2s ease}
  /* <picture> mặc định là inline nên chen một khoảng trắng dưới ảnh; cho nó block. */
  .scard picture,.spot-cover picture,.sh-cover picture{display:block}
  /* height:auto là BẮT BUỘC: thẻ <img> mang width=300 height=450 (để trình duyệt giữ chỗ
     trước khi ảnh tải). Chỉ ghi width:100% thì height=450 vẫn thắng aspect-ratio, ảnh bị
     kéo cao thành ~9:16 và mỗi thẻ cao gần 600px — đúng lỗi trang chủ ngày 25/10/2026. */
  .scard img,.scard .noart{display:block;width:100%;height:auto;aspect-ratio:2/3;object-fit:cover}
  .noart{display:flex;align-items:center;justify-content:center;padding:12px;text-align:center;
         font-size:13px;color:var(--muted);background:var(--surface-2)}
  .scard:hover .scover{transform:translateY(-4px);box-shadow:var(--shadow-lift)}
  .badge{position:absolute;left:8px;top:8px;font-size:11px;font-weight:700;letter-spacing:.02em;line-height:1;
         padding:5px 8px;border-radius:999px;background:var(--btn);color:#fff}
  /* Tên truyện dài tới ~200 ký tự (kiểu câu mở đầu). Cắt ở 3 dòng và GIỮ CHỖ đủ 3 dòng
     để dòng thể loại thẳng hàng giữa các thẻ; tên đầy đủ ở trang truyện. */
  .scard h3{font-size:15px;line-height:1.38;margin:11px 0 5px;font-weight:650;color:var(--text);
            display:-webkit-box;-webkit-line-clamp:3;-webkit-box-orient:vertical;overflow:hidden;
            min-height:calc(1.38em * 3);transition:color .15s}
  .scard:hover h3{color:var(--accent-ink)}
  .scard .meta{font-size:12.5px;margin:0;display:flex;align-items:center;gap:5px;flex-wrap:wrap}
  .scard .genre{color:var(--accent-ink);font-weight:650}

  /* Hàng cuộn ngang theo thể loại. Cuộn bằng tay/trackpad là chính; hai nút mũi tên
     chỉ hiện trên máy có chuột, để người dùng chuột không phải kéo thanh cuộn. */
  .shelf-wrap{position:relative}
  .shelf{display:grid;grid-auto-flow:column;grid-auto-columns:calc((100% - 5 * 20px) / 6);gap:20px;
         overflow-x:auto;overscroll-behavior-x:contain;scroll-snap-type:x mandatory;scrollbar-width:none;
         margin:0;padding:4px 0 10px;list-style:none}
  .shelf::-webkit-scrollbar{display:none}
  .shelf li{scroll-snap-align:start;margin:0}
  .shelf .scard h3{font-size:14px}
  .shelf-btn{position:absolute;top:28%;width:42px;height:42px;border-radius:999px;border:1px solid var(--border);
         background:var(--surface);color:var(--text);box-shadow:var(--shadow-lift);cursor:pointer;display:none;
         place-items:center;z-index:2;padding:0}
  .shelf-btn svg{width:18px;height:18px}
  .shelf-btn.prev{left:-16px}
  .shelf-btn.next{right:-16px}
  .shelf-btn[disabled]{opacity:0;pointer-events:none}
  @media (hover:hover) and (pointer:fine){ .shelf-btn{display:grid} }

  /* ---------- Danh sách xếp hạng / gợi ý (bìa nhỏ + tên) ---------- */
  .rank{list-style:none;margin:0;padding:0;counter-reset:rank}
  .rank li{margin:0}
  .rank a{display:grid;grid-template-columns:30px 48px minmax(0,1fr);gap:12px;align-items:center;
          padding:8px;border-radius:12px;text-decoration:none;color:inherit}
  .rank a:hover{background:var(--surface-2)}
  .rank-n{font-size:22px;font-weight:800;font-style:italic;color:var(--muted);text-align:center;
          font-variant-numeric:tabular-nums;line-height:1}
  .rank li:nth-child(-n+3) .rank-n{color:var(--accent-deep)}
  .rank img,.rank .noart{width:48px;height:72px;object-fit:cover;border-radius:8px;display:block;
          background:var(--surface-2);font-size:0;padding:0}
  .rank strong{display:-webkit-box;-webkit-line-clamp:2;-webkit-box-orient:vertical;overflow:hidden;
          font-size:14.5px;line-height:1.38;font-weight:650;color:var(--text)}
  .rank a:hover strong{color:var(--accent-ink)}
  .rank .meta{display:block;font-size:12.5px;margin-top:3px}
  .rank.plain a{grid-template-columns:56px minmax(0,1fr)}
  .rank.plain img,.rank.plain .noart{width:56px;height:84px}

  /* ---------- Dải ảnh bìa mờ (trang chủ + trang truyện) ----------
     Ảnh nền là bản WebP 320w của chính bìa truyện, phóng to và làm mờ — vài chục KB,
     không phải tải thêm ảnh nào khác. Lớp phủ tối đủ dày để chữ trắng luôn đọc được
     dù bìa sáng hay tối. */
  .spot-bg{position:absolute;inset:-60px;z-index:-2;background-size:cover;background-position:center;
           filter:blur(40px) saturate(1.35);transform:scale(1.15)}
  .spotlight,.story-hero{position:relative;overflow:hidden;isolation:isolate;background:#1C1517;color:#fff}
  .spotlight::after,.story-hero::after{content:"";position:absolute;inset:0;z-index:-1}
  .spotlight::after{background:linear-gradient(100deg,rgba(17,11,13,.93) 0%,rgba(17,11,13,.76) 55%,rgba(17,11,13,.5) 100%)}
  .story-hero::after{background:linear-gradient(180deg,rgba(17,11,13,.74) 0%,rgba(17,11,13,.9) 100%)}
  .spot-cover img,.sh-cover img,.spot-cover .noart,.sh-cover .noart{display:block;width:100%;height:auto;aspect-ratio:2/3;
           object-fit:cover;border-radius:14px;box-shadow:0 18px 44px rgba(0,0,0,.5);background:#2A2124;color:#bbb}
  .eyebrow{display:inline-block;font-size:12px;font-weight:800;letter-spacing:.11em;text-transform:uppercase;
           color:var(--accent);margin:0}

  /* Trang chủ: truyện nổi bật bên trái, bảng xếp hạng bên phải */
  .home-top{display:grid;grid-template-columns:minmax(0,1.75fr) minmax(300px,1fr);gap:22px;align-items:stretch}
  .spotlight{border-radius:24px;display:flex}
  .spot-inner{display:flex;gap:30px;align-items:center;padding:34px}
  .spot-cover{flex:none;width:210px}
  .spotlight h2{font-size:30px;line-height:1.18;margin:10px 0 8px;letter-spacing:-.022em}
  .spotlight h2 a{color:#fff;text-decoration:none}
  .spotlight h2 a:hover{text-decoration:underline;text-decoration-thickness:2px;text-underline-offset:4px}
  .spot-meta{color:rgba(255,255,255,.72);font-size:14px;margin:0 0 12px}
  .spot-desc{color:rgba(255,255,255,.88);font-size:15.5px;line-height:1.62;margin:0 0 22px;
             display:-webkit-box;-webkit-line-clamp:4;-webkit-box-orient:vertical;overflow:hidden}
  .spotlight .chips{margin:0 0 14px}
  .panel{background:var(--surface);border:1px solid var(--border);border-radius:24px;padding:20px 14px 12px;box-shadow:var(--shadow)}
  .panel .sec-head{margin:0 8px 8px}
  .panel .sec-head h2{font-size:19px}
  /* Không có truyện nổi bật: bảng xếp hạng một mình cả hàng, chia hai cột */
  .home-top.solo{grid-template-columns:minmax(0,1fr)}
  .home-top.solo .rank{display:grid;grid-template-columns:repeat(2,minmax(0,1fr));column-gap:14px}

  /* "Continue reading" — dựng bằng JS từ localStorage, ẩn khi chưa đọc gì */
  .cont{display:grid;grid-template-columns:repeat(3,minmax(0,1fr));gap:14px;margin:0;padding:0;list-style:none}
  .cont li{margin:0}
  .cont a{display:grid;grid-template-columns:54px minmax(0,1fr);gap:14px;align-items:center;padding:10px;
          border-radius:16px;background:var(--surface);border:1px solid var(--border);text-decoration:none;color:inherit;
          box-shadow:var(--shadow);transition:border-color .15s}
  .cont a:hover{border-color:var(--accent)}
  .cont img{width:54px;height:81px;object-fit:cover;border-radius:9px;display:block;background:var(--surface-2)}
  .cont strong{display:-webkit-box;-webkit-line-clamp:2;-webkit-box-orient:vertical;overflow:hidden;font-size:14px;
          line-height:1.38;color:var(--text)}
  .cont .meta{display:block;font-size:12.5px;margin:4px 0 7px}
  .cont .bar{display:block;height:4px;border-radius:4px;background:var(--surface-2);overflow:hidden}
  .cont .bar i{display:block;height:100%;background:linear-gradient(90deg,var(--accent),var(--accent-deep))}

  .app-band{display:flex;align-items:center;gap:24px;justify-content:space-between;flex-wrap:wrap;
            margin:56px 0 0;padding:28px 30px;border-radius:24px;background:var(--surface);border:1px solid var(--border);box-shadow:var(--shadow)}
  .app-band h2{margin:0 0 6px}
  .app-band p{margin:0;max-width:62ch}

  /* ---------- Trang truyện ---------- */
  .story-hero{padding:24px 0 38px;margin-top:-32px}
  .story-hero .crumbs,.story-hero .crumbs a{color:rgba(255,255,255,.66)}
  .sh-main{display:grid;grid-template-columns:220px minmax(0,1fr);grid-template-areas:"cover head" "cover more";
           grid-template-rows:1fr auto;column-gap:36px;margin-top:8px}
  .sh-cover{grid-area:cover}
  .sh-head{grid-area:head;align-self:end;padding-top:10px}
  .sh-more{grid-area:more}
  .sh-head h1{color:#fff;font-size:34px;line-height:1.16;margin:0 0 8px}
  .sh-by{color:rgba(255,255,255,.74);font-size:15px;margin:0}
  .status{display:inline-block;font-size:12px;font-weight:700;padding:3px 9px;border-radius:999px;vertical-align:1px;
          background:rgba(255,255,255,.14);color:#fff}
  .status.live{background:var(--btn)}
  .stats{display:flex;flex-wrap:wrap;list-style:none;margin:18px 0 16px;padding:0}
  .stats li{margin:0;padding:0 22px;border-left:1px solid rgba(255,255,255,.18)}
  .stats li:first-child{padding-left:0;border-left:0}
  .stats strong{display:block;font-size:22px;line-height:1.2;color:#fff;font-variant-numeric:tabular-nums}
  .stats li > span{font-size:11.5px;letter-spacing:.07em;text-transform:uppercase;color:rgba(255,255,255,.6)}
  .story-hero .chips{margin:0 0 20px}
  .story-hero .rating{margin:0;color:var(--accent)}
  .stats .rating.compact{font-size:inherit;gap:6px;color:#fff}
  .stats .rating.compact svg{width:20px;height:20px;color:var(--accent)}
  .story-hero .rating-count{color:rgba(255,255,255,.6)}
  .story-cols{display:grid;grid-template-columns:minmax(0,1fr) 330px;gap:44px;margin-top:6px}
  .story-cols > *{min-width:0}
  .synopsis{font-size:16.5px;line-height:1.75;color:var(--text-2);margin:0}
  .story-side .panel{position:sticky;top:88px}
  .chapters{list-style:none;padding:0;margin:0;border:1px solid var(--border);border-radius:18px;overflow:hidden;
            background:var(--surface);box-shadow:var(--shadow)}
  .chapters li{margin:0;border-top:1px solid var(--border)}
  .chapters li:first-child{border-top:0}
  .chapters a{display:flex;gap:14px;padding:13px 18px;text-decoration:none;color:var(--text);align-items:center}
  .chapters a:hover{background:var(--accent-soft)}
  .chapters .n{width:32px;height:32px;border-radius:999px;background:var(--surface-2);color:var(--muted);flex:none;
               display:grid;place-items:center;font-size:13px;font-weight:700;font-variant-numeric:tabular-nums}
  .chapters .t{flex:1;min-width:0}
  .chapters .here{font-size:11.5px;font-weight:700;color:var(--accent-ink);background:var(--accent-soft);
               padding:3px 9px;border-radius:999px;white-space:nowrap}
  .chapters a.done .n{background:var(--accent-soft);color:var(--accent-ink)}

  /* ---------- Màn đọc ----------
     Ba con số quyết định đọc dài có mỏi mắt hay không: số ký tự mỗi dòng (chuẩn văn
     xuôi 60-75), font serif, và khoảng cách đoạn đủ rộng so với giãn dòng. 660px ở cỡ
     20px Charter cho khoảng 67 ký tự. Đặt bằng px, KHÔNG bằng em — em tính theo font
     16px của .reader chứ không phải cỡ chữ của phần thân chương. */
  .reader{max-width:660px;margin:0 auto}
  .reader-kicker{font-size:12px;letter-spacing:.09em;text-transform:uppercase;font-weight:750;margin:0 0 8px}
  .reader-kicker a{color:var(--accent-ink);text-decoration:none}
  .reader-kicker a:hover{text-decoration:underline}
  .reader h1{font-size:30px;line-height:1.2;margin:0 0 8px;letter-spacing:-.018em}
  .reader-by{color:var(--muted);font-size:14px;margin:0}
  .reader-tools{display:flex;gap:8px;align-items:center;margin:20px 0 30px;padding:0 0 22px;border-bottom:1px solid var(--border)}
  .jump{position:relative;flex:1;min-width:0;max-width:380px}
  .jump select{width:100%;font:inherit;font-size:14px;padding:9px 34px 9px 14px;border-radius:12px;border:1px solid var(--border);
         background:var(--surface);color:var(--text);-webkit-appearance:none;appearance:none;cursor:pointer;text-overflow:ellipsis}
  .jump::after{content:"";position:absolute;right:15px;top:50%;width:7px;height:7px;margin-top:-6px;pointer-events:none;
         border-right:1.6px solid var(--muted);border-bottom:1.6px solid var(--muted);transform:rotate(45deg)}
  .reader-tools .icon-btn{border-radius:12px}
  .reader-tools .icon-btn[aria-disabled="true"]{opacity:.35;pointer-events:none}
  .aa{font:700 15px/1 Georgia,serif;letter-spacing:-.02em}

  .chapter-body{
    /* Charter và Iowan Old Style có sẵn trên máy Apple, Georgia có ở mọi nơi —
       không tải font ngoài, nên không thêm một lượt chờ mạng nào trước khi chữ hiện ra. */
    font-family:Charter,"Bitstream Charter","Iowan Old Style","Palatino Linotype",Georgia,"Times New Roman",serif;
    font-size:var(--rs,20px);line-height:1.66;color:var(--text);
  }
  :root[data-reader-font="sans"] .chapter-body{
    font-family:-apple-system,BlinkMacSystemFont,"Segoe UI",Roboto,Helvetica,Arial,sans-serif;line-height:1.7}
  .chapter-body p{margin:0 0 1.35em;color:inherit}
  /* Đoạn đầu tiên: chữ cái đầu lớn, mắt biết bắt đầu từ đâu */
  .chapter-body.dropcap > p:first-child::first-letter{
    font-size:3.1em;line-height:.86;float:left;margin:.04em .09em 0 0;
    color:var(--accent-deep);font-weight:600}

  /* --- Chặn sao chép thân truyện --- xem partials/protect.blade.php để biết
     lớp này chặn được gì và KHÔNG chặn được gì. Chỉ nhắm vào thân chương và mô
     tả truyện; ô tìm kiếm, email ở footer, tên truyện vẫn bôi đen được. */
  .chapter-body,.synopsis{
    -webkit-user-select:none;-moz-user-select:none;-ms-user-select:none;user-select:none;
    /* Chặn bảng "Copy / Look Up / Share" khi nhấn giữ trên iOS. Phần lớn người
       đọc vào bằng điện thoại, nên thiếu dòng này là lớp chặn gần như vô nghĩa. */
    -webkit-touch-callout:none;
  }
  /* Ảnh bìa: tắt kéo-thả ở tầng CSS, không chỉ dựa vào JS. */
  .scard img,.rank img,.spot-cover img,.sh-cover img,.cont img,.chapter-body img{-webkit-user-drag:none;user-drag:none}

  /* In ra giấy / lưu thành PDF là đường copy sạch nhất còn lại, nên bịt luôn.
     Đánh đổi: người đọc không in truyện ra để đọc ngoại tuyến được nữa. */
  @media print{
    .chapter-body,.synopsis{display:none!important}
    .reader::after{
      content:"This chapter is free to read at tunastory.com. Printing is disabled.";
      display:block;font:16px Georgia,serif;color:#241E1B;padding:40px 0}
  }

  /* Cuối chương: thẻ "chương sau" to, rõ — đây là cú bấm quan trọng nhất của trang */
  .next-card{display:flex;align-items:center;justify-content:space-between;gap:16px;margin:40px 0 0;padding:20px 22px;
        border-radius:18px;background:var(--btn);color:#fff;text-decoration:none;box-shadow:0 10px 26px rgba(236,98,40,.3);
        transition:transform .15s}
  .next-card:hover{transform:translateY(-2px)}
  .next-card small{display:block;font-size:12px;letter-spacing:.09em;text-transform:uppercase;opacity:.85;font-weight:700}
  .next-card strong{display:block;font-size:18px;line-height:1.3;margin-top:3px}
  .next-card svg{width:26px;height:26px;flex:none}
  .next-card.end{background:var(--surface);color:var(--text);border:1px solid var(--border);box-shadow:var(--shadow)}
  .pager{display:flex;justify-content:space-between;gap:10px;margin:16px 0 0}
  .pager a{flex:1;text-align:center;padding:12px 14px;border-radius:12px;background:var(--surface);
           border:1px solid var(--border);text-decoration:none;font-size:14px;color:var(--text);font-weight:600;
           white-space:nowrap;overflow:hidden;text-overflow:ellipsis;transition:border-color .15s,background .15s}
  .pager a:hover{border-color:var(--accent);background:var(--accent-soft)}

  /* Nút "Aa" nổi và bảng chỉnh chữ. Cỡ chữ, phông và nền lưu ở localStorage; nền dùng
     chung với nút đổi nền ở đầu trang (một lựa chọn cho cả site). */
  .fab{position:fixed;right:20px;bottom:20px;z-index:60;width:52px;height:52px;border-radius:999px;
       background:var(--surface);color:var(--text);border:1px solid var(--border-strong);box-shadow:var(--shadow-lift);
       cursor:pointer;display:grid;place-items:center;padding:0}
  .rset{position:fixed;right:20px;bottom:84px;z-index:61;width:300px;max-width:calc(100vw - 40px);padding:18px;
        background:var(--surface);border:1px solid var(--border);border-radius:20px;box-shadow:var(--shadow-lift)}
  .rset[hidden]{display:none}
  .rset h3{font-size:12px;letter-spacing:.09em;text-transform:uppercase;color:var(--muted);margin:0 0 8px}
  .rset h3:not(:first-child){margin-top:16px}
  .seg{display:grid;grid-auto-flow:column;grid-auto-columns:1fr;gap:4px;padding:4px;border-radius:12px;background:var(--surface-2)}
  .seg button{border:0;background:none;padding:9px 6px;border-radius:9px;font:inherit;font-size:14px;color:var(--text-2);cursor:pointer}
  .seg button[aria-pressed="true"]{background:var(--surface);color:var(--text);font-weight:700;box-shadow:var(--shadow)}
  .seg .serif{font-family:Georgia,serif}
  .size-row{display:grid;grid-template-columns:44px 1fr 44px;align-items:center;gap:6px;padding:4px;border-radius:12px;background:var(--surface-2)}
  .size-row button{height:36px;border:0;border-radius:9px;background:var(--surface);color:var(--text);cursor:pointer;
        font:700 14px/1 Georgia,serif;box-shadow:var(--shadow)}
  .size-row button.big{font-size:19px}
  .size-row output{text-align:center;font-size:14px;color:var(--text-2);font-variant-numeric:tabular-nums}
  .swatch{display:inline-block;width:14px;height:14px;border-radius:999px;border:1px solid rgba(0,0,0,.18);vertical-align:-2px;margin-right:6px}

  /* Thanh tiến độ đọc bám đỉnh trang */
  .progress{position:fixed;top:0;left:0;height:3px;width:0;z-index:70;
            background:linear-gradient(90deg,var(--accent),var(--accent-deep))}

  /* --- điểm hài lòng của truyện --- */
  .rating{display:inline-flex;align-items:center;gap:6px;margin-top:8px;
          color:var(--accent-ink);font-weight:650;font-size:14px}
  .rating svg{width:19px;height:19px;flex:none}
  .rating-count{color:var(--muted);font-weight:400;font-size:13px}
  .rating.compact{margin:0;font-size:12.5px;gap:3px}
  .rating.compact svg{width:14px;height:14px}

  /* --- thang hài lòng cuối chương --- */
  .reaction{margin:44px 0 0;padding:22px 20px;background:var(--surface);
            border:1px solid var(--border);border-radius:18px;text-align:center}
  .reaction h2{font-size:16px;margin:0 0 16px;letter-spacing:0}
  /* Sau khi bỏ phiếu khối chỉ còn một dòng, nên bỏ khoảng trống phía dưới. */
  .reaction h2.reaction-done{margin:0;color:var(--accent-ink)}
  .reaction h2.reaction-done + .reaction-note{margin-top:8px}
  .faces{display:flex;justify-content:center;gap:8px;flex-wrap:wrap}
  .face{background:none;border:0;padding:8px 6px;cursor:pointer;color:var(--muted);
        border-radius:12px;display:flex;flex-direction:column;align-items:center;gap:6px;
        font:inherit;transition:color .15s,background .15s,transform .15s;min-width:64px}
  .face svg{width:40px;height:40px;display:block}
  .face-label{font-size:11px;line-height:1;opacity:0;transition:opacity .15s;white-space:nowrap}
  .face:hover{color:var(--accent-ink);background:var(--accent-soft);transform:translateY(-2px)}
  .face:hover .face-label,.face.chosen .face-label{opacity:1}
  .face.chosen{color:var(--accent-ink);background:var(--accent-soft)}
  .face:focus-visible{outline:2px solid var(--accent-deep);outline-offset:2px}
  .reaction-note{font-size:13px;color:var(--muted);margin:14px 0 0}
  .reaction-stat{display:block;margin-top:4px;color:var(--accent-ink);font-weight:600}

  /* --- ô quảng cáo --- */
  .adslot{margin:30px 0;min-height:100px;text-align:center;overflow:hidden}
  .adslot ins{display:block}

  .pagination{display:flex;gap:8px;flex-wrap:wrap;justify-content:center;margin:40px 0 0;padding:0;list-style:none}
  .pagination li{margin:0}
  .pagination a,.pagination span{display:inline-block;min-width:40px;text-align:center;padding:8px 13px;border-radius:10px;
        border:1px solid var(--border);text-decoration:none;font-size:14px;background:var(--surface);color:var(--text-2)}
  .pagination a:hover{border-color:var(--accent);color:var(--accent-ink)}
  .pagination .cur{background:var(--btn);border-color:var(--btn);color:#fff;font-weight:700}

  /* Trang danh sách (Browse / thể loại / tìm kiếm) */
  .page-head{margin:0 0 22px}
  .page-head .updated{margin:4px 0 0}
  .filters{margin:0 0 30px}

  /* ---------- Màn rộng ---------- */
  @media (min-width:1400px){
    .wrap.wide{max-width:1320px}
    .grid{grid-template-columns:repeat(6,minmax(0,1fr));gap:30px 20px}
  }

  /* ---------- Máy tính bảng / cửa sổ hẹp ---------- */
  @media (max-width:1060px){
    .home-top{grid-template-columns:minmax(0,1fr)}
    .shelf{grid-auto-columns:calc((100% - 3 * 18px) / 4);gap:18px}
    .story-cols{grid-template-columns:minmax(0,1fr)}
    .story-side .panel{position:static}
    .cont{grid-template-columns:repeat(2,minmax(0,1fr))}
  }
  @media (max-width:1000px){
    .grid{grid-template-columns:repeat(3,minmax(0,1fr))}
  }
  @media (max-width:860px){
    .main-nav,.head-row > .theme-toggle{display:none}
    .menu{display:block}
    .menu > summary{list-style:none}
    .foot-grid{grid-template-columns:repeat(2,minmax(0,1fr))}
    .foot-brand{grid-column:1 / -1}
  }

  /* ---------- Điện thoại ----------
     Header giữ MỘT hàng (~56px): header sticky nên mỗi pixel chiều cao bị chiếm vĩnh viễn.
     Lưới hai cột, không một cột — thẻ một cột to bằng nửa màn hình, cuộn mãi vẫn chỉ
     thấy được hai truyện. */
  @media (max-width:600px){
    .narrow-only{display:inline}
    .wide-only{display:none}
    .wrap,.wrap.wide{padding:0 16px}
    main{padding:20px 0 56px}
    .head-row{height:56px;gap:8px}
    .logo{font-size:19px;gap:7px}
    .logo img{width:26px;height:26px;border-radius:7px}
    .menu-pop{top:56px;max-height:calc(100vh - 56px)}
    .searchbox{flex:1}
    .searchbox input{font-size:16px;padding:8px 12px 8px 36px}
    .icon-btn{width:38px;height:38px}
    h1{font-size:26px}
    h2{font-size:18px;margin:28px 0 8px}
    .card{padding:18px 16px;border-radius:16px}
    .sec{margin-top:36px}
    .sec-head{margin-bottom:12px}
    .sec-head h2{font-size:19px}

    .grid{grid-template-columns:repeat(2,minmax(0,1fr));gap:22px 14px}
    .scard h3{font-size:14px}
    .scard .meta{font-size:12px}
    /* Hàng cuộn: hai thẻ rưỡi trên màn hình — nửa thẻ lộ ra là gợi ý "cuộn ngang được" */
    .shelf{grid-auto-columns:40%;gap:14px;margin:0 -16px;padding:4px 16px 10px;scroll-padding:0 16px}
    .chips.scroll{flex-wrap:nowrap;overflow-x:auto;scrollbar-width:none;margin:0 -16px;padding:2px 16px}
    .chips.scroll::-webkit-scrollbar{display:none}
    .chip{padding:7px 13px}

    .spotlight{border-radius:20px}
    .spot-inner{gap:16px;padding:20px;align-items:flex-start}
    .spot-cover{width:112px}
    .spotlight h2{font-size:20px;margin:6px 0}
    .spot-meta{font-size:13px;margin-bottom:8px}
    .spot-desc{font-size:14px;-webkit-line-clamp:3;margin-bottom:14px}
    .spotlight .chips{display:none}
    .spotlight .btn{padding:10px 18px;font-size:14px}
    .spotlight .btn.glass{display:none}
    .panel{border-radius:20px;padding:16px 8px 8px}
    .cont{grid-template-columns:minmax(0,1fr)}
    .app-band{padding:22px 18px;margin-top:40px}

    .story-hero{margin-top:-20px;padding:16px 0 26px}
    .sh-main{grid-template-columns:116px minmax(0,1fr);grid-template-areas:"cover head" "more more";
             grid-template-rows:auto auto;column-gap:16px}
    .sh-head{align-self:center;padding-top:0}
    .sh-head h1{font-size:21px;line-height:1.25}
    .sh-more{margin-top:16px}
    .sh-by{font-size:13.5px}
    .stats{margin:0 0 4px}
    .stats li{padding:0 12px}
    .stats strong{font-size:17px}
    .stats li > span{font-size:10px}
    .story-hero .chips{margin:16px 0 18px}
    .story-hero .actions .btn{flex:1 1 auto;white-space:nowrap;padding:12px 16px}
    .synopsis{font-size:15.5px}
    .chapters a{padding:13px 14px}

    .reader h1{font-size:24px}
    .reader-tools{margin:16px 0 24px;padding-bottom:18px}
    /* 19px serif trong bề ngang 343px cho khoảng 42-45 ký tự mỗi dòng — hẹp hơn chuẩn
       văn xuôi nhưng đó là giới hạn của thiết bị, bù lại bằng giãn dòng rộng hơn. */
    .chapter-body{font-size:var(--rs,19px);line-height:1.7}
    .chapter-body.dropcap > p:first-child::first-letter{font-size:2.9em}
    .next-card{padding:16px 18px}
    .next-card strong{font-size:16px}
    .pager a{font-size:13px;padding:12px 8px}
    .fab{right:14px;bottom:14px;width:48px;height:48px}
    .rset{right:14px;bottom:72px}

    /* Không có hover trên cảm ứng nên nhãn phải hiện sẵn, và mặt nhỏ lại cho
       năm cái vừa một hàng trong 343px. */
    .reaction{padding:18px 12px}
    .faces{gap:2px}
    .face{min-width:0;flex:1;padding:8px 2px}
    .face svg{width:34px;height:34px}
    .face-label{opacity:1;font-size:10px;white-space:normal;max-width:60px}
  }
  @media (max-width:360px){
    .logo span.word{display:none}
  }
  @media (prefers-reduced-motion:reduce){
    .scover,.btn,.next-card,.face{transition:none}
    .shelf{scroll-behavior:auto}
  }
</style>
@stack('head')
</head>
<body>
@php
    $navIcon = fn (string $name): string => match ($name) {
        'search' => '<svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2.2" stroke-linecap="round" aria-hidden="true"><circle cx="11" cy="11" r="7"/><path d="m20 20-3.6-3.6"/></svg>',
        'moon' => '<svg class="i-moon" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round" aria-hidden="true"><path d="M21 12.8A9 9 0 1 1 11.2 3a7 7 0 0 0 9.8 9.8z"/></svg>',
        'sun' => '<svg class="i-sun" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" aria-hidden="true"><circle cx="12" cy="12" r="4.2"/><path d="M12 2.5v2M12 19.5v2M4.6 4.6 6 6M18 18l1.4 1.4M2.5 12h2M19.5 12h2M4.6 19.4 6 18M18 6l1.4-1.4"/></svg>',
        'menu' => '<svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2.2" stroke-linecap="round" aria-hidden="true"><path d="M4 7h16M4 12h16M4 17h16"/></svg>',
        default => '',
    };
    $current = fn (string ...$routes): string => request()->routeIs(...$routes) ? ' aria-current="page"' : '';
@endphp
<header class="site-head"><div class="wrap wide head-row">
  <a class="logo" href="{{ route('public.home') }}">
    <img src="{{ asset('icons/icon-48.png') }}" width="30" height="30" alt="">
    <span class="word" style="color:inherit">Sto<span>ries</span></span>
  </a>
  <nav class="main-nav" aria-label="Main">
    <a href="{{ route('public.home') }}"{!! $current('public.home') !!}>Home</a>
    <a href="{{ route('public.browse') }}"{!! $current('public.browse') !!}>Browse</a>
    @if (($navCategories ?? collect())->isNotEmpty())
      {{-- <details> mở/đóng được cả khi không có JavaScript; JS ở cuối trang chỉ lo
           đóng nó khi bấm ra ngoài. --}}
      <details class="genres">
        <summary>Genres</summary>
        <div class="genres-pop">
          @foreach ($navCategories as $c)
            <a href="{{ route('public.category', $c) }}">{{ $c->name }} <small>{{ $c->stories_count }}</small></a>
          @endforeach
        </div>
      </details>
    @endif
    {{-- About và Contact chỉ hiện trên màn rộng; màn hẹp có trong menu và ở footer MỌI
         trang. Contact đứng trên thanh điều hướng vì người soát AdSense và người gửi
         khiếu nại bản quyền đều tìm chữ "Contact" ở đây trước. --}}
    <a href="{{ route('public.about') }}"{!! $current('public.about') !!}>About</a>
    <a href="{{ route('public.contact') }}"{!! $current('public.contact') !!}>Contact</a>
  </nav>
  <form class="searchbox" action="{{ route('public.search') }}" method="get" role="search">
    <input type="search" name="q" value="{{ request('q') }}" placeholder="Search stories" aria-label="Search stories">
    <button type="submit" aria-label="Search">{!! $navIcon('search') !!}</button>
  </form>
  <button class="icon-btn theme-toggle" type="button" aria-label="Switch light or dark mode" title="Light / dark">
    {!! $navIcon('moon') !!}{!! $navIcon('sun') !!}
  </button>
  <details class="menu">
    <summary class="icon-btn" aria-label="Menu">{!! $navIcon('menu') !!}</summary>
    <div class="menu-pop">
      <a href="{{ route('public.home') }}">Home</a>
      <a href="{{ route('public.browse') }}">Browse all stories</a>
      <a href="{{ route('public.about') }}">About</a>
      <a href="{{ route('public.contact') }}">Contact</a>
      <a href="#" class="theme-toggle" role="button">Light / dark mode</a>
      @if (($navCategories ?? collect())->isNotEmpty())
        <h3>Genres</h3>
        <ul class="chips">
          @foreach ($navCategories as $c)
            <li><a class="chip" href="{{ route('public.category', $c) }}">{{ $c->name }}</a></li>
          @endforeach
        </ul>
      @endif
    </div>
  </details>
</div></header>
<main>@yield('hero')<div class="wrap{{ $isWide ? ' wide' : '' }}">@yield('content')</div></main>
<footer class="site-foot"><div class="wrap wide">
  <div class="foot-grid">
    <div class="foot-brand">
      <a class="logo" href="{{ route('public.home') }}">
        <img src="{{ asset('icons/icon-48.png') }}" width="30" height="30" alt="">
        <span style="color:inherit">Sto<span>ries</span></span>
      </a>
      <p>Short serials you can finish in one sitting. Every chapter free to read on the web.</p>
      @unless ($legalPage)
      <div class="app-promo">
        <a class="qr" href="{{ route('public.app') }}" aria-label="Download Stories on the App Store">
          <img src="{{ asset('images/app-qr.svg') }}" width="88" height="88" loading="lazy"
               alt="QR code to download the Stories app for iPhone">
        </a>
        <div>
          <strong>Read Stories on iPhone</strong>
          <span class="qr-hint">Scan with your phone's camera, or</span>
          <a href="{{ route('public.app') }}">download it on the App Store</a>
        </div>
      </div>
      @endunless
    </div>
    <div>
      <h4>Read</h4>
      <a href="{{ route('public.home') }}">Home</a>
      <a href="{{ route('public.browse') }}">Browse</a>
      @foreach (($navCategories ?? collect())->sortByDesc('stories_count')->take(4) as $c)
        <a href="{{ route('public.category', $c) }}">{{ $c->name }}</a>
      @endforeach
    </div>
    <div>
      <h4>Stories</h4>
      <a href="{{ route('public.about') }}">About</a>
      <a href="{{ route('public.editorial') }}">Editorial standards</a>
      <a href="{{ route('public.contact') }}">Contact</a>
      <a href="mailto:{{ config('app.support_email') }}">{{ config('app.support_email') }}</a>
    </div>
    <div>
      <h4>Legal</h4>
      <a href="{{ route('public.privacy') }}">Privacy Policy</a>
      <a href="{{ route('public.terms') }}">Terms of Service</a>
    </div>
  </div>
  <div class="foot-bottom">
    @include('public.partials.disclosure')
    <div style="margin-top:10px">&copy; {{ date('Y') }} Stories</div>
  </div>
</div></footer>
@include('public.partials.protect')
{{-- Mời tải app trên iPhone/iPad. Không gắn trên ba trang pháp lý (app mở chúng bên trong app). --}}
@unless ($legalPage)
  @include('public.partials.app-prompt')
@endunless
<script>
(function () {
  var root = document.documentElement;
  // Nút đổi nền: sáng <-> tối. Đang sepia (chọn trong màn đọc) thì bấm sang tối.
  function effective() {
    var t = root.getAttribute('data-theme');
    if (t) return t;
    return matchMedia('(prefers-color-scheme: dark)').matches ? 'dark' : 'light';
  }
  document.querySelectorAll('.theme-toggle').forEach(function (b) {
    b.addEventListener('click', function (e) {
      e.preventDefault();
      var next = effective() === 'dark' ? 'light' : 'dark';
      root.setAttribute('data-theme', next);
      try { localStorage.setItem('stories.theme', next); } catch (err) {}
      document.dispatchEvent(new CustomEvent('stories:theme', { detail: next }));
    });
  });
  // Menu thả xuống (<details>): bấm ra ngoài hoặc Esc thì đóng.
  document.addEventListener('click', function (e) {
    document.querySelectorAll('details.genres[open], details.menu[open]').forEach(function (d) {
      if (!d.contains(e.target)) d.removeAttribute('open');
    });
  });
  document.addEventListener('keydown', function (e) {
    if (e.key !== 'Escape') return;
    document.querySelectorAll('details.genres[open], details.menu[open]').forEach(function (d) { d.removeAttribute('open'); });
  });
  // Hàng cuộn ngang: nút mũi tên cuộn đúng một "trang" thẻ, tự mờ đi ở hai đầu.
  document.querySelectorAll('.shelf-wrap').forEach(function (w) {
    var list = w.querySelector('.shelf'), prev = w.querySelector('.prev'), next = w.querySelector('.next');
    if (!list || !prev || !next) return;
    function sync() {
      prev.disabled = list.scrollLeft < 8;
      next.disabled = list.scrollLeft + list.clientWidth > list.scrollWidth - 8;
    }
    prev.addEventListener('click', function () { list.scrollBy({ left: -list.clientWidth * .9, behavior: 'smooth' }); });
    next.addEventListener('click', function () { list.scrollBy({ left: list.clientWidth * .9, behavior: 'smooth' }); });
    list.addEventListener('scroll', sync, { passive: true });
    addEventListener('resize', sync, { passive: true });
    sync();
  });
})();
</script>
</body>
</html>
