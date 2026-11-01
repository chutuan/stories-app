{{--
  "Pick up where you left off" — bottom sheet mời đọc tiếp truyện đang dở, giống app
  (mobile/src/components/app-open-prompts.tsx).

  Khi nào hiện (tương đương "mở app" và "quay lại app sau ≥ 30 phút" của app):
    - đầu một lượt ghé mới: lần hoạt động cuối trên site cách đây ≥ 30 phút
      (stories.lastSeen), hoặc quay lại tab đang mở sau ≥ 30 phút;
    - CHỈ ở các trang tương đương tab của app: trang chủ, Browse, thể loại, tìm kiếm.
      Trang truyện đã có nút "Continue · Chapter N"; trang chương thì người ta đang đọc
      rồi (vào thẳng trang chương là bỏ luôn lời mời của lượt ghé đó);
    - trang vào thẳng từ máy tìm kiếm thì để dành sang trang sau, cùng lý do với
      app-prompt (Google phạt popup che nội dung ngay sau khi bấm từ kết quả tìm kiếm);
    - mỗi lượt ghé tối đa một lần; đã hiện ở trang này thì app-prompt nhường trang sau.
  Truyện được mời: truyện gần nhất trong lịch sử mà chưa đọc tới chương cuối.

  Mọi thứ nằm ở localStorage vì trang được Cloudflare cache chung cho mọi người. Dựng
  nội dung bằng textContent, KHÔNG innerHTML: tên truyện đến từ localStorage.
--}}
@php
    // Trang tương đương tab của app: chỉ ở đây mới mời.
    $continueEligible = request()->routeIs('public.home', 'public.browse', 'public.category', 'public.search');
    $continueReading = request()->routeIs('public.chapter');
@endphp
<div id="continue-prompt" class="cp" hidden>
  <div class="cp-backdrop" data-cp-close></div>
  <div class="cp-sheet" role="dialog" aria-modal="true" aria-labelledby="cp-title" tabindex="-1">
    <span class="cp-grip" aria-hidden="true"></span>
    <p class="cp-eyebrow">Pick up where you left off</p>
    <div class="cp-story">
      <img class="cp-cover" width="72" height="108" alt="">
      <div class="cp-info">
        <strong id="cp-title" class="cp-name"></strong>
        <span class="cp-meta"></span>
        <em class="cp-chapter"></em>
        <span class="cp-bar"><i></i></span>
      </div>
    </div>
    <a class="btn cp-go" href="#"></a>
    <button class="cp-later" type="button" data-cp-close>Not now</button>
  </div>
</div>
<style>
  .cp{position:fixed;inset:0;z-index:1000}
  .cp[hidden]{display:none}
  .cp-backdrop{position:absolute;inset:0;background:rgba(16,12,10,.45);opacity:0;transition:opacity .22s ease}
  .cp-sheet{position:absolute;left:0;right:0;bottom:0;max-width:480px;margin:0 auto;background:var(--surface);
            border-radius:22px 22px 0 0;padding:10px 20px calc(16px + env(safe-area-inset-bottom));
            box-shadow:0 -10px 34px rgba(16,12,10,.2),inset 0 1px 0 var(--border-strong);transform:translateY(100%);transition:transform .26s ease}
  .cp-sheet:focus{outline:none}
  .cp.open .cp-backdrop{opacity:1}
  .cp.open .cp-sheet{transform:translateY(0)}
  .cp-grip{display:block;width:40px;height:5px;border-radius:3px;background:var(--border-strong);margin:0 auto 16px}
  .cp-eyebrow{margin:0 0 12px;font-size:12.5px;font-weight:800;letter-spacing:.11em;text-transform:uppercase;color:var(--accent-ink)}
  .cp-story{display:flex;gap:14px;align-items:flex-start;margin-bottom:18px}
  .cp-cover{width:72px;height:108px;flex:none;object-fit:cover;border-radius:10px;background:var(--surface-2);
            box-shadow:var(--shadow)}
  .cp-info{flex:1;min-width:0;display:flex;flex-direction:column;gap:4px}
  .cp-name{font-size:17px;line-height:1.3;color:var(--text);
           display:-webkit-box;-webkit-line-clamp:3;-webkit-box-orient:vertical;overflow:hidden}
  .cp-meta{font-size:14px;font-weight:600;color:var(--text-2)}
  .cp-chapter{font-size:14px;color:var(--muted);white-space:nowrap;overflow:hidden;text-overflow:ellipsis}
  .cp-chapter:empty{display:none}
  .cp-bar{display:block;height:4px;border-radius:2px;background:var(--accent-soft);margin-top:6px;overflow:hidden}
  .cp-bar i{display:block;height:100%;background:var(--btn);border-radius:2px}
  .cp-go{display:block;text-align:center;width:100%;box-sizing:border-box}
  .cp-later{display:block;width:100%;margin-top:6px;padding:12px;border:0;background:transparent;
            color:var(--muted);font-size:15px;font-weight:600;cursor:pointer}
  @media (prefers-reduced-motion:reduce){.cp-backdrop,.cp-sheet{transition:none}}
