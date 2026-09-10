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
    $seoImage = trim($__env->yieldContent('og_image', asset('icons/icon-512.png')));
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
<meta name="twitter:card" content="summary_large_image">
<meta name="twitter:title" content="{!! $seo($seoTitle) !!}">
<meta name="twitter:description" content="{!! $seo($seoDescription) !!}">
<meta name="twitter:image" content="{!! $seo($seoImage) !!}">

<link rel="icon" href="/favicon.ico" sizes="any">
<link rel="icon" type="image/png" sizes="32x32" href="{{ asset('icons/icon-32.png') }}">
<link rel="icon" type="image/png" sizes="16x16" href="{{ asset('icons/icon-16.png') }}">
<link rel="apple-touch-icon" href="{{ asset('icons/icon-180.png') }}">
<meta name="theme-color" content="#FF9052">

{{-- Google AdSense. Cùng publisher ID với AdMob của app (pub-9892355907152840),
     nhưng là HAI sản phẩm riêng: AdSense phục vụ website, AdMob phục vụ app, và
     mỗi bên đòi một file xác thực riêng (/ads.txt cho AdSense, /app-ads.txt cho
     AdMob — xem routes/web.php).

     Chỉ nhúng khi ADMOB_PUBLISHER_ID có giá trị, cùng lý do với route /ads.txt:
     máy dev không nên gọi ra Google, và thẻ script mang client rỗng thì AdSense
     báo lỗi thay vì im lặng bỏ qua. --}}
@if (filled(config('app.admob_publisher_id')))
<script async src="https://pagead2.googlesyndication.com/pagead/js/adsbygoogle.js?client=ca-{{ config('app.admob_publisher_id') }}"
     crossorigin="anonymous"></script>
@endif

{{-- Google Analytics 4 — chỉ cho WEBSITE. App không có SDK phân tích nào, và thư
     gửi Apple khai đúng như vậy; đừng đem thẻ này sang app. Cùng quy tắc với
     AdSense: thiếu ID thì không in gì, để máy dev không bơm dữ liệu giả vào báo
     cáo và mỗi lần chạy test không bị tính thành một phiên truy cập. --}}
@if (filled(config('app.ga_measurement_id')))
<script async src="https://www.googletagmanager.com/gtag/js?id={{ config('app.ga_measurement_id') }}"></script>
<script>
  window.dataLayer = window.dataLayer || [];
  function gtag(){dataLayer.push(arguments);}
  gtag('js', new Date());
  gtag('config', '{{ config('app.ga_measurement_id') }}');
