/*!
 * Stories — mời người đọc trên iPhone/iPad tải app, dùng trên trang web KHÁC tunastory.com.
 *
 * Nhúng (dán trước </body>, hoặc trong <head> cũng được vì có async):
 *   <script src="https://tunastory.com/embed/app-prompt.js" async></script>
 *
 * Tuỳ chọn trên chính thẻ <script>:
 *   data-delay="8"          số giây chờ trước khi hiện, mặc định 8
 *   data-campaign="blog-a"  nhãn nguồn gắn vào link tải (?src=), mặc định là tên miền trang nhúng
 *
 * Cùng quy tắc với lời mời trên tunastory.com (partials/app-prompt.blade.php), vì trang
 * nhúng cũng bị Google chấm "intrusive interstitials" y như vậy:
 *   - chỉ iPhone/iPad/iPod; máy khác thì script thoát ngay, không thêm gì vào trang;
 *   - vào từ máy tìm kiếm thì không hiện ở trang đó;
 *   - là bottom sheet hiện sau vài giây, không che kín màn hình ngay khi vào;
 *   - đóng thì 7 ngày sau mới hiện lại, bấm tải thì 30 ngày (nhớ theo từng trang nhúng).
 *
 * Giao diện nằm trong Shadow DOM: CSS của trang nhúng không làm vỡ hộp mời, và CSS của
 * hộp mời không lọt ra làm hỏng trang nhúng. Không gọi mạng gì ngoài ảnh icon.
 */
