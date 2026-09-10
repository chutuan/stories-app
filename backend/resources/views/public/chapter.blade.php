@extends('public.layout')

@php
    // Nhiều tên chương trong kho đã mang sẵn tiền tố "Chapter N:". In nguyên si
    // sẽ ra "Chapter 3 — Chapter 3: The Elevator" trên cả <title> lẫn <h1>, vừa
    // xấu vừa loãng từ khoá. Cắt tiền tố rồi tự dựng lại một lần duy nhất.
    $cleanTitle = trim(preg_replace('/^\s*Chapter\s+\d+\s*[:\-–—]\s*/i', '', (string) $chapter->title));
    $heading = 'Chapter '.$chapter->number.($cleanTitle !== '' ? ': '.$cleanTitle : '');
    $excerpt = Str::limit($paragraphs[0] ?? $story->description ?? '', 155);
@endphp

@section('title', $heading.' — '.$story->title)
@section('description', $excerpt)
@section('og_image', $story->thumbnail_url ?: asset('icons/icon-512.png'))
@section('og_type', 'article')

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
  <p class="crumbs">
    <a href="{{ route('public.home') }}">Home</a> ›
    <a href="{{ route('public.story', $story) }}">{{ $story->title }}</a> ›
    Chapter {{ $chapter->number }}
  </p>

  <h1 style="font-size:26px">{{ $heading }}</h1>
  <p class="updated">{{ $story->title }} @if ($story->author) · {{ $story->author }} @endif</p>

  <article class="chapter-body">
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
@endsection
