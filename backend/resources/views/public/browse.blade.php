@extends('public.layout')

@section('title', $category ? $category->name.' stories' : 'Browse all stories')
@section('description', $category
    ? 'Free '.strtolower($category->name).' serials — read every chapter on the web, no account needed.'
    : 'Every story in the Stories catalogue. Free short serials about hidden wealth, secret identities and overdue revenge.')
@section('wide', '1')

@push('head')
{{-- ItemList giúp Google hiểu đây là trang DANH MỤC dẫn tới các trang truyện,
     chứ không phải một trang nội dung mỏng lặp lại tiêu đề. --}}
<script type="application/ld+json">
{!! json_encode([
    '@context' => 'https://schema.org',
    '@type' => 'CollectionPage',
    'name' => $category ? $category->name.' stories' : 'All stories',
    'url' => $category ? route('public.category', $category) : route('public.browse'),
    'isPartOf' => ['@type' => 'WebSite', 'name' => 'Stories', 'url' => route('public.home')],
    'mainEntity' => [
        '@type' => 'ItemList',
        'numberOfItems' => $stories->total(),
        'itemListElement' => collect($stories->items())->values()->map(fn ($s, $i) => [
            '@type' => 'ListItem',
            'position' => $stories->firstItem() + $i,
            'url' => route('public.story', $s),
            'name' => $s->title,
        ])->all(),
    ],
], JSON_UNESCAPED_SLASHES | JSON_UNESCAPED_UNICODE) !!}
</script>
@endpush

@section('content')
  <h1>{{ $category ? $category->name : 'All stories' }}</h1>
  <p class="updated">
    {{ $stories->total() }} {{ Str::plural('story', $stories->total()) }}
    @if ($category) in {{ $category->name }} @endif
    · every chapter free to read
  </p>

  @if ($categories->isNotEmpty())
    <ul class="tags">
      <li><a class="tag {{ $category ? '' : 'on' }}" href="{{ route('public.browse') }}">All</a></li>
      @foreach ($categories as $c)
        <li><a class="tag {{ $category && $c->is($category) ? 'on' : '' }}"
               href="{{ route('public.category', $c) }}">{{ $c->name }}</a></li>
      @endforeach
    </ul>
  @endif

  @include('public.partials.ad')

  @if ($stories->isEmpty())
    <p class="empty">No stories here yet. <a href="{{ route('public.browse') }}">Browse everything</a>.</p>
  @else
    <ul class="grid">
      @foreach ($stories as $story)
        <li>@include('public.partials.story-card', ['story' => $story])</li>
      @endforeach
    </ul>

    {{ $stories->onEachSide(1)->links('public.partials.pagination') }}
  @endif
@endsection