</style>
<script>
(function () {
  var SEEN = 'stories.lastSeen', DUE = 'stories.continueDue', GAP = 30 * 60 * 1000;
  var ELIGIBLE = @json($continueEligible), READING = @json($continueReading);
  function get(k) { try { return localStorage.getItem(k); } catch (e) { return null; } }
  function put(k, v) { try { v === null ? localStorage.removeItem(k) : localStorage.setItem(k, v); } catch (e) {} }

  // Lượt ghé mới: đánh dấu "nợ" một lời mời, trả ở trang hợp lệ đầu tiên của lượt ghé.
  if (Date.now() - (Number(get(SEEN)) || 0) >= GAP) put(DUE, '1');
  put(SEEN, String(Date.now()));

  var root = document.getElementById('continue-prompt');
  var host = (document.referrer.split('/')[2] || '').toLowerCase();
  var fromSearch = /(^|\.)(google|bing|duckduckgo|yahoo|yandex|baidu|ecosia|brave)\./.test(host);
  var lastFocus = null;

  // Truyện gần nhất chưa đọc tới chương cuối (`max` = chương xa nhất từng mở; bản ghi
  // cũ chưa có `max` thì lấy chương đang đọc).
  function pick() {
    var list = window.StoriesHistory ? StoriesHistory.all() : [];
    for (var i = 0; i < list.length; i++) {
      var h = list[i];
      if (!h || !h.url || !/^\/story\/[^/]+\/chapter\/\d+$/.test(h.url) || !h.ch) continue;
      if (!StoriesHistory.finished(h)) return h;
    }
    return null;
  }

  function fill(h) {
    var img = root.querySelector('.cp-cover');
    if (h.cover && /^https?:\/\//.test(h.cover)) img.src = h.cover; else img.hidden = true;
    root.querySelector('.cp-name').textContent = h.title || '';
    root.querySelector('.cp-meta').textContent = 'Chapter ' + h.ch + (h.total ? ' of ' + h.total : '');
    // Nhiều tên chương tự ghi "Chapter 2: …" — bỏ tiền tố, dòng trên đã ghi số chương.
    root.querySelector('.cp-chapter').textContent = String(h.chTitle || '').replace(/^chapter\s+\d+\s*[:.\-–—]\s*/i, '');
    root.querySelector('.cp-bar i').style.width = (h.total ? Math.min(100, Math.round(h.ch / h.total * 100)) : 0) + '%';
    var go = root.querySelector('.cp-go');
    go.href = h.url;
    go.textContent = 'Continue · Chapter ' + h.ch;
  }

  function onKey(e) { if (e.key === 'Escape') close(); }
  function close() {
    root.classList.remove('open');
    document.documentElement.style.overflow = '';
    document.removeEventListener('keydown', onKey);
    setTimeout(function () { root.hidden = true; }, 260);
    if (lastFocus && lastFocus.focus) lastFocus.focus();
  }
  function open() {
    lastFocus = document.activeElement;
    root.hidden = false;
    document.documentElement.style.overflow = 'hidden';
    // Đọc offsetHeight để trình duyệt dựng vị trí ẩn trước, rồi mới thêm .open cho
    // transition chạy. Không đợi requestAnimationFrame: ở tab mở nền rAF đứng tới khi
    // người dùng chuyển sang, trong lúc đó trang đã bị khoá cuộn mà sheet chưa lên.
    void root.offsetHeight;
    root.classList.add('open');
    root.querySelector('.cp-sheet').focus({ preventScroll: true });
    document.addEventListener('keydown', onKey);
  }
  root.addEventListener('click', function (e) {
    if (e.target.closest('[data-cp-close]')) close();
  });

  function typing() {
    var a = document.activeElement;
    return !!a && (a.isContentEditable || /^(INPUT|TEXTAREA|SELECT)$/.test(a.tagName));
  }

  function maybeShow(resumed) {
    if (!get(DUE)) return;
    if (READING) { put(DUE, null); return; }
    if (!ELIGIBLE || (fromSearch && !resumed)) return;
    var h = pick();
    put(DUE, null);
    if (!h) return;
    fill(h);
    // Báo app-prompt nhường trang này.
    window.StoriesSheetShown = true;
    (function trigger() {
      // Đang gõ ô tìm kiếm thì đợi rời ô, không cướp focus giữa chừng.
      if (typing()) { document.addEventListener('focusout', function () { setTimeout(trigger, 1500); }, { once: true }); return; }
      open();
    })();
  }

  // Đợi trang vẽ xong một nhịp, như app đợi màn hình ổn định rồi mới mời.
  setTimeout(function () { maybeShow(false); }, 900);

  // Để tab mở rồi quay lại sau ≥ 30 phút cũng là một lượt ghé mới.
  document.addEventListener('visibilitychange', function () {
    if (document.visibilityState === 'hidden') { put(SEEN, String(Date.now())); return; }
    if (Date.now() - (Number(get(SEEN)) || 0) >= GAP) { put(DUE, '1'); maybeShow(true); }
    put(SEEN, String(Date.now()));
  });
})();
</script>
