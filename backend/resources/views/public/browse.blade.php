@extends('public.layout')

{{-- Trang 2, 3… mang "Page N" trong tiêu đề và mô tả: không có nó thì mọi trang của
     /browse cùng một tiêu đề + mô tả, Search Console gom thành "trùng lặp" và Google
     chỉ giữ một trang — các truyện chỉ nằm ở trang sau mất lối vào. --}}
@php
    $pageSuffix = $stories->currentPage() > 1 ? ' — page '.$stories->currentPage() : '';
@endphp
{{-- Thể loại: thêm "free to read online" — người ta tìm "revenge stories to read online
     free", còn "Revenge stories" trần thì chỉ khớp với nửa câu. --}}
@section('title', ($category ? $category->name.' stories — free to read online' : 'Browse all stories').$pageSuffix)
{{-- KHÔNG strtolower tên thể loại: nó biến "CEO" thành "ceo". Giữ nguyên cách
     viết hoa như trong dữ liệu, và cho mỗi thể loại một câu riêng thay vì một
     khuôn mẫu chỉ khác đúng một từ. --}}
@section('description', ($category
    ? $stories->total().' free '.$category->name.' serials on '.config('app.site_name').' — every chapter readable on the web, no account and no payment.'
    : 'Browse all '.$stories->total().' stories on '.config('app.site_name').': short serials about betrayal, family secrets, second chances and overdue revenge, free to read on the web.')
    .($stories->currentPage() > 1 ? ' Page '.$stories->currentPage().' of '.$stories->lastPage().'.' : ''))
@section('wide', '1')
@if (! empty($thin))
@section('robots', 'noindex, follow')
@endif

@push('head')
@foreach ($jsonLd as $block)
<script type="application/ld+json">{!! $block !!}</script>
@endforeach
@endpush

@section('content')
  <div class="page-head">
    <h1>{{ $category ? $category->name : 'All stories' }}</h1>
    <p class="updated">
      {{ $stories->total() }} {{ Str::plural('story', $stories->total()) }}
      @if ($category) in {{ $category->name }} @endif
      · every chapter free to read
      @if ($stories->currentPage() > 1) · page {{ $stories->currentPage() }} of {{ $stories->lastPage() }} @endif
    </p>
    @if (! empty($intro))
      <p class="intro">{{ $intro }}</p>
    @endif
  </div>

  @if ($categories->isNotEmpty())
    <ul class="chips scroll filters">
      <li><a class="chip {{ $category ? '' : 'on' }}" href="{{ route('public.browse') }}">All</a></li>
      @foreach ($categories as $c)
        <li><a class="chip {{ $category && $c->is($category) ? 'on' : '' }}"
               href="{{ route('public.category', $c) }}">{{ $c->name }}</a></li>
      @endforeach
    </ul>
  @endif

  @include('public.partials.ad')

  @if ($stories->isEmpty())
    <p class="empty">No stories here yet. <a href="{{ route('public.browse') }}">Browse everything</a>.</p>
  @else
    {{-- Hàng đầu (2 ô trên điện thoại) nằm ngay màn hình đầu: tải ngay, không lazy. Trang thể
         loại là nơi Google đưa người đọc vào nhiều nhất, và lazy làm bìa hiện chậm hơn chữ. --}}
    <ul class="grid">
      @foreach ($stories as $story)
        <li>@include('public.partials.story-card', ['story' => $story, 'lazy' => $loop->index >= 2])</li>
      @endforeach
    </ul>

    {{ $stories->onEachSide(1)->links('public.partials.pagination') }}
  @endif
@endsection
