@extends('public.layout')

@php
    // Nhiều tên chương trong kho đã mang sẵn tiền tố "Chapter N:". In nguyên si
    // sẽ ra "Chapter 3 — Chapter 3: The Elevator" trên cả <title> lẫn <h1>, vừa
    // xấu vừa loãng từ khoá. Cắt tiền tố rồi tự dựng lại một lần duy nhất.
    $cleanTitle = trim(preg_replace('/^\s*Chapter\s+\d+\s*[:\-–—]\s*/i', '', (string) $chapter->title));
    $heading = 'Chapter '.$chapter->number.($cleanTitle !== '' ? ': '.$cleanTitle : '');
    $excerpt = Str::limit($paragraphs[0] ?? $story->description ?? '', 155);

    // Chữ cái lớn đầu đoạn chỉ bật khi đoạn mở đầu bắt đầu bằng CHỮ CÁI.
    // CSS ::first-letter gộp cả dấu câu đứng trước, nên chương mở bằng lời thoại
    // ('"You do not sell a thousand," Alys said.') sẽ phóng to cái dấu ngoặc kép
    // thành một khối cao ba dòng — trông như lỗi hiển thị. Hai chương trong kho
    // hiện đang mở đầu như vậy.
    $dropCap = (bool) preg_match('/^\p{L}/u', $paragraphs[0] ?? '');
@endphp

@section('title', $heading.' — '.$story->title)
@section('description', $excerpt)
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
<script type="application/ld+json">
{!! json_encode(array_filter([
    '@context' => 'https://schema.org',
    '@type' => 'Article',
    'headline' => $heading.' — '.$story->title,
    'url' => route('public.chapter', [$story, $chapter->number]),
    'description' => $excerpt ?: null,
    'image' => $story->thumbnail_url ?: null,
    'inLanguage' => 'en',
    'datePublished' => $chapter->created_at?->toAtomString(),
    'dateModified' => $chapter->updated_at?->toAtomString(),
    'author' => ['@type' => 'Person', 'name' => $story->author ?: 'Stories'],
    'publisher' => ['@type' => 'Organization', 'name' => 'Stories'],
    'isPartOf' => ['@type' => 'Book', 'name' => $story->title, 'url' => route('public.story', $story)],
]), JSON_UNESCAPED_SLASHES | JSON_UNESCAPED_UNICODE) !!}
</script>
<script type="application/ld+json">
{!! json_encode([
    '@context' => 'https://schema.org',
    '@type' => 'BreadcrumbList',
    'itemListElement' => [
        ['@type' => 'ListItem', 'position' => 1, 'name' => 'Home', 'item' => route('public.home')],
        ['@type' => 'ListItem', 'position' => 2, 'name' => $story->title, 'item' => route('public.story', $story)],
        ['@type' => 'ListItem', 'position' => 3, 'name' => 'Chapter '.$chapter->number],
    ],
], JSON_UNESCAPED_SLASHES | JSON_UNESCAPED_UNICODE) !!}
</script>
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