(function () {
  'use strict';

  // Lấy ngay ở đầu: document.currentScript chỉ có giá trị lúc script đang chạy lần đầu.
  var script = document.currentScript;

  // Dán trùng hai lần thì chỉ chạy một.
  if (window.__storiesAppPrompt) return;
  window.__storiesAppPrompt = true;

  var ua = navigator.userAgent || '';
  // iPadOS 13+ tự xưng là Mac: nhận ra nhờ màn cảm ứng nhiều điểm.
  var isIOS = /iPhone|iPad|iPod/.test(ua) || (/Macintosh/.test(ua) && navigator.maxTouchPoints > 1);
  if (!isIOS || navigator.standalone) return;

  // Gốc lấy theo chính địa chỉ đã tải script, nên chạy được cả ở máy dev lẫn production.
  var BASE = 'https://tunastory.com';
  try {
    if (script && script.src) BASE = new URL(script.src).origin;
  } catch (e) {}
  // Trên chính tunastory.com đã có lời mời riêng; nhúng nhầm vào thì không hiện hai lần.
  if (location.origin === BASE) return;

  var KEY = 'stories.appPrompt';
  var DAY = 864e5;
  function snoozedUntil() {
    try { return JSON.parse(localStorage.getItem(KEY) || '{}').until || 0; } catch (e) { return 0; }
  }
  function snooze(days) {
    try { localStorage.setItem(KEY, JSON.stringify({ until: Date.now() + days * DAY })); } catch (e) {}
  }
  if (snoozedUntil() > Date.now()) return;

  var refHost = (document.referrer.split('/')[2] || '').toLowerCase();
  if (/(^|\.)(google|bing|duckduckgo|yahoo|yandex|baidu|ecosia|brave)\./.test(refHost)) return;

  var data = (script && script.dataset) || {};
  var delay = parseFloat(data.delay);
  if (!(delay >= 0)) delay = 8;
  // Cùng bộ ký tự với route /app (ct của App Store chỉ nhận tối đa 40 ký tự).
  var campaign = String(data.campaign || location.hostname || '').replace(/[^A-Za-z0-9._-]/g, '').slice(0, 40);
  var href = BASE + '/app' + (campaign ? '?src=' + encodeURIComponent(campaign) : '');

  var CSS =
    '.ap{position:fixed;top:0;right:0;bottom:0;left:0;z-index:2147483647;color:#1A1614;' +
      'font:15px/1.55 -apple-system,BlinkMacSystemFont,"Segoe UI",Roboto,Helvetica,Arial,sans-serif;' +
      '-webkit-text-size-adjust:100%;text-align:left}' +
    '.bd{position:absolute;top:0;right:0;bottom:0;left:0;background:rgba(26,22,20,.38);opacity:0;transition:opacity .22s ease}' +
    '.sh{position:absolute;left:0;right:0;bottom:0;max-width:480px;margin:0 auto;background:#fff;box-sizing:border-box;' +
      'border-radius:20px 20px 0 0;padding:22px 20px calc(18px + env(safe-area-inset-bottom));' +
      'box-shadow:0 -10px 34px rgba(26,22,20,.18);transform:translateY(100%);transition:transform .26s ease}' +
    '.sh:focus{outline:none}' +
    '.ap.open .bd{opacity:1}' +
    '.ap.open .sh{transform:translateY(0)}' +
    '.x{position:absolute;top:10px;right:12px;width:36px;height:36px;padding:0;border:0;border-radius:50%;' +
      'background:transparent;color:#6E635C;font:26px/1 sans-serif;cursor:pointer}' +
    '.hd{display:flex;align-items:center;margin:0 36px 14px 0}' +
    '.hd img{width:60px;height:60px;margin-right:14px;border-radius:14px;border:1px solid rgba(26,22,20,.10);flex:none}' +
    '.hd strong{display:block;font-size:18px;line-height:1.25;font-weight:700}' +
    '.hd span{color:#6E635C;font-size:14px}' +
    'ul{margin:0 0 18px;padding-left:20px}' +
    'li{margin:0}' +
    '.get{display:block;box-sizing:border-box;width:100%;padding:12px 22px;border-radius:999px;background:#F2703A;' +
      'color:#fff;text-align:center;text-decoration:none;font-weight:650}' +
    '.later{display:block;width:100%;margin-top:8px;padding:12px;border:0;background:transparent;' +
      'color:#6E635C;font:inherit;cursor:pointer}' +
    '@media (prefers-reduced-motion:reduce){.bd,.sh{transition:none}}';

  var HTML =
    '<div class="ap" hidden>' +
      '<div class="bd" data-close></div>' +
      '<div class="sh" role="dialog" aria-modal="true" aria-labelledby="t" tabindex="-1">' +
        '<button class="x" type="button" aria-label="Close" data-close>&times;</button>' +
        '<div class="hd">' +
          '<img src="' + BASE + '/icons/icon-180.png" width="60" height="60" alt="">' +
          '<div><strong id="t">Read Stories in the app</strong><span>Free on the App Store</span></div>' +
        '</div>' +
        '<ul>' +
          '<li>Adjust font size, line spacing and page theme</li>' +
          '<li>Listen to narrated stories</li>' +
          '<li>Keep a library on your phone, no account needed</li>' +
        '</ul>' +
        '<a class="get" href="">Get the free app</a>' +
        '<button class="later" type="button" data-close>Continue in browser</button>' +
      '</div>' +
    '</div>';

  // Hộp chỉ được khoá cuộn trang khi chắc chắn nó hiện đúng chỗ (phủ khung nhìn).
  // Hỏng khi: CSP của trang nhúng chặn CSS (hộp mất position:fixed và nằm cuối trang),
  // hoặc <html> có transform/filter (fixed bám theo <html> chứ không theo màn hình).
  // Khoá cuộn trong hai trường hợp đó là treo trang của người ta, nên thà không hiện.
  function placedRight(el) {
    if (getComputedStyle(el).position !== 'fixed') return false;
    var r = el.getBoundingClientRect();
    var vh = window.innerHeight;
    return Math.abs(r.top) <= 2 && r.height > vh * 0.5 && r.height < vh * 1.5;
  }

  function show() {
    var host = document.createElement('div');
    if (!host.attachShadow) return; // iOS quá cũ (< 10): bỏ qua còn hơn vẽ lệch.
    // Gán qua CSSOM, không qua thuộc tính style: CSP chặn thuộc tính, không chặn CSSOM.
    // Inline style thắng CSS thường của trang nhúng, nên thẻ chứa không bị ẩn/đẩy lệch.
    host.style.cssText = 'all:initial';
    // FastClick (theme di động cũ) nuốt mọi cú chạm vào phần tử không có lớp này rồi bắn
    // click giả lên thẻ chứa — click đó không vào được Shadow DOM, nên hộp bấm không ăn.
    host.className = 'needsclick';
    host.setAttribute('data-stories-app-prompt', '');
    var shadow = host.attachShadow({ mode: 'open' });
    // Stylesheet dựng bằng JS không bị CSP style-src chặn như thẻ <style>; máy cũ dùng thẻ.
    if (shadow.adoptedStyleSheets && window.CSSStyleSheet && CSSStyleSheet.prototype.replaceSync) {
      var sheetCss = new CSSStyleSheet();
      sheetCss.replaceSync(CSS);
      shadow.adoptedStyleSheets = [sheetCss];
      shadow.innerHTML = HTML;
    } else {
      shadow.innerHTML = '<style>' + CSS + '</style>' + HTML;
    }
    // Gán href bằng thuộc tính chứ không nối vào chuỗi HTML: khỏi lo thoát ký tự.
    shadow.querySelector('.get').href = href;
    // Gắn vào <html> chứ không vào <body>: Turbo/pjax thay cả <body> khi chuyển trang (hộp
    // mất mà trang vẫn bị khoá cuộn), và transform/filter đặt trên <body> không ảnh hưởng tới.
    document.documentElement.appendChild(host);

    var root = shadow.querySelector('.ap');
    var sheet = shadow.querySelector('.sh');
    root.hidden = false;
    if (!placedRight(root)) {
      host.parentNode.removeChild(host);
      return;
    }

    var lastFocus = document.activeElement;
    var html = document.documentElement;
    var prevOverflow = html.style.overflow;

    function onKey(e) { if (e.key === 'Escape') close(7); }
    function close(days) {
      snooze(days);
      root.classList.remove('open');
      html.style.overflow = prevOverflow;
      document.removeEventListener('keydown', onKey);
      setTimeout(function () { if (host.parentNode) host.parentNode.removeChild(host); }, 260);
      if (lastFocus && lastFocus.focus) lastFocus.focus();
    }

    root.addEventListener('click', function (e) {
      var t = e.target;
      if (t.closest('[data-close]')) close(7);
      // Không chặn link: App Store vẫn mở. Đóng luôn hộp để quay lại Safari không gặp lại nó
      // (thẻ chứa chỉ bị gỡ sau 260ms, sau khi trình duyệt đã đi theo link).
      else if (t.closest('.get')) close(30);
    });

    html.style.overflow = 'hidden';
    // Đợi một khung hình để transition chạy từ vị trí ẩn.
    requestAnimationFrame(function () { requestAnimationFrame(function () { root.classList.add('open'); }); });
    // Focus vào khung hộp thoại chứ không vào nút: trình đọc màn hình đọc tiêu đề trước.
    sheet.focus({ preventScroll: true });
    document.addEventListener('keydown', onKey);
  }

  // Người đọc đang gõ (ô tìm kiếm, bình luận, đăng nhập...) thì không bật hộp lên giữa
  // chừng — làm thế là cướp focus, tắt bàn phím và nuốt mất chữ. Chờ họ rời ô rồi tính.
  function typing() {
    var a = document.activeElement;
    return !!a && (a.isContentEditable || /^(INPUT|TEXTAREA|SELECT)$/.test(a.tagName));
  }

  function trigger() {
    // Trang khác có thể cũng đã đặt hẹn trong lúc chờ (vd. hai tab): kiểm lại lần nữa.
    if (snoozedUntil() > Date.now()) return;
    if (typing()) {
      document.addEventListener('focusout', function () { setTimeout(trigger, 3000); }, { once: true });
      return;
    }
    show();
  }

  function schedule() {
    setTimeout(trigger, delay * 1000);
  }

  if (document.body) schedule();
  else document.addEventListener('DOMContentLoaded', schedule);
})();
