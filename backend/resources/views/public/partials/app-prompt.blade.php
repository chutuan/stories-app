{{--
  Mời người đọc trên iPhone/iPad tải app — bottom sheet, KHÔNG phải hộp che kín màn hình.

  Vì sao không bật ngay khi vào trang: Google hạ hạng trang di động hiện popup che nội
  dung ngay sau khi người dùng bấm vào từ kết quả tìm kiếm ("intrusive interstitials"),
  và lời mời cài app là ví dụ chính họ nêu. Nên:
    - vào từ máy tìm kiếm thì KHÔNG hiện ở trang đó, đợi trang kế tiếp;
    - các trường hợp khác hiện sau 8 giây, khi người đọc đã thấy nội dung;
    - đóng thì 7 ngày sau mới hiện lại, bấm tải thì 30 ngày;
    - không gắn trên Privacy/Terms/Contact (layout lo phần này): app mở ba trang đó bên trong app.
  Safari trên iPhone còn tự hiện Smart App Banner (thẻ meta apple-itunes-app trong layout),
  thứ đó không bị Google phạt; modal này là lời mời thứ hai, rõ hơn.

  localStorage chỉ để nhớ "đã đóng" — lỗi đọc/ghi (chế độ riêng tư) thì vẫn chạy, cùng
  lắm là hiện lại ở lần sau.
--}}
<div id="app-prompt" class="ap" hidden>
  <div class="ap-backdrop" data-ap-close></div>
  <div class="ap-sheet" role="dialog" aria-modal="true" aria-labelledby="ap-title" tabindex="-1">
    <button class="ap-x" type="button" aria-label="Close" data-ap-close>&times;</button>
    <div class="ap-head">
      <img src="{{ asset('icons/icon-180.png') }}" width="60" height="60" alt="">
      <div>
        <strong id="ap-title">Read Stories in the app</strong>
        <span>Free on the App Store</span>
      </div>
    </div>
    <ul class="ap-list">
      <li>Adjust font size, line spacing and page theme</li>
      <li>Listen to narrated stories</li>
      <li>Keep a library on your phone, no account needed</li>
    </ul>
    <a class="btn ap-get" href="{{ route('public.app') }}">Get the free app</a>
    <button class="ap-later" type="button" data-ap-close>Continue in browser</button>
  </div>
</div>
<style>
  .ap{position:fixed;inset:0;z-index:1000}
  .ap[hidden]{display:none}
  .ap-backdrop{position:absolute;inset:0;background:rgba(26,22,20,.38);opacity:0;transition:opacity .22s ease}
  .ap-sheet{position:absolute;left:0;right:0;bottom:0;max-width:480px;margin:0 auto;background:var(--surface);
            border-radius:20px 20px 0 0;padding:22px 20px calc(18px + env(safe-area-inset-bottom));
            box-shadow:0 -10px 34px rgba(26,22,20,.18);transform:translateY(100%);transition:transform .26s ease}
  .ap-sheet:focus{outline:none}
  .ap.open .ap-backdrop{opacity:1}
  .ap.open .ap-sheet{transform:translateY(0)}
  .ap-x{position:absolute;top:10px;right:12px;width:36px;height:36px;border:0;border-radius:50%;
        background:transparent;color:var(--muted);font-size:26px;line-height:1;cursor:pointer}
  .ap-head{display:flex;gap:14px;align-items:center;margin:0 36px 14px 0}
  .ap-head img{width:60px;height:60px;border-radius:14px;border:1px solid var(--border);flex:none}
  .ap-head strong{display:block;font-size:18px;line-height:1.25}
  .ap-head span{color:var(--muted);font-size:14px}
  .ap-list{margin:0 0 18px;padding-left:20px;font-size:15px;line-height:1.55}
  .ap-get{display:block;text-align:center;width:100%;box-sizing:border-box}
  .ap-later{display:block;width:100%;margin-top:8px;padding:12px;border:0;background:transparent;
            color:var(--muted);font-size:15px;cursor:pointer}
  @media (prefers-reduced-motion:reduce){.ap-backdrop,.ap-sheet{transition:none}}
</style>
<script>
(function () {
  var ua = navigator.userAgent || '';
  // iPadOS 13+ tự xưng là Mac: nhận ra nhờ màn cảm ứng nhiều điểm.
  var isIOS = /iPhone|iPad|iPod/.test(ua) || (/Macintosh/.test(ua) && navigator.maxTouchPoints > 1);
  if (!isIOS || window.navigator.standalone) return;

  var KEY = 'stories.appPrompt';
  var DAY = 864e5;
  function snoozedUntil() {
    try { return JSON.parse(localStorage.getItem(KEY) || '{}').until || 0; } catch (e) { return 0; }
  }
  function snooze(days) {
    try { localStorage.setItem(KEY, JSON.stringify({ until: Date.now() + days * DAY })); } catch (e) {}
  }
  if (snoozedUntil() > Date.now()) return;

  var host = (document.referrer.split('/')[2] || '').toLowerCase();
  if (/(^|\.)(google|bing|duckduckgo|yahoo|yandex|baidu|ecosia|brave)\./.test(host)) return;

  var root = document.getElementById('app-prompt');
  if (!root) return;
  var lastFocus = null;

  function close(days) {
    snooze(days);
    root.classList.remove('open');
    document.documentElement.style.overflow = '';
    document.removeEventListener('keydown', onKey);
    setTimeout(function () { root.hidden = true; }, 260);
    if (lastFocus && lastFocus.focus) lastFocus.focus();
  }
  function onKey(e) { if (e.key === 'Escape') close(7); }

  function open() {
    lastFocus = document.activeElement;
    root.hidden = false;
    document.documentElement.style.overflow = 'hidden';
    // Đợi một khung hình để transition chạy từ vị trí ẩn.
    requestAnimationFrame(function () { requestAnimationFrame(function () { root.classList.add('open'); }); });
    // Focus vào khung hộp thoại chứ không vào nút: trình đọc màn hình đọc tiêu đề trước,
    // và Safari không vẽ viền focus xanh quanh nút cam như khi focus thẳng vào nút.
    root.querySelector('.ap-sheet').focus({ preventScroll: true });
    document.addEventListener('keydown', onKey);
  }

  root.addEventListener('click', function (e) {
    if (e.target.closest('[data-ap-close]')) { close(7); }
    // Link vẫn mở App Store; đóng luôn hộp để quay lại Safari không gặp lại nó.
    else if (e.target.closest('.ap-get')) { close(30); }
  });

  // Đang gõ ô tìm kiếm thì không bật hộp lên giữa chừng (cướp focus, tắt bàn phím):
  // chờ rời ô rồi 3 giây sau thử lại.
  function typing() {
    var a = document.activeElement;
    return !!a && (a.isContentEditable || /^(INPUT|TEXTAREA|SELECT)$/.test(a.tagName));
  }
  function trigger() {
    if (typing()) {
      document.addEventListener('focusout', function () { setTimeout(trigger, 3000); }, { once: true });
      return;
    }
    open();
  }

  setTimeout(trigger, 8000);
})();
</script>
