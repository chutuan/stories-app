@extends('public.layout')

@section('title', $story->title)
@section('description', Str::limit($story->description
    ?: $story->title.' — a free short serial you can read in one sitting.', 155))
{{-- Ảnh chia sẻ dựng riêng khổ ngang 1200×630, KHÔNG dùng ảnh bìa dọc: ô xem
     trước của Facebook/Zalo là 1,91:1 nên ảnh dọc bị cắt lấy dải giữa, mất mặt
     nhân vật. Xem App\Services\SocialCardGenerator. --}}
@section('og_image', route('public.og', $story))
@section('og_image_w', '1200')
@section('og_image_h', '630')
@section('og_image_alt', $story->title.' — free short serial on Stories')
@section('og_type', 'book')

@push('meta')
<meta property="book:author" content="{{ $story->author ?: 'Stories' }}">
<meta property="book:release_date" content="{{ $story->created_at?->toDateString() }}">
@foreach ($story->categories as $category)
<meta property="book:tag" content="{{ $category->name }}">
@endforeach
<meta name="author" content="{{ $story->author ?: 'Stories' }}">
@endpush

@push('head')
<script type="application/ld+json">
{!! json_encode(array_filter([
    '@context' => 'https://schema.org',
    '@type' => 'Book',
    'name' => $story->title,
    'url' => route('public.story', $story),
    'author' => $story->author ? ['@type' => 'Person', 'name' => $story->author] : null,
    'description' => $story->description ?: null,
    'image' => $story->thumbnail_url ?: null,
    'numberOfPages' => $story->chapters->count(),
    'inLanguage' => 'en',
    'genre' => $story->categories->pluck('name')->all() ?: null,
]), JSON_UNESCAPED_SLASHES | JSON_UNESCAPED_UNICODE) !!}
</script>
@endpush

@section('content')
  <p class="crumbs">
    <a href="{{ route('public.home') }}">Home</a> ›
    <a href="{{ route('public.browse') }}">Stories</a> ›
    {{ $story->title }}
  </p>

  <div class="hero">
    @if ($story->thumbnail_url)
      <img src="{{ $story->thumbnail_url }}" alt="Cover art for {{ $story->title }}" width="180" height="270">
    @else
      <span class="noart">{{ $story->title }}</span>
    @endif
    <div class="hero-body">
      <h1 style="font-size:28px">{{ $story->title }}</h1>
      <p class="meta" style="color:var(--muted);margin:0">
        {{ $story->author ?: 'Stories' }} · {{ $story->chapters->count() }}
        {{ Str::plural('chapter', $story->chapters->count()) }} · {{ $story->status_label }}
      </p>

      @if ($story->categories->isNotEmpty())
        <ul class="tags">
          @foreach ($story->categories as $category)
            <li><a class="tag" href="{{ route('public.category', $category) }}">{{ $category->name }}</a></li>
          @endforeach
        </ul>
      @endif

      @if ($story->description)
        <p>{{ $story->description }}</p>
      @endif

      @if ($story->chapters->isNotEmpty())
        <a class="btn" href="{{ route('public.chapter', [$story, $story->chapters->first()->number]) }}">
          Read chapter {{ $story->chapters->first()->number }}
        </a>
      @endif
    </div>
  </div>

  @include('public.partials.ad')

  <h2>Chapters</h2>
  @if ($story->chapters->isEmpty())
    <p class="empty">No chapters published yet.</p>
  @else
    <ul class="chapters">
      @foreach ($story->chapters as $chapter)
        <li>
          <a href="{{ route('public.chapter', [$story, $chapter->number]) }}">
            <span class="n">{{ $chapter->number }}</span>
            {{-- Nhiều tên chương trong kho đã mang sẵn tiền tố "Chapter N:", nên
                 in thẳng sẽ ra "3  Chapter 3: ..." — cắt đi cho khỏi lặp. --}}
            <span>{{ Str::of($chapter->title)->replaceMatches('/^\s*Chapter\s+\d+\s*[:\-–—]\s*/i', '') }}</span>
          </a>
        </li>
      @endforeach
    </ul>
  @endif
@endsection
