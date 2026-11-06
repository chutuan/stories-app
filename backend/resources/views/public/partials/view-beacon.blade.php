{{--
  Báo về server "có một người vừa đọc trang này" — thay cho việc đếm lúc GET trang.

  @include('public.partials.view-beacon', ['story' => $story, 'chapter' => $chapter])   // trang chương
  @include('public.partials.view-beacon', ['story' => $story, 'chapter' => null])       // trang truyện

  LUÔN truyền `chapter`, kể cả null: @include kế thừa biến của trang gọi, và trang truyện có
  vòng @foreach ($story->chapters as $chapter) — bỏ trống là nó báo nhầm thành chương cuối.

  Chỉ gửi khi CẢ HAI điều kiện đã xảy ra:
    - trang hiện trên màn hình liền 5 giây (tab nền, trang đang prerender thì không tính);
    - người xem đã cuộn, chạm, bấm phím hoặc lăn chuột ít nhất một lần.
  Máy cào nội dung không chạy JavaScript nên không bao giờ tới đây; trình duyệt tự động chạy
  JS mà không cuộn cũng không. Mỗi lần mở trang gửi tối đa một lần, kể cả khi quay lại bằng
  nút Back (trang lấy từ bfcache giữ nguyên biến `sent`).

  Đường dẫn TƯƠNG ĐỐI: gọi cùng tên miền nên không dính CORS, và không lệ thuộc APP_URL
  (production đặt api.tunastory.com). Xem App\Http\Controllers\Api\WebSeenController.
--}}
@php
    $seenUrl = route('api.seen', ['story' => $story->getKey()] + ($chapter ? ['chapter' => $chapter->number] : []), false);
@endphp
<script>
(function () {
  var url = @json($seenUrl), DWELL = 5000;
  var sent = false, dwelled = false, engaged = false, timer = null;
  function send() {
    if (sent || !dwelled || !engaged) return;
    sent = true;
    try { if (navigator.sendBeacon && navigator.sendBeacon(url)) return; } catch (e) {}
    try { fetch(url, { method: 'POST', keepalive: true, credentials: 'omit' }); } catch (e) {}
  }
  function arm() {
    clearTimeout(timer);
    if (sent || dwelled) return;
    if (document.visibilityState === 'visible' && !document.prerendering) {
      timer = setTimeout(function () { dwelled = true; send(); }, DWELL);
    }
  }
  ['scroll', 'wheel', 'touchstart', 'pointerdown', 'keydown'].forEach(function (type) {
    addEventListener(type, function () { engaged = true; send(); }, { passive: true, capture: true, once: true });
  });
  document.addEventListener('visibilitychange', arm);
  document.addEventListener('prerenderingchange', arm);
  arm();
})();
</script>
