@extends('public.layout')

@section('title', $story->title)
@section('description', Str::limit($story->description
    ?: $story->title.' — a free short serial you can read in one sitting.', 155))
@section('og_image', $story->thumbnail_url ?: asset('icons/icon-512.png'))
@section('og_type', 'book')

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
