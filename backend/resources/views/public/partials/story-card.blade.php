{{--
  Một ô truyện trong lưới.  @include('public.partials.story-card', ['story' => $s])

  `alt` của ảnh bìa cố ý nhắc lại tên truyện: 10 trên 14 truyện dùng bìa AI sinh,
  không có nội dung mô tả được, nên alt hữu ích nhất là cho biết ảnh này thuộc
  truyện nào — vừa đúng cho trình đọc màn hình, vừa là tín hiệu SEO thật thay vì
  nhồi từ khoá.
--}}
<a class="scard" href="{{ route('public.story', $story) }}">
  @if ($story->thumbnail_url)
    <img src="{{ $story->thumbnail_url }}" alt="Cover art for {{ $story->title }}" loading="lazy" width="300" height="450">
  @else
    <span class="noart">{{ $story->title }}</span>
  @endif
  <h3>{{ $story->title }}</h3>
  <p class="meta">
    {{ $story->chapters_count }} {{ Str::plural('chapter', $story->chapters_count) }}
    @if ($story->author) · {{ $story->author }} @endif
  </p>
</a>
