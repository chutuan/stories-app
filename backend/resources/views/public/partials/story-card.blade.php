{{--
  Một ô truyện trong lưới.  @include('public.partials.story-card', ['story' => $s])

  `alt` của ảnh bìa cố ý nhắc lại tên truyện: 10 trên 14 truyện dùng bìa AI sinh,
  không có nội dung mô tả được, nên alt hữu ích nhất là cho biết ảnh này thuộc
  truyện nào — vừa đúng cho trình đọc màn hình, vừa là tín hiệu SEO thật thay vì
  nhồi từ khoá.
--}}
<a class="scard" href="{{ route('public.story', $story) }}">
  @if ($story->thumbnail_url)
    {{-- `sizes` bám theo breakpoint của .grid trong layout: 5 cột >1400px,
         4 cột mặc định, 3 cột <1000px, 2 cột <600px. --}}
    @include('public.partials.cover', [
        'story' => $story,
        'sizes' => '(max-width:600px) 45vw, (max-width:1000px) 30vw, (max-width:1400px) 23vw, 250px',
        'w' => 300, 'h' => 450,
    ])
  @else
    <span class="noart">{{ $story->title }}</span>
  @endif
  <h3>{{ $story->title }}</h3>
  <p class="meta">
    {{ $story->chapters_count }} {{ Str::plural('chapter', $story->chapters_count) }}
    @if ($story->author) · {{ $story->author }} @endif
    @include('public.partials.rating', ['compact' => true])
  </p>
</a>
