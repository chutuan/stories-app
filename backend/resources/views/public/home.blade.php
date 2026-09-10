@extends('public.layout')

@section('title', 'Read hidden-billionaire fiction free')
@section('description', 'Free short serials you can finish in one sitting — the janitor who owns the tower, the broke husband with a hidden empire, the beggar at the board meeting. New chapters weekly.')
@section('wide', '1')

@push('head')
<script type="application/ld+json">
{!! json_encode([
    '@context' => 'https://schema.org',
    '@type' => 'WebSite',
    'name' => 'Stories',
    'url' => route('public.home'),
    'description' => 'Free short serialised fiction — hidden billionaires, secret identities and long-overdue revenge.',
    'potentialAction' => [
        '@type' => 'SearchAction',
        'target' => ['@type' => 'EntryPoint', 'urlTemplate' => route('public.search').'?q={search_term_string}'],
        'query-input' => 'required name=search_term_string',
    ],
], JSON_UNESCAPED_SLASHES | JSON_UNESCAPED_UNICODE) !!}
</script>
@endpush

@section('content')
  <h1>Short serials, free to read</h1>
  <p class="updated">Stories about people who get underestimated — and what happens when the
    truth comes out. Every chapter is free on the web.</p>

  @if ($featured)
    <div class="card">
      <div class="hero">
        @if ($featured->thumbnail_url)
          <img src="{{ $featured->thumbnail_url }}" alt="Cover art for {{ $featured->title }}" width="180" height="270">
        @else
          <span class="noart">{{ $featured->title }}</span>
        @endif
        <div class="hero-body">
          <p class="meta" style="color:var(--accent-deep);font-weight:650;margin:0 0 4px">Featured</p>
          <h2 style="margin:0;font-size:24px">{{ $featured->title }}</h2>
          <p class="meta" style="color:var(--muted);margin:6px 0 0">
            {{ $featured->author ?: 'Stories' }} · {{ $featured->chapters_count }}
            {{ Str::plural('chapter', $featured->chapters_count) }} · {{ $featured->status_label }}
          </p>
          @if ($featured->description)
            <p>{{ Str::limit($featured->description, 260) }}</p>
          @endif
          <a class="btn" href="{{ route('public.story', $featured) }}">Start reading</a>
        </div>
      </div>
    </div>
  @endif

  @include('public.partials.ad')

  @if ($updated->isNotEmpty())
    <h2>Recently updated</h2>
    <ul class="grid">
      @foreach ($updated as $story)
        <li>@include('public.partials.story-card', ['story' => $story])</li>
      @endforeach
    </ul>
  @endif

  @if ($categories->isNotEmpty())
    <h2>Browse by genre</h2>
    <ul class="tags">
      @foreach ($categories as $category)
        <li><a class="tag" href="{{ route('public.category', $category) }}">{{ $category->name }}</a></li>
      @endforeach
    </ul>
  @endif

  @include('public.partials.ad')

  @if ($newest->isNotEmpty())
    <h2>Newest stories</h2>
    <ul class="grid">
      @foreach ($newest as $story)
        <li>@include('public.partials.story-card', ['story' => $story])</li>
      @endforeach
    </ul>
  @endif

  <div class="card">
    <h2 style="margin-top:0">Prefer to read on your phone?</h2>
    <p>The Stories app adds a reader you can tune — font size, line spacing, brightness and four
      page themes — plus narrated audio for selected stories, and a library that remembers where
      you stopped. No account needed.</p>
    <p class="meta">Questions or content requests:
      <a href="mailto:{{ config('app.support_email') }}">{{ config('app.support_email') }}</a></p>
  </div>
@endsection
