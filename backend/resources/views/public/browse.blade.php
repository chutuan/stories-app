@extends('public.layout')

@section('title', $category ? $category->name.' stories' : 'Browse all stories')
@section('description', $category
    ? 'Free '.strtolower($category->name).' serials — read every chapter on the web, no account needed.'
    : 'Every story in the Stories catalogue. Free short serials about hidden wealth, secret identities and overdue revenge.')
@section('wide', '1')

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
