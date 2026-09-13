{{--
  Ảnh bìa một truyện.

  @include('public.partials.cover', ['story' => $s, 'sizes' => '250px', 'w' => 300, 'h' => 450])

  Ảnh gốc nằm trong <img> làm bản dự phòng, bản WebP thu nhỏ nằm trong <source>.
  Trình duyệt không hiểu WebP (hoặc bản dẫn xuất chưa sinh) thì rơi về ảnh gốc —
  nên trang không bao giờ vỡ vì thiếu file dẫn xuất.

  `sizes` phải khớp với CSS thật, nếu không trình duyệt chọn sai bản: khai rộng
  hơn thực tế thì nó tải bản 640 cho một ô 250px và mất luôn ý nghĩa của việc này.
--}}
@php
    $srcset = $story->coverSrcset();
    $lazy = $lazy ?? true;
@endphp
<picture>
  @if ($srcset)
    <source type="image/webp" srcset="{{ $srcset }}" sizes="{{ $sizes ?? '250px' }}">
  @endif
  <img src="{{ $story->thumbnail_url }}"
       alt="Cover art for {{ $story->title }}"
       width="{{ $w ?? 300 }}" height="{{ $h ?? 450 }}"
       @if ($lazy) loading="lazy" decoding="async" @else fetchpriority="high" @endif>
</picture>