</script>
@endif
<style>
  :root{--bg:#FDF8F4;--surface:#fff;--text:#1A1614;--muted:#6E635C;
        --accent:#FF9052;--accent-deep:#F2703A;--border:rgba(26,22,20,.10)}
  *{box-sizing:border-box}
  body{margin:0;background:var(--bg);color:var(--text);line-height:1.7;
       font:16px/1.7 -apple-system,BlinkMacSystemFont,"Segoe UI",Roboto,Helvetica,Arial,sans-serif}
  .wrap{max-width:760px;margin:0 auto;padding:0 20px}
  .wrap.wide{max-width:1080px}
  header{border-bottom:1px solid var(--border);background:var(--surface);position:sticky;top:0;z-index:10}
  header .wrap{display:flex;align-items:center;justify-content:space-between;gap:16px;min-height:64px;flex-wrap:wrap}
  .logo{font-size:22px;font-weight:800;letter-spacing:-.02em;text-decoration:none;color:var(--text);white-space:nowrap}
  .logo span{color:var(--accent-deep)}
  nav{display:flex;align-items:center;gap:18px;flex-wrap:wrap}
  nav a{color:var(--muted);text-decoration:none;font-size:14px}
  nav a:hover,nav a[aria-current="page"]{color:var(--accent-deep)}
  .searchbox{display:flex;gap:6px}
  .searchbox input{font:inherit;font-size:14px;padding:7px 12px;border:1px solid var(--border);
                   border-radius:999px;background:var(--bg);color:var(--text);min-width:150px}
  .searchbox button{font:inherit;font-size:14px;padding:7px 14px;border:0;border-radius:999px;
                    background:var(--accent-deep);color:#fff;cursor:pointer}
  main{padding:36px 0 72px}
  h1{font-size:32px;line-height:1.25;margin:0 0 8px;letter-spacing:-.02em}
  h2{font-size:19px;margin:34px 0 10px;letter-spacing:-.01em}
  .updated{color:var(--muted);font-size:14px;margin:0 0 28px}
  p,li{color:#2E2724}
  ul{padding-left:20px}
  li{margin:6px 0}
  a{color:var(--accent-deep)}
  .card{background:var(--surface);border:1px solid var(--border);border-radius:16px;padding:22px 24px;margin:22px 0}
  footer{border-top:1px solid var(--border);padding:26px 0;color:var(--muted);font-size:14px}
  footer a{color:var(--muted);margin-right:16px}

  /* --- lưới truyện --- */
  .grid{display:grid;gap:22px;grid-template-columns:repeat(auto-fill,minmax(160px,1fr));margin:18px 0 0;padding:0;list-style:none}
  .grid li{margin:0}
  .scard{display:block;text-decoration:none;color:inherit}
  .scard img,.scard .noart{width:100%;aspect-ratio:2/3;object-fit:cover;border-radius:12px;
                           border:1px solid var(--border);background:#EFE7E0;display:block}
  .scard .noart{display:flex;align-items:center;justify-content:center;color:var(--muted);font-size:13px;text-align:center;padding:10px}
  .scard h3{font-size:15px;line-height:1.35;margin:9px 0 2px;font-weight:650}
  .scard .meta{color:var(--muted);font-size:13px;margin:0}

  /* --- chi tiết truyện --- */
  .hero{display:flex;gap:24px;align-items:flex-start;flex-wrap:wrap}
  .hero img,.hero .noart{width:180px;aspect-ratio:2/3;object-fit:cover;border-radius:14px;
                         border:1px solid var(--border);background:#EFE7E0;flex:none}
  .hero .noart{display:flex;align-items:center;justify-content:center;color:var(--muted);font-size:13px}
  .hero-body{flex:1;min-width:240px}
  .tags{display:flex;flex-wrap:wrap;gap:8px;margin:10px 0 0;padding:0;list-style:none}
  .tags li{margin:0}
  .tag{display:inline-block;font-size:13px;padding:3px 11px;border-radius:999px;
       background:#fff;border:1px solid var(--border);color:var(--muted);text-decoration:none}
  .tag.on{background:var(--accent-deep);border-color:var(--accent-deep);color:#fff}
  .btn{display:inline-block;margin-top:16px;padding:11px 22px;border-radius:999px;
       background:var(--accent-deep);color:#fff;text-decoration:none;font-weight:650}
  .chapters{list-style:none;padding:0;margin:14px 0 0;border:1px solid var(--border);
            border-radius:14px;overflow:hidden;background:var(--surface)}
  .chapters li{margin:0;border-top:1px solid var(--border)}
  .chapters li:first-child{border-top:0}
  .chapters a{display:flex;gap:12px;padding:13px 18px;text-decoration:none;color:inherit;align-items:baseline}
  .chapters a:hover{background:#FFF6F0}
  .chapters .n{color:var(--muted);font-size:13px;min-width:34px;flex:none}

  /* --- màn đọc ---
     Ba con số quyết định đọc dài có mỏi mắt hay không, đo trên bản cũ ở 1280px:
     80 ký tự mỗi dòng (chuẩn văn xuôi là 60-75), font SANS thừa kế từ body, và
     khoảng cách đoạn chỉ 1.15em trong khi giãn dòng tận 1.85 — các đoạn dính vào
     nhau thành một khối chữ đặc. Sửa cả ba. */
  /* Đặt bằng px, KHÔNG bằng em: em ở đây tính theo font 16px của .reader chứ
     không phải 20px của phần chữ, nên 35em ra 560px = 57 ký tự mỗi dòng, hẹp hơn
     mong muốn. 660px ở cỡ chữ 20px Charter cho khoảng 67 ký tự — giữa vùng 60-75. */
  .reader{max-width:660px;margin:0 auto}
  .reader-kicker{font-size:12px;letter-spacing:.09em;text-transform:uppercase;
                 color:var(--accent-deep);font-weight:700;margin:0 0 6px}
  .reader h1{font-size:30px;line-height:1.2;margin:0 0 6px;letter-spacing:-.015em}
  .reader-by{color:var(--muted);font-size:14px;margin:0 0 22px}
  .reader-rule{border:0;border-top:1px solid var(--border);margin:0 0 30px}

  .chapter-body{
    /* Charter và Iowan Old Style có sẵn trên máy Apple, Georgia có ở mọi nơi —
       không tải font ngoài, nên không thêm một lượt chờ mạng nào trước khi chữ
       hiện ra. */
    font-family:Charter,"Bitstream Charter","Iowan Old Style","Palatino Linotype",Georgia,"Times New Roman",serif;
    font-size:20px;line-height:1.62;color:#241E1B;
    /* Chữ serif nhỏ dễ bị mảnh trên nền sáng; tắt tối ưu hoá độ nét của macOS
       cho nét dày lại đúng như thiết kế. */
    -webkit-font-smoothing:antialiased;
  }
  .chapter-body p{margin:0 0 1.35em}
  /* Đoạn đầu tiên: chữ cái đầu lớn, mắt biết bắt đầu từ đâu */
  .chapter-body.dropcap > p:first-child::first-letter{
    font-size:3.1em;line-height:.86;float:left;margin:.04em .09em 0 0;
    color:var(--accent-deep);font-weight:600}

  .pager{display:flex;justify-content:space-between;gap:12px;margin:44px 0 0;flex-wrap:wrap}
  .pager a{padding:13px 20px;border-radius:12px;background:var(--surface);
           border:1px solid var(--border);text-decoration:none;font-size:14px;
           color:var(--text);font-weight:600;transition:border-color .15s,background .15s}
  .pager a:hover{border-color:var(--accent);background:#FFF6F0}
  .crumbs{font-size:13px;color:var(--muted);margin:0 0 18px}
  .crumbs a{color:var(--muted)}

  /* Thanh tiến độ đọc bám đỉnh trang */
  .progress{position:fixed;top:0;left:0;height:3px;width:0;z-index:20;
            background:linear-gradient(90deg,var(--accent),var(--accent-deep))}

  /* --- ô quảng cáo --- */
  .adslot{margin:30px 0;min-height:100px;text-align:center;overflow:hidden}
  .adslot ins{display:block}

  .pagination{display:flex;gap:8px;flex-wrap:wrap;margin:30px 0 0;padding:0;list-style:none}
  .pagination li{margin:0}
  .pagination a,.pagination span{display:inline-block;padding:7px 13px;border-radius:9px;
        border:1px solid var(--border);text-decoration:none;font-size:14px;background:var(--surface)}
  .pagination .cur{background:var(--accent-deep);border-color:var(--accent-deep);color:#fff}
  .empty{text-align:center;color:var(--muted);padding:44px 0}
  /* ---------- Màn hẹp ----------
     Đo trên iPhone 375px trước khi sửa: header cao 136px (17% màn hình) vì logo,
     nav và ô tìm kiếm xếp thành ba hàng — mà header lại sticky nên chiếm chỗ đó
     vĩnh viễn. Lưới truyện tụt xuống 1 cột vì minmax(160px) không đủ chỗ cho hai
     cột trong 335px nội dung. Thẻ hero vỡ hàng vì ảnh 180px cộng thân tối thiểu
     240px vượt bề ngang màn hình. */
  .narrow-only{display:none}

  @media (max-width:600px){
    .wide-only{display:none}
    .narrow-only{display:inline}

    .wrap,.wrap.wide{padding:0 16px}
    main{padding:22px 0 56px}

    /* Header về MỘT hàng, cao ~56px thay vì 136px */
    header .wrap{min-height:56px;gap:10px;flex-wrap:nowrap}
    .logo{font-size:19px}
    nav{gap:12px;flex:1;justify-content:flex-end;flex-wrap:nowrap}
    .searchbox{flex:1;max-width:180px}
    .searchbox input{min-width:0;width:100%;padding:8px 12px}
    /* Nút mũi tên: giữ tối thiểu 40px cho vừa đầu ngón tay */
    .searchbox button{padding:8px 14px;min-width:40px;font-size:16px;line-height:1}

    /* Hai cột truyện thay vì một — thẻ 1 cột to bằng nửa màn hình, cuộn mãi
       không hết mà vẫn chỉ thấy được hai truyện. */
    .grid{grid-template-columns:repeat(2,1fr);gap:14px}
    .scard h3{font-size:14px}
    .scard .meta{font-size:12px}

    /* Hero: ảnh và chữ đi CẠNH nhau. Xếp dọc thì ảnh 120px đứng một mình một
       hàng, để lại mảng trắng hai phần ba bề ngang. flex-basis:100% là thứ đẩy
       thân chữ xuống dòng, nên đổi thành flex:1 kèm min-width:0 (thiếu min-width
       thì nội dung dài vẫn phá vỡ flex item). */
    .hero{gap:14px;flex-wrap:nowrap}
    .hero img,.hero .noart{width:126px}
    .hero-body{min-width:0;flex:1}
    .hero-body h1,.hero-body h2{font-size:20px;line-height:1.25}
    /* Tóm tắt cắt còn 4 dòng: đọc đủ để tò mò mà không đẩy nút xuống quá sâu */
    .hero-body p:not(.meta){display:-webkit-box;-webkit-line-clamp:4;
      -webkit-box-orient:vertical;overflow:hidden;font-size:14px;margin:8px 0 0}
    .hero .btn{margin-top:12px;padding:10px 18px;font-size:14px}

    h1{font-size:25px}
    h2{font-size:17px;margin:26px 0 8px}
    .card{padding:18px 16px;border-radius:14px}

    /* Màn đọc trên điện thoại: 19px serif trong bề ngang 343px cho khoảng 42-45
       ký tự mỗi dòng — hẹp hơn chuẩn văn xuôi nhưng đó là giới hạn của thiết bị,
       bù lại bằng giãn dòng rộng hơn. */
    .chapter-body{font-size:19px;line-height:1.68}
    .reader h1{font-size:24px}
    .reader-by{margin-bottom:18px}
    .reader-rule{margin-bottom:24px}
    .chapter-body.dropcap > p:first-child::first-letter{font-size:2.9em}

    /* Previous / Next không được vỡ dòng, và phải đủ to để bấm */
    .pager{gap:8px;flex-wrap:nowrap}
    .pager a{flex:1;text-align:center;padding:12px 8px;font-size:13px;
             white-space:nowrap;overflow:hidden;text-overflow:ellipsis}

    .chapters a{padding:15px 14px}
    .tag{padding:5px 12px}
    footer a{display:inline-block;margin:0 14px 8px 0}
  }

  /* Rất hẹp (iPhone SE): nhường thêm chỗ cho ô tìm kiếm */
  @media (max-width:360px){
    nav{gap:8px}
    .searchbox{max-width:150px}
    .logo{font-size:18px}
  }
</style>
@stack('head')
</head>
<body>
<header><div class="wrap{{ $isWide ? ' wide' : '' }}">
  <a class="logo" href="{{ route('public.home') }}">Sto<span>ries</span></a>
  <nav>
    <a href="{{ route('public.browse') }}">Browse</a>
    {{-- Privacy vẫn ở footer nên trên màn hẹp ẩn đi, nhường chỗ cho ô tìm kiếm --}}
    <a class="wide-only" href="{{ route('public.privacy') }}">Privacy</a>
    <form class="searchbox" action="{{ route('public.search') }}" method="get" role="search">
      <input type="search" name="q" value="{{ request('q') }}" placeholder="Search stories" aria-label="Search stories">
      <button type="submit" aria-label="Search"><span class="wide-only">Search</span><span class="narrow-only" aria-hidden="true">→</span></button>
    </form>
  </nav>
</div></header>
<main><div class="wrap{{ $isWide ? ' wide' : '' }}">@yield('content')</div></main>
<footer><div class="wrap{{ $isWide ? ' wide' : '' }}">
  <a href="{{ route('public.home') }}">Home</a>
  <a href="{{ route('public.browse') }}">Browse</a>
  <a href="{{ route('public.privacy') }}">Privacy Policy</a>
  <a href="{{ route('public.terms') }}">Terms of Service</a>
  <a href="mailto:{{ config('app.support_email') }}">{{ config('app.support_email') }}</a>
  <div style="margin-top:10px">&copy; {{ date('Y') }} Stories</div>
</div></footer>
</body>
</html>
