@extends('public.layout')

{{-- Trang 404 mang giao diện site thay cho trang trắng mặc định của Laravel.

     Người tới đây phần lớn bấm link cũ: truyện đã ẩn khỏi web, hoặc URL chương của
     truyện đã gỡ. Trang trắng "404 | Not Found" làm họ rời site; trang này cho họ ô
     tìm kiếm và lối sang kho truyện. Mã trạng thái VẪN là 404 (Laravel giữ nguyên khi
     dựng view lỗi) — đừng đổi thành 200, đó là soft 404 Search Console sẽ báo.
     noindex: không bao giờ muốn một trang lỗi lên kết quả tìm kiếm; kéo theo không có
     quảng cáo (layout tắt quảng cáo trên trang noindex). --}}
@section('title', 'Page not found')
@section('description', 'This page does not exist or has moved.')
@section('robots', 'noindex, follow')

@section('content')
  <div class="page-head">
    <h1>Page not found</h1>
    <p class="updated">The link may be old, or the story may have been taken down.</p>
  </div>

  <form action="{{ route('public.search') }}" method="get" role="search" class="card" style="display:flex;gap:10px;align-items:center">
    <label for="nf-q" class="visually-hidden">Search stories</label>
    <input id="nf-q" type="search" name="q" placeholder="Search by title or genre" style="flex:1;min-width:0;padding:10px 12px;border:1px solid var(--border);border-radius:10px;background:var(--surface);color:var(--text);font:inherit">
    <button type="submit" class="btn">Search</button>
  </form>

  <p>Or start from the <a href="{{ route('public.browse') }}">full library</a>, the
    <a href="{{ route('public.home') }}">home page</a>, or one of these genres:</p>
  {{-- $navCategories chỉ được composer gắn cho layout, phần content ở đây không thấy. --}}
  @php
      $genres = \App\Models\Category::query()->withWebStoryCount()->orderByDesc('stories_count')->limit(8)->get()
          ->filter(fn ($c) => $c->stories_count >= \App\Models\Category::WEB_MIN_STORIES);
  @endphp
  @if ($genres->isNotEmpty())
    <ul class="chips">
      @foreach ($genres as $c)
        <li><a class="chip" href="{{ route('public.category', $c) }}">{{ $c->name }}</a></li>
      @endforeach
    </ul>
  @endif
@endsection
