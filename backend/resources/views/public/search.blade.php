@extends('public.layout')

@section('title', $q !== '' ? 'Search: '.$q : 'Search stories')
@section('description', 'Search the Stories catalogue by title, author or genre.')
{{-- Trang kết quả tìm kiếm KHÔNG được lập chỉ mục: mỗi truy vấn sinh một URL
     mới với nội dung ghép lại từ trang khác. Đó là trang mỏng, và cả Google lẫn
     AdSense đều xử phạt loại này. --}}
@section('robots', 'noindex, follow')
@section('wide', '1')

@section('content')
  <h1>{{ $q !== '' ? 'Results for “'.$q.'”' : 'Search stories' }}</h1>

  @if ($q === '')
    <p class="empty">Type a story title, an author or a genre in the box above.</p>
  @elseif ($stories->isEmpty())
    <p class="empty">
      Nothing matched “{{ $q }}”.
      <a href="{{ route('public.browse') }}">Browse all stories</a> instead.
    </p>
  @else
    <p class="updated">{{ $stories->total() }} {{ Str::plural('story', $stories->total()) }} found</p>
    <ul class="grid">
      @foreach ($stories as $story)
        <li>@include('public.partials.story-card', ['story' => $story])</li>
      @endforeach
    </ul>
    {{ $stories->onEachSide(1)->links('public.partials.pagination') }}
  @endif
@endsection
