@extends('public.layout')

@section('title', $category ? $category->name.' stories' : 'Browse all stories')
{{-- KHÔNG strtolower tên thể loại: nó biến "CEO" thành "ceo". Giữ nguyên cách
     viết hoa như trong dữ liệu, và cho mỗi thể loại một câu riêng thay vì một
     khuôn mẫu chỉ khác đúng một từ. --}}
@section('description', $category
    ? $stories->total().' free '.$category->name.' serials on Stories — every chapter readable on the web, no account and no payment.'
    : 'Browse all '.$stories->total().' stories on Stories: short serials about hidden wealth, secret identities and overdue revenge, free to read on the web.')
@section('wide', '1')

@push('head')
@foreach ($jsonLd as $block)
<script type="application/ld+json">{!! $block !!}</script>
@endforeach
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
