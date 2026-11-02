{{--
  Nút chia sẻ truyện. Toàn bộ là LINK THƯỜNG tới trang chia sẻ của từng mạng, KHÔNG nạp
  script/widget của Facebook, Pinterest hay X: script của họ đặt cookie theo dõi (phải
  khai trong Privacy, có nơi phải xin đồng ý), làm chậm trang, và Cloudflare cache chung
  không vướng gì.

  Luôn chia sẻ trang TRUYỆN, kể cả khi bấm ở cuối chương: người được gửi link nên vào
  từ câu mồi và nút "Read chapter 1", không rơi vào giữa truyện.

  Pinterest lấy bìa dọc (thumbnail_url) làm ảnh ghim: khổ 3:4 đúng loại ảnh Pinterest
  ưu tiên, còn ảnh chia sẻ 1200×630 khổ ngang thì bị thu nhỏ trên bảng ghim.

  Tham số: $story, $dark (nền tối ở đầu trang truyện).
--}}
@php
    $shareUrl = route('public.story', $story);
    $shareText = $story->hook ? $story->title.' — '.rtrim($story->hook, '.') : $story->title;
    $chipClass = 'chip'.(($dark ?? false) ? ' dark' : '');
    $links = [
        'Facebook' => 'https://www.facebook.com/sharer/sharer.php?'.http_build_query(['u' => $shareUrl]),
        'Pinterest' => 'https://www.pinterest.com/pin/create/button/?'.http_build_query(array_filter([
            'url' => $shareUrl,
            'media' => $story->thumbnail_url,
            'description' => Str::limit($shareText, 480),
        ])),
        'X' => 'https://twitter.com/intent/tweet?'.http_build_query(['url' => $shareUrl, 'text' => Str::limit($shareText, 200)]),
        'Reddit' => 'https://www.reddit.com/submit?'.http_build_query(['url' => $shareUrl, 'title' => $story->title]),
    ];
@endphp
<div class="share{{ ($dark ?? false) ? ' dark' : '' }}">
  <span class="share-label">Share</span>
  <ul class="chips">
    @foreach ($links as $name => $href)
      <li><a class="{{ $chipClass }}" href="{{ $href }}" target="_blank" rel="noopener nofollow">{{ $name }}</a></li>
    @endforeach
    {{-- Điện thoại: mở bảng chia sẻ của máy (Messenger, WhatsApp, Zalo...). Máy tính không
         có thì chép link. Nhãn đổi bằng JS, nên không có JS thì nút vẫn mang nghĩa chép link. --}}
    <li><button class="{{ $chipClass }}" type="button" data-share-url="{{ $shareUrl }}" data-share-title="{{ $story->title }}">Copy link</button></li>
  </ul>
</div>
@once
<style>
  .share{display:flex;align-items:center;gap:10px 12px;flex-wrap:wrap;margin:28px 0 0}
  .share-label{font-size:13px;font-weight:700;letter-spacing:.08em;text-transform:uppercase;color:var(--muted)}
  .share .chips{margin:0}
  .share.dark{margin:18px 0 0}
  .share.dark .share-label{color:rgba(255,255,255,.7)}
  .share button.chip{font:inherit;font-size:14px;font-weight:560;cursor:pointer}
  .share.dark button.chip{font-size:13px}
</style>
<script>
(function () {
  document.querySelectorAll('[data-share-url]').forEach(function (btn) {
    if (navigator.share) btn.textContent = 'More…';
    btn.addEventListener('click', function () {
      var url = btn.getAttribute('data-share-url');
      if (navigator.share) {
        navigator.share({ title: btn.getAttribute('data-share-title') || document.title, url: url }).catch(function () {});
        return;
      }
      var done = function () {
        btn.textContent = 'Link copied';
        setTimeout(function () { btn.textContent = 'Copy link'; }, 2000);
      };
      if (navigator.clipboard && navigator.clipboard.writeText) {
        navigator.clipboard.writeText(url).then(done, function () { window.prompt('Copy this link:', url); });
      } else {
        window.prompt('Copy this link:', url);
      }
    });
  });
})();
</script>
@endonce
