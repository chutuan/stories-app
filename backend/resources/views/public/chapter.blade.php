@extends('public.layout')

@php
    // $heading và $metaDescription do controller dựng — xem ReadController::chapterDescription().
    // Trước đây mô tả lấy nguyên đoạn văn đầu tiên, khiến 55/75 trang có mô tả vô nghĩa.
    $dropCap = (bool) preg_match('/^\p{L}/u', $paragraphs[0] ?? '');
@endphp

{{-- Tiêu đề bỏ tên chương dài dòng: 47/75 trang từng vượt 60 ký tự nên bị
     Google cắt cụt trên trang kết quả. Tên truyện đứng trước vì đó mới là thứ
     người ta tìm; layout tự nối ' — Stories'. --}}
@section('title', $story->title.' · Chapter '.$chapter->number)
@section('description', $metaDescription)
@section('og_image', route('public.og', $story))
@section('og_image_w', '1200')
@section('og_image_h', '630')
@section('og_image_alt', $story->title.' — '.$heading)
@section('og_type', 'article')

@push('meta')
<meta property="article:published_time" content="{{ $chapter->created_at?->toAtomString() }}">
<meta property="article:modified_time" content="{{ $chapter->updated_at?->toAtomString() }}">
<meta property="article:author" content="{{ $story->author ?: 'Stories' }}">
@foreach ($story->categories as $category)
<meta property="article:tag" content="{{ $category->name }}">
@endforeach
<meta name="author" content="{{ $story->author ?: 'Stories' }}">
@endpush

@push('head')
@foreach ($jsonLd as $block)
<script type="application/ld+json">{!! $block !!}</script>
@endforeach
@endpush

@section('content')
<div class="progress" id="readProgress"></div>

<div class="reader">
  <p class="crumbs">
    <a href="{{ route('public.home') }}">Home</a> ›
    <a href="{{ route('public.story', $story) }}">{{ $story->title }}</a> ›
    Chapter {{ $chapter->number }}
  </p>

  {{-- Tên truyện làm dòng dẫn nhỏ phía trên: người đọc tới thẳng từ Google cần
       biết ngay đang đọc truyện nào, mà không để nó tranh chỗ với tên chương. --}}
  <p class="reader-kicker">{{ $story->title }}</p>
  <h1>{{ $heading }}</h1>
  <p class="reader-by">
    @if ($story->author) {{ $story->author }} · @endif
    Chapter {{ $chapter->number }} of {{ $story->chapters()->count() }}
  </p>
  <hr class="reader-rule">

  <article class="chapter-body{{ $dropCap ? ' dropcap' : '' }}">
    @foreach ($paragraphs as $i => $paragraph)
      <p>{{ $paragraph }}</p>

      {{-- Một ô quảng cáo trong bài, đặt sau đoạn thứ ba.
           Không đặt sớm hơn: AdSense cấm quảng cáo lấn át nội dung ở đầu trang,
           và người đọc cần thấy chữ trước đã. Không đặt nhiều ô giữa bài vì
           chương ở đây chỉ dài 150-260 từ với phần lớn truyện — nhồi thêm là
           thành "quảng cáo nhiều hơn nội dung", đúng thứ AdSense đánh trượt. --}}
      @if ($i === 2 && count($paragraphs) > 6)
        @include('public.partials.ad')
      @endif
    @endforeach

    @if ($paragraphs === [])
      <p class="empty">This chapter has no text yet.</p>
    @endif
  </article>

  @include('public.partials.ad')

  <nav class="pager" aria-label="Chapter navigation">
    @if ($prev)
      <a href="{{ route('public.chapter', [$story, $prev->number]) }}" rel="prev">‹ Chapter {{ $prev->number }}</a>
    @else
      <a href="{{ route('public.story', $story) }}">‹ All chapters</a>
    @endif

    <a href="{{ route('public.story', $story) }}">Chapter list</a>

    @if ($next)
      <a href="{{ route('public.chapter', [$story, $next->number]) }}" rel="next">Chapter {{ $next->number }} ›</a>
    @else
      <a href="{{ route('public.browse') }}">More stories ›</a>
    @endif
  </nav>
</div>

{{-- Thanh tiến độ: đọc một chương 1.800 từ trên điện thoại là cuộn rất lâu, không
     có mốc nào cho biết còn bao xa. Dùng passive listener và requestAnimationFrame
     để không làm khựng thao tác cuộn. --}}
<script>
(function () {
  var bar = document.getElementById('readProgress');
  if (!bar) return;
  var ticking = false;
  function update() {
    var max = document.documentElement.scrollHeight - innerHeight;
    bar.style.width = (max > 0 ? Math.min(1, scrollY / max) * 100 : 0) + '%';
    ticking = false;
  }
  addEventListener('scroll', function () {
    if (!ticking) { ticking = true; requestAnimationFrame(update); }
  }, { passive: true });
  addEventListener('resize', update, { passive: true });
  update();
})();
</script>
@endsection
