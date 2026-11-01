{{--
  Một dòng truyện gọn: bìa nhỏ + tên + thể loại. Dùng cho bảng xếp hạng ở trang chủ
  (có số thứ tự) và "You may also like" ở trang truyện (không số).

      @include('public.partials.story-row', ['story' => $s, 'rank' => 1])

  Bìa nhỏ lấy bản WebP 320w (Story::coverSmallUrl) chứ không qua <picture>: ô chỉ
  48–56px, một bản là đủ nét cả trên màn Retina.
--}}
<li>
  <a href="{{ route('public.story', $story) }}">
    @isset($rank)
      <span class="rank-n" aria-label="Rank {{ $rank }}">{{ $rank }}</span>
    @endisset
    @if ($src = $story->coverSmallUrl())
      <img src="{{ $src }}" width="56" height="84" loading="lazy" decoding="async" alt="Cover art for {{ $story->title }}">
    @else
      <span class="noart"></span>
    @endif
    <span>
      <strong>{{ $story->title }}</strong>
      <span class="meta">
        @if ($story->categories->isNotEmpty()) {{ $story->categories->first()->name }} · @endif
        {{ $story->chapters_count }} {{ Str::plural('chapter', $story->chapters_count) }}
      </span>
    </span>
  </a>
</li>
