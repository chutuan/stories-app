/*!
 * Stories — mời người đọc trên một trang web KHÁC tunastory.com mở truyện trong app.
 *
 * Nhúng (dán trước </body>, hoặc trong <head> cũng được vì có async):
 *   <script src="https://tunastory.com/embed/app-prompt.js" async></script>
 *
 * Tuỳ chọn trên chính thẻ <script>:
 *   data-delay="8"          số giây chờ trước khi hiện, mặc định 8
 *   data-campaign="blog-a"  nhãn nguồn gắn vào link tải (?src=), mặc định là tên miền trang nhúng
 *
 * Thiết kế (Tuấn duyệt 25/11/2026, "kiểu A"): hộp mời trước đây (icon app to + tên app)
 * trông như quảng cáo nên ít người cài. Giờ hộp nói bằng giọng của CHÍNH trang đang đọc:
 * icon + tên trang ở đầu, rồi thẻ truyện người ta đang đọc (bìa + tên, khớp bằng
 * /embed/story), rồi badge chính thức của cửa hàng đúng theo máy ("Download on the App
 * Store" / "GET IT ON Google Play" — Tuấn gửi ảnh mẫu, muốn đúng hình đó). Không ghi tên
 * app (Tuấn chọn). Trang không khớp được truyện nào trong app thì mời chung, KHÔNG hứa đọc
 * tiếp truyện đó.
 *
 * Chọn cửa hàng theo máy:
 *   - iPhone / iPad / iPod (kể cả iPad ở chế độ "máy tính" và trình duyệt trong app
 *     Facebook, Instagram, TikTok…) -> App Store;
 *   - Android -> Google Play, chỉ khi server bật config app.android_public (route
 *     /embed/app-prompt.js điền vào chỗ đánh dấu android_public bên dưới). HarmonyOS
 *     (không có Google Play) thì không mời;
 *   - điện thoại / máy tính bảng không nhận ra hệ điều hành (vd. Android bật "trang cho
 *     máy tính") -> hiện CẢ HAI nút (Tuấn dặn); khi chưa bật Android thì thôi không mời,
 *     vì máy đó nhiều khả năng là Android;
 *   - máy tính -> không mời (không cài app từ máy tính được).
 *
 * Cùng quy tắc với lời mời trên tunastory.com (partials/app-prompt.blade.php), vì trang
 * nhúng cũng bị Google chấm "intrusive interstitials" y như vậy:
 *   - vào từ máy tìm kiếm thì không hiện ở trang đó;
 *   - là bottom sheet hiện sau vài giây, không che kín màn hình ngay khi vào;
 *   - đóng hay bấm tải thì 1 giờ sau mới hiện lại (nhớ theo từng trang nhúng; Tuấn đổi từ
 *     1 ngày xuống 1 giờ, 03/11/2026 — bản trên tunastory.com vẫn 1 ngày);
 *     không bấm gì mà rời trang thì chưa tính, trang sau vẫn có thể hiện.
 *
 * Giao diện nằm trong Shadow DOM: CSS của trang nhúng không làm vỡ hộp mời, và CSS của
 * hộp mời không lọt ra làm hỏng trang nhúng. Gọi mạng: một yêu cầu /embed/story (tựa bài
 * của trang, không cookie), ảnh bìa truyện (hay icon app) và icon của chính trang nhúng.
 */
