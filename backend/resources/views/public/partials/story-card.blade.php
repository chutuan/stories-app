{{--
  Một ô truyện trong lưới hoặc hàng cuộn.  @include('public.partials.story-card', ['story' => $s])

  `alt` của ảnh bìa cố ý nhắc lại tên truyện: 10 trên 14 truyện dùng bìa AI sinh,
  không có nội dung mô tả được, nên alt hữu ích nhất là cho biết ảnh này thuộc
  truyện nào — vừa đúng cho trình đọc màn hình, vừa là tín hiệu SEO thật thay vì
  nhồi từ khoá.

  Dòng phụ là THỂ LOẠI chứ không phải tác giả: mọi truyện đều mang cùng một bút danh,
  nên tên tác giả không giúp ai chọn truyện; thể loại thì có.
  Nhãn trên bìa chỉ hiện cho truyện "Ongoing" — gần như cả kho đã hoàn thành, gắn
  "Completed" lên mọi bìa thì nhãn chẳng còn nói lên điều gì.
--}}
<a class="scard" href="{{ route('public.story', $story) }}">
  <span class="scover">
    @if ($story->thumbnail_url)
      {{-- `sizes` bám theo breakpoint của .grid / .shelf trong layout: 6 cột >1400px,
           4 cột mặc định, 3 cột <1000px, 2 cột (lưới) hoặc 40% (hàng cuộn) <600px. --}}
      @include('public.partials.cover', [
          'story' => $story,
          'sizes' => '(max-width:600px) 45vw, (max-width:1000px) 30vw, (max-width:1400px) 23vw, 200px',
          'w' => 300, 'h' => 450,
      ])
    @else
      <span class="noart">{{ $story->title }}</span>
    @endif
    @if ($story->status === 'ongoing')
      <span class="badge">Ongoing</span>
    @endif
  </span>
  <h3>{{ $story->title }}</h3>
  <p class="meta">
    @if ($story->categories->isNotEmpty())
      <span class="genre">{{ $story->categories->first()->name }}</span> ·
    @endif
    {{ $story->chapters_count }} {{ Str::plural('chapter', $story->chapters_count) }}
    @include('public.partials.rating', ['compact' => true])
  </p>
</a>
