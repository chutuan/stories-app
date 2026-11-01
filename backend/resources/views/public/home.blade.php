@extends('public.layout')

@section('title', 'Read hidden-billionaire fiction free')
{{-- Mô tả cũ nêu đích danh ba truyện — the janitor who owns the tower, the broke
     husband with a hidden empire, the beggar at the board meeting — CẢ BA đã bị gỡ
     khỏi kho ngày 10/09/2026. Google hiển thị đúng chuỗi này dưới kết quả tìm kiếm,
     nên người bấm vào sẽ không tìm thấy thứ được hứa. Mô tả mới nói về thể loại
     thật của kho hiện tại, không đính vào truyện cụ thể nào để khỏi lạc hậu lần nữa. --}}
@section('description', 'Free short serials about betrayal, custody battles and hidden wealth — every chapter readable on the web, no account and no payment. Two stories are free in full, with narrated audio.')
@section('wide', '1')

@push('head')
@foreach ($jsonLd as $block)
<script type="application/ld+json">{!! $block !!}</script>
@endforeach
@if ($featured && ($bg = $featured->coverSmallUrl()))
{{-- Ảnh nền mờ của dải nổi bật nằm trong CSS nên trình duyệt chỉ thấy nó khi đã dựng
     xong cây CSS; preload để nó về cùng lúc với ảnh bìa. --}}
<link rel="preload" as="image" href="{{ $bg }}">
@endif
@endpush

@section('content')
  {{-- Tiêu đề trang cho trình đọc màn hình và Google; mắt người đọc thấy dải nổi bật trước. --}}
  <h1 class="visually-hidden">Stories — short serials, free to read</h1>

  <div class="home-top{{ $featured ? '' : ' solo' }}">
    @if ($featured)
      <section class="spotlight" aria-labelledby="spot-title">
        @if ($bg = $featured->coverSmallUrl())
          <span class="spot-bg" style="background-image:url('{{ $bg }}')" aria-hidden="true"></span>
        @endif
        <div class="spot-inner">
          <a class="spot-cover" href="{{ route('public.story', $featured) }}" tabindex="-1" aria-hidden="true">
            @if ($featured->thumbnail_url)
              @include('public.partials.cover', ['story' => $featured, 'sizes' => '(max-width:600px) 112px, 210px', 'w' => 210, 'h' => 315, 'lazy' => false])
            @else
              <span class="noart">{{ $featured->title }}</span>
            @endif
          </a>
          <div>
            <p class="eyebrow">Featured</p>
            <h2 id="spot-title"><a href="{{ route('public.story', $featured) }}">{{ $featured->title }}</a></h2>
            <p class="spot-meta">
              {{ $featured->chapters_count }} {{ Str::plural('chapter', $featured->chapters_count) }}
              · {{ $featured->status_label }}
              @include('public.partials.rating', ['story' => $featured, 'compact' => true])
            </p>
            @if ($featured->categories->isNotEmpty())
              <ul class="chips">
                @foreach ($featured->categories->take(3) as $category)
                  <li><a class="chip dark" href="{{ route('public.category', $category) }}">{{ $category->name }}</a></li>
                @endforeach
              </ul>
            @endif
            @if ($featured->description)
              <p class="spot-desc">{{ Str::limit($featured->description, 320) }}</p>
            @endif
            <div class="actions">
              @if ($featured->chapters_min_number)
                <a class="btn" href="{{ route('public.chapter', [$featured, $featured->chapters_min_number]) }}">Start reading</a>
              @endif
              <a class="btn glass" href="{{ route('public.story', $featured) }}">Chapters</a>
            </div>
          </div>
        </div>
      </section>
    @endif

    @if ($ranking->isNotEmpty())
      <section class="panel" aria-labelledby="rank-title">
        <div class="sec-head"><h2 id="rank-title">Most read</h2></div>
        <ol class="rank">
          @foreach ($ranking as $story)
            @include('public.partials.story-row', ['story' => $story, 'rank' => $loop->iteration])
          @endforeach
        </ol>
      </section>
    @endif
  </div>

  {{-- Đọc dở: dựng bằng JS từ localStorage (trang này được cache chung cho mọi người,
       server không biết ai đang đọc gì). Không có lịch sử thì mục này ẩn hẳn. --}}
  <section class="sec" id="continue" aria-labelledby="continue-title" hidden>
    <div class="sec-head"><h2 id="continue-title">Continue reading</h2></div>
    <ul class="cont" id="continue-list"></ul>
  </section>

  @if ($categories->isNotEmpty())
    <section class="sec" aria-labelledby="genres-title">
      <div class="sec-head">
        <h2 id="genres-title">Browse by genre</h2>
        <a href="{{ route('public.browse') }}">All stories →</a>
      </div>
      <ul class="chips scroll">
        @foreach ($categories as $category)
          <li><a class="chip" href="{{ route('public.category', $category) }}">{{ $category->name }} <small>{{ $category->stories_count }}</small></a></li>
        @endforeach
      </ul>
    </section>
  @endif

  @include('public.partials.ad')

  @if ($updated->isNotEmpty())
    <section class="sec" aria-labelledby="updated-title">
      <div class="sec-head">
        <h2 id="updated-title">Recently updated</h2>
        <a href="{{ route('public.browse') }}">See all →</a>
      </div>
      <ul class="grid">
        @foreach ($updated as $story)
          <li>@include('public.partials.story-card', ['story' => $story])</li>
        @endforeach
      </ul>
    </section>
  @endif

  @foreach ($shelves as $i => $shelf)
    <section class="sec" aria-labelledby="shelf-{{ $i }}">
      <div class="sec-head">
        <h2 id="shelf-{{ $i }}">{{ $shelf['category']->name }}</h2>
        <a href="{{ route('public.category', $shelf['category']) }}">See all {{ $shelf['category']->stories_count }} →</a>
      </div>
      <div class="shelf-wrap">
        <button class="shelf-btn prev" type="button" aria-label="Scroll left">
          <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2.4" stroke-linecap="round" stroke-linejoin="round" aria-hidden="true"><path d="m15 5-7 7 7 7"/></svg>
        </button>
        <ul class="shelf">
          @foreach ($shelf['stories'] as $story)
            <li>@include('public.partials.story-card', ['story' => $story])</li>
          @endforeach
        </ul>
        <button class="shelf-btn next" type="button" aria-label="Scroll right">
          <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2.4" stroke-linecap="round" stroke-linejoin="round" aria-hidden="true"><path d="m9 5 7 7-7 7"/></svg>
        </button>
      </div>
    </section>
    @if ($i === 0)
      @include('public.partials.ad')
    @endif
  @endforeach

  <section class="app-band" aria-labelledby="app-title">
    <div>
      <h2 id="app-title">Prefer to read on your phone?</h2>
      <p>The Stories app adds a reader you can tune — font size, line spacing, brightness and four
        page themes — plus narrated audio for selected stories, and a library that remembers where
        you stopped. No account needed.</p>
      <p class="meta" style="margin-top:10px;font-size:14px">Questions or content requests:
        <a href="mailto:{{ config('app.support_email') }}">{{ config('app.support_email') }}</a>
        · <a href="{{ route('public.contact') }}">Contact</a>
        · <a href="{{ route('public.editorial') }}">Editorial standards</a></p>
    </div>
    <a class="btn" href="{{ route('public.app') }}">Get the free app</a>
  </section>