(function () {
  'use strict';

  // Lấy ngay ở đầu: document.currentScript chỉ có giá trị lúc script đang chạy lần đầu.
  var script = document.currentScript;

  // Dán trùng hai lần thì chỉ chạy một.
  if (window.__storiesAppPrompt) return;
  window.__storiesAppPrompt = true;

  // Server thay "false" ngay trước dấu android_public bằng "true" khi app Android đã lên
  // Google Play công khai. Đừng đổi chữ ở chỗ này: route tìm đúng chuỗi đó để thay.
  var ANDROID = false /*android_public*/;

  // ---------- Máy nào, cửa hàng nào ----------
  var ua = navigator.userAgent || '';
  var touch = navigator.maxTouchPoints || 0;
  var uaData = navigator.userAgentData || {};
  // iPadOS 13+ tự xưng là Mac: nhận ra nhờ màn cảm ứng nhiều điểm.
  var isIOS = /iPhone|iPad|iPod/.test(ua) || (/Macintosh/.test(ua) && touch > 1);
  // Chrome bật "trang cho máy tính" vẫn khai platform thật qua User-Agent Client Hints.
  var isAndroid = /Android/.test(ua) || /Android/i.test(String(uaData.platform || ''));
  var noPlay = /OpenHarmony|ArkWeb|HarmonyOS/.test(ua);
  var coarse = !!(window.matchMedia && window.matchMedia('(pointer: coarse)').matches);
  var shortSide = Math.min(screen.width || 0, screen.height || 0);
  // Cảm ứng + con trỏ thô + màn không quá lớn = điện thoại / máy tính bảng.
  var handheld = touch > 0 && coarse && shortSide > 0 && shortSide <= 1100 &&
    !/Windows|CrOS/.test(ua);

  var os = '';
  if (isIOS) os = 'ios';
  else if (isAndroid) os = ANDROID && !noPlay ? 'android' : '';
  else if (handheld && ANDROID && !noPlay) os = 'both';
  if (!os) return;

  // Đang chạy như app đã cài ra màn hình chính (PWA của trang nhúng): không mời.
  var standalone = navigator.standalone ||
    (window.matchMedia && window.matchMedia('(display-mode: standalone)').matches);
  if (standalone) return;

  // Gốc lấy theo chính địa chỉ đã tải script, nên chạy được cả ở máy dev lẫn production.
  var BASE = 'https://tunastory.com';
  try {
    if (script && script.src) BASE = new URL(script.src).origin;
  } catch (e) {}
  // Trên chính tunastory.com đã có lời mời riêng; nhúng nhầm vào thì không hiện hai lần.
  if (location.origin === BASE) return;

  var KEY = 'stories.appPrompt';
  var HOUR = 36e5;
  function snoozedUntil() {
    try { return JSON.parse(localStorage.getItem(KEY) || '{}').until || 0; } catch (e) { return 0; }
  }
  function snooze(hours) {
    try { localStorage.setItem(KEY, JSON.stringify({ until: Date.now() + hours * HOUR })); } catch (e) {}
  }
  if (snoozedUntil() > Date.now()) return;

  var refHost = (document.referrer.split('/')[2] || '').toLowerCase();
  if (/(^|\.)(google|bing|duckduckgo|yahoo|yandex|baidu|ecosia|brave)\./.test(refHost)) return;

  var data = (script && script.dataset) || {};
  var delay = parseFloat(data.delay);
  if (!(delay >= 0)) delay = 8;
  // Cùng bộ ký tự với route /app (ct của App Store chỉ nhận tối đa 40 ký tự).
  var campaign = String(data.campaign || location.hostname || '').replace(/[^A-Za-z0-9._-]/g, '').slice(0, 40);

  // ---------- Trang đang đọc: tên site, icon, màu, tựa bài, chương ----------
  function meta(sel) {
    var m = document.querySelector(sel);
    return m && m.content ? String(m.content).trim() : '';
  }
  function clean(s) { return String(s || '').replace(/\s+/g, ' ').trim(); }
  function prettyHost(host) {
    var first = host.replace(/^www\./, '').split('.')[0] || host;
    return first.charAt(0).toUpperCase() + first.slice(1);
  }
  function httpUrl(href) {
    try {
      var u = new URL(href, location.href);
      return /^https?:$/.test(u.protocol) ? u.href : '';
    } catch (e) { return ''; }
  }

  var siteName = clean(meta('meta[property="og:site_name"]') || meta('meta[name="application-name"]') ||
    meta('meta[name="apple-mobile-web-app-title"]')) || prettyHost(location.hostname);
  if (siteName.length > 40) siteName = siteName.slice(0, 39) + '…';

  var iconLink = document.querySelector('link[rel~="apple-touch-icon"]') ||
    document.querySelector('link[rel~="icon"]');
  var siteIcon = iconLink ? httpUrl(iconLink.getAttribute('href')) : '';

  // Nút mang màu của chính trang (theme-color) nếu đủ tối cho chữ trắng; không thì gần đen.
  var color = '#1A1614';
  var theme = meta('meta[name="theme-color"]');
  if (/^#([0-9a-f]{3}|[0-9a-f]{6})$/i.test(theme)) {
    var hex = theme.length === 4 ? theme.replace(/[0-9a-f]/gi, '$&$&').slice(1) : theme.slice(1);
    var r = parseInt(hex.slice(0, 2), 16), g = parseInt(hex.slice(2, 4), 16), b = parseInt(hex.slice(4, 6), 16);
    if ((0.299 * r + 0.587 * g + 0.114 * b) / 255 < 0.55) color = '#' + hex;
  }

  var h1 = document.querySelector('article h1, main h1, h1');
  var titles = [];
  [h1 && h1.textContent, meta('meta[property="og:title"]'), document.title].forEach(function (t) {
    t = clean(t).slice(0, 200);
    if (t && titles.indexOf(t) < 0) titles.push(t);
  });
  var chapter = 0;
  (titles.join(' ') + ' ' + decodeURIComponent(location.pathname).replace(/[-_/]+/g, ' '))
    .replace(/\b(?:chapter|chap|ch|part|pt|episode|ep)\.?\s*(\d{1,3})\b/i, function (_, n) {
      chapter = parseInt(n, 10);
    });

  // Hỏi trước, trong lúc chờ hết thời gian trễ. Lỗi mạng hay không khớp -> null (mời chung).
  var storyReq = null;
  if (titles.length && window.fetch && window.Promise) {
    var qs = titles.map(function (t) { return 't[]=' + encodeURIComponent(t); }).join('&');
    storyReq = fetch(BASE + '/embed/story?' + qs, { credentials: 'omit' })
      .then(function (res) { return res.ok ? res.json() : null; })
      .then(function (d) { return d && d.story && d.story.id ? d.story : null; })
      .catch(function () { return null; });
  }

  function storeHref(which, story) {
    return BASE + '/app?os=' + which + (campaign ? '&src=' + encodeURIComponent(campaign) : '') +
      (story ? '&story=' + story.id : '');
  }

  var CSS =
    '.ap{position:fixed;top:0;right:0;bottom:0;left:0;z-index:2147483647;color:#1A1614;' +
      'font:15px/1.5 -apple-system,BlinkMacSystemFont,"Segoe UI",Roboto,Helvetica,Arial,sans-serif;' +
      '-webkit-text-size-adjust:100%;text-align:left;--c:#1A1614}' +
    '.bd{position:absolute;top:0;right:0;bottom:0;left:0;background:rgba(26,22,20,.38);opacity:0;transition:opacity .22s ease}' +
    '.sh{position:absolute;left:0;right:0;bottom:0;max-width:480px;margin:0 auto;background:#fff;box-sizing:border-box;' +
      'border-radius:20px 20px 0 0;padding:18px 18px calc(12px + env(safe-area-inset-bottom));' +
      'box-shadow:0 -10px 34px rgba(26,22,20,.18);transform:translateY(100%);transition:transform .26s ease}' +
    '.sh:focus{outline:none}' +
    '.ap.open .bd{opacity:1}' +
    '.ap.open .sh{transform:translateY(0)}' +
    '.x{position:absolute;top:8px;right:10px;width:36px;height:36px;padding:0;border:0;border-radius:50%;' +
      'background:transparent;color:#6E635C;font:26px/1 sans-serif;cursor:pointer}' +
    '.site{display:flex;align-items:center;margin:0 40px 12px 0;font-size:13.5px;font-weight:600;color:#4A433E;' +
      'white-space:nowrap;overflow:hidden;text-overflow:ellipsis}' +
    '.fv,.fl{width:22px;height:22px;margin-right:8px;border-radius:6px;flex:none}' +
    '.fv{object-fit:cover}' +
    '.fl{background:var(--c);color:#fff;font:700 12px/22px sans-serif;text-align:center}' +
    '.card{display:flex;align-items:center;padding:10px;border:1px solid rgba(26,22,20,.09);border-radius:14px;background:#FAF7F4}' +
    '.cv{width:54px;height:76px;margin-right:12px;border-radius:7px;object-fit:cover;flex:none;background:#E9E2DB}' +
    '.cv.ic{height:54px;border-radius:13px}' +
    '.lb{display:block;font-size:12.5px;color:#6E635C}' +
    '.tt{display:-webkit-box;-webkit-box-orient:vertical;-webkit-line-clamp:3;overflow:hidden;' +
      'font-size:16px;line-height:1.3;font-weight:700;color:#1A1614}' +
    '.tx{margin:12px 2px 14px;font-size:14.5px;color:#3B3632}' +
    '.acts{display:flex;justify-content:center;align-items:center}' +
    '.st{display:block;line-height:0;border-radius:8px;text-decoration:none}' +
    '.st+.st{margin-left:10px}' +
    '.st img{display:block}' +
    // Ảnh badge không tải được (CSP của trang nhúng chặn ảnh ngoài...) thì hiện chữ thay.
    '.st .alt{display:block;line-height:1.25;background:#000;color:#fff;border-radius:8px;' +
      'padding:13px 16px;font-weight:700;font-size:15px}' +
    '.fr{text-align:center;font-size:12.5px;color:#6E635C;margin-top:8px}' +
    '.later{display:block;width:100%;margin-top:2px;padding:10px;border:0;background:transparent;' +
      'color:#6E635C;font:inherit;font-size:14.5px;cursor:pointer}' +
    '@media (prefers-reduced-motion:reduce){.bd,.sh{transition:none}}';

  // Khung tĩnh: mọi chữ lấy từ trang nhúng hay từ API được gán bằng textContent/thuộc
  // tính sau đó, KHÔNG nối vào chuỗi HTML (tựa bài là dữ liệu của người khác).
  var HTML =
    '<div class="ap" hidden>' +
      '<div class="bd" data-close></div>' +
      '<div class="sh" role="dialog" aria-modal="true" aria-labelledby="tt" tabindex="-1">' +
        '<button class="x" type="button" aria-label="Close" data-close>&times;</button>' +
        '<div class="site"><span class="fl" aria-hidden="true"></span><span class="sn"></span></div>' +
        '<div class="card">' +
          '<img class="cv" alt="" width="54" height="76">' +
          '<div><span class="lb"></span><strong class="tt" id="tt"></strong></div>' +
        '</div>' +
        '<p class="tx"></p>' +
        '<div class="acts"></div>' +
        '<div class="fr"></div>' +
        '<button class="later" type="button" data-close>Not now</button>' +
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

  function fill(shadow, story) {
    var $ = function (s) { return shadow.querySelector(s); };
    $('.ap').style.setProperty('--c', color);

    // Đầu hộp: icon + tên của CHÍNH trang đang đọc. Không có icon (hay icon lỗi) thì chữ cái đầu.
    $('.sn').textContent = siteName;
    var letter = $('.fl');
    letter.textContent = siteName.charAt(0).toUpperCase();
    if (siteIcon) {
      var fv = document.createElement('img');
      fv.className = 'fv';
      fv.alt = '';
      fv.onerror = function () { if (fv.parentNode) fv.parentNode.replaceChild(letter, fv); };
      fv.src = siteIcon;
      letter.parentNode.replaceChild(fv, letter);
    }

    // Bìa (hay icon app) thiếu hoặc tải lỗi thì bỏ hẳn, không để khung ảnh vỡ.
    var cover = $('.cv');
    var dropCover = function () { if (cover.parentNode) cover.parentNode.removeChild(cover); };
    cover.onerror = dropCover;
    if (story) {
      if (story.cover && /^https?:\/\//.test(story.cover)) cover.src = story.cover;
      else dropCover();
      $('.lb').textContent = chapter ? "You're on chapter " + chapter : 'Continue reading';
      $('.tt').textContent = story.title;
      // Nghe audio là điểm app có mà blog không có (Tuấn dặn thêm 25/11).
      $('.tx').textContent = (chapter && story.chapters > chapter
        ? 'The next chapters are waiting in the app. '
        : 'Pick up this story in the app. ') +
        'Listen as audio or read in bigger text, and it keeps your place.';
    } else {
      // Không khớp được truyện: mời chung, không hứa có truyện này trong app.
      cover.className = 'cv ic';
      cover.width = cover.height = 54;
      cover.src = BASE + '/icons/icon-180.png';
      $('.lb').textContent = 'On your phone';
      $('.tt').textContent = 'New short stories every day';
      $('.tx').textContent = 'Read them in the app, or listen as audio. Bigger text, night mode, and it keeps your place.';
    }

    // Badge chính thức của cửa hàng theo máy (cùng file với tunastory.com); máy không rõ hệ
    // điều hành thì hai badge cạnh nhau, nhỏ hơn một chút cho vừa màn điện thoại.
    var acts = $('.acts');
    var height = os === 'both' ? 46 : 54;
    function badge(which, file, ratio, alt) {
      var a = document.createElement('a');
      a.className = 'st';
      a.href = storeHref(which, story);
      var img = document.createElement('img');
      img.alt = alt;
      img.height = height;
      img.width = Math.round(height * ratio);
      img.onerror = function () {
        var t = document.createElement('span');
        t.className = 'alt';
        t.textContent = alt;
        if (img.parentNode) a.replaceChild(t, img);
      };
      img.src = BASE + '/images/' + file;
      a.appendChild(img);
      acts.appendChild(a);
    }
    if (os !== 'android') badge('ios', 'app-store-badge.svg', 119.66407 / 40, 'Download on the App Store');
    if (os !== 'ios') badge('android', 'google-play-badge.png', 564 / 168, 'Get it on Google Play');
    $('.fr').textContent = 'Free to download';
  }

  function show(story) {
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
    fill(shadow, story);
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

    function onKey(e) { if (e.key === 'Escape') close(1); }
    function close(hours) {
      snooze(hours);
      root.classList.remove('open');
      html.style.overflow = prevOverflow;
      document.removeEventListener('keydown', onKey);
      setTimeout(function () { if (host.parentNode) host.parentNode.removeChild(host); }, 260);
      if (lastFocus && lastFocus.focus) lastFocus.focus();
    }

    root.addEventListener('click', function (e) {
      var t = e.target;
      if (t.closest('[data-close]')) close(1);
      // Không chặn link: cửa hàng vẫn mở. Đóng luôn hộp để quay lại trình duyệt không gặp lại nó
      // (thẻ chứa chỉ bị gỡ sau 260ms, sau khi trình duyệt đã đi theo link).
      else if (t.closest('.st')) close(1);
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

  var shown = false;
  function trigger() {
    if (shown) return;
    // Trang khác có thể cũng đã đặt hẹn trong lúc chờ (vd. hai tab): kiểm lại lần nữa.
    if (snoozedUntil() > Date.now()) return;
    if (typing()) {
      document.addEventListener('focusout', function () { setTimeout(trigger, 3000); }, { once: true });
      return;
    }
    if (!storyReq) { shown = true; show(null); return; }
    // API chậm quá 2,5 giây sau thời gian trễ thì mời chung, không bắt người đọc chờ.
    var done = false;
    var go = function (story) {
      if (done) return;
      done = true;
      shown = true;
      show(story);
    };
    storyReq.then(go);
    setTimeout(function () { go(null); }, 2500);
  }

  function schedule() {
    setTimeout(trigger, delay * 1000);
  }

  if (document.body) schedule();
  else document.addEventListener('DOMContentLoaded', schedule);
})();
