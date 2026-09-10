{{--
  Một ô quảng cáo AdSense.

      @include('public.partials.ad')
      @include('public.partials.ad', ['slot' => config('app.adsense_slot_article')])

  KHÔNG in gì khi thiếu publisher ID hoặc thiếu slot. Ô rỗng không chỉ xấu — một
  <ins class="adsbygoogle"> mang data-ad-slot rỗng bị AdSense tính là lỗi triển
  khai, và ảnh chụp màn hình App Store từng dính đúng kiểu ô quảng cáo trống này.

  Chưa tạo ad unit nào thì cứ để trống: chỉ riêng thẻ script trong layout đã đủ
  cho Auto ads (bật trong bảng điều khiển AdSense), Google tự chọn vị trí.
--}}
@php
    $pub = config('app.admob_publisher_id');
    $slot = $slot ?? config('app.adsense_slot_article');
    $format = $format ?? 'auto';
    $label = $label ?? 'Advertisement';
@endphp
@if (filled($pub) && filled($slot))
  <div class="adslot" role="complementary" aria-label="{{ $label }}">
    <ins class="adsbygoogle"
         style="display:block"
         data-ad-client="ca-{{ $pub }}"
         data-ad-slot="{{ $slot }}"
         data-ad-format="{{ $format }}"
         data-full-width-responsive="true"></ins>
    <script>(adsbygoogle = window.adsbygoogle || []).push({});</script>
  </div>
@endif