<script>
(function () {
  var list = (window.StoriesHistory ? StoriesHistory.all() : []).slice(0, 6);
  if (!list.length) return;
  var ul = document.getElementById('continue-list');
  // Dựng bằng createElement + textContent, KHÔNG ghép chuỗi HTML: tên truyện nằm trong
  // localStorage, ai sửa được localStorage thì sẽ chèn được mã vào trang nếu dùng innerHTML.
  list.forEach(function (h) {
    if (!h || !h.url || !/^\/story\//.test(h.url)) return;
    var li = document.createElement('li'), a = document.createElement('a');
    a.href = h.url;
    var img = document.createElement('img');
    img.alt = ''; img.width = 54; img.height = 81; img.loading = 'lazy';
    if (h.cover && /^https?:\/\//.test(h.cover)) img.src = h.cover;
    var body = document.createElement('span'), t = document.createElement('strong'), m = document.createElement('span');
    t.textContent = h.title || '';
    m.className = 'meta';
    m.textContent = 'Chapter ' + h.ch + (h.total ? ' of ' + h.total : '');
    var bar = document.createElement('span'), fill = document.createElement('i');
    bar.className = 'bar';
    fill.style.width = (h.total ? Math.min(100, Math.round(h.ch / h.total * 100)) : 0) + '%';
    bar.appendChild(fill);
    body.appendChild(t); body.appendChild(m); body.appendChild(bar);
    a.appendChild(img); a.appendChild(body); li.appendChild(a); ul.appendChild(li);
  });
  if (ul.children.length) document.getElementById('continue').hidden = false;
})();
</script>
@endsection
