@extends('public.layout')

@section('title', $story->title)
{{-- Str::limit cắt đúng số ký tự nên chẻ đôi từ giữa chừng; Str::words cắt ở
     ranh giới từ. 29 trang từng có mô tả gãy kiểu "…it was my f…". --}}
@section('description', Str::words($story->description
    ?: $story->title.' — a free short serial you can read in one sitting.', 26, '…'))
{{-- Ảnh chia sẻ dựng riêng khổ ngang 1200×630, KHÔNG dùng ảnh bìa dọc: ô xem
     trước của Facebook/Zalo là 1,91:1 nên ảnh dọc bị cắt lấy dải giữa, mất mặt
     nhân vật. Xem App\Services\SocialCardGenerator. --}}
@section('og_image', route('public.og', $story))
@section('og_image_w', '1200')
@section('og_image_h', '630')
@section('og_image_alt', $story->title.' — free short serial on Stories')
@section('og_type', 'book')
@section('wide', '1')

@push('meta')
<meta property="book:author" content="{{ $story->author ?: 'Stories' }}">
<meta property="book:release_date" content="{{ $story->created_at?->toDateString() }}">
@foreach ($story->categories as $category)
<meta property="book:tag" content="{{ $category->name }}">
@endforeach
<meta name="author" content="{{ $story->author ?: 'Stories' }}">
@endpush

@push('head')
@foreach ($jsonLd as $block)
<script type="application/ld+json">{!! $block !!}</script>
@endforeach
@endpush

@php($first = $story->chapters->first())

@section('hero')
  <section class="story-hero">
    @if ($bg = $story->coverSmallUrl())
      <span class="spot-bg" style="background-image:url('{{ $bg }}')" aria-hidden="true"></span>
    @endif
    <div class="wrap wide">
      <p class="crumbs">
        <a href="{{ route('public.home') }}">Home</a> ›
        <a href="{{ route('public.browse') }}">Stories</a> ›
        @if ($story->categories->isNotEmpty())
          <a href="{{ route('public.category', $story->categories->first()) }}">{{ $story->categories->first()->name }}</a> ›
        @endif
        <span>{{ Str::limit($story->title, 60) }}</span>
      </p>

      <div class="sh-main">
        <div class="sh-cover">
          @if ($story->thumbnail_url)
            {{-- Không lazy: ảnh này ở ngay đầu trang, lazy chỉ làm nó hiện chậm hơn. --}}
            @include('public.partials.cover', ['story' => $story, 'sizes' => '(max-width:600px) 116px, 220px', 'w' => 220, 'h' => 330, 'lazy' => false])
          @else
            <span class="noart">{{ $story->title }}</span>
          @endif
        </div>

        <div class="sh-head">
          <h1>{{ $story->title }}</h1>
          <p class="sh-by">
            by {{ $story->author ?: 'Stories' }}
            · <span class="status{{ $story->status === 'ongoing' ? ' live' : '' }}">{{ $story->status_label }}</span>
          </p>
        </div>

        {{-- Trên điện thoại khối này xuống dưới, trải hết bề ngang (xem .sh-main trong layout):
             cạnh bìa 116px không đủ chỗ cho ba con số và hai nút. --}}
        <div class="sh-more">
          <ul class="stats">
            <li><strong>{{ $story->chapters->count() }}</strong><span>{{ Str::plural('Chapter', $story->chapters->count()) }}</span></li>
            <li><strong>{{ $readMinutes < 60 ? $readMinutes.' min' : round($readMinutes / 60, 1).' h' }}</strong><span>Read time</span></li>
            @if (($story->reactions_count ?? 0) >= 3)
              <li><strong>@include('public.partials.rating', ['compact' => true])</strong><span>{{ $story->reactions_count }} ratings</span></li>
            @endif
          </ul>

          @if ($story->categories->isNotEmpty())
            <ul class="chips">
              @foreach ($story->categories as $category)
                <li><a class="chip dark" href="{{ route('public.category', $category) }}">{{ $category->name }}</a></li>
              @endforeach
            </ul>
          @endif

          @if ($first)
            <div class="actions">
              <a class="btn" id="read-btn" href="{{ route('public.chapter', [$story, $first->number]) }}">
                Read chapter {{ $first->number }}
              </a>
              <a class="btn glass" href="#chapters">All chapters</a>
            </div>
          @endif
        </div>
      </div>
    </div>
  </section>
@endsection

@section('content')
  <div class="story-cols">
    <div>
      @if ($story->description)
        <section class="sec" aria-labelledby="about-title" style="margin-top:36px">
          <div class="sec-head"><h2 id="about-title">Synopsis</h2></div>
          <p class="synopsis">{{ $story->description }}</p>
        </section>
      @endif

      @include('public.partials.ad')

      <section class="sec" id="chapters" aria-labelledby="chapters-title" style="scroll-margin-top:84px">
        <div class="sec-head"><h2 id="chapters-title">Chapters <span class="meta" style="font-weight:500">({{ $story->chapters->count() }})</span></h2></div>
        @if ($story->chapters->isEmpty())
          <p class="empty">No chapters published yet.</p>
        @else
          <ol class="chapters">
            @foreach ($story->chapters as $chapter)
              <li>
                <a href="{{ route('public.chapter', [$story, $chapter->number]) }}" data-ch="{{ $chapter->number }}">
                  <span class="n">{{ $chapter->number }}</span>
                  {{-- Nhiều tên chương trong kho đã mang sẵn tiền tố "Chapter N:", nên
                       in thẳng sẽ ra "3  Chapter 3: ..." — cắt đi cho khỏi lặp. --}}
                  <span class="t">{{ Str::of($chapter->title)->replaceMatches('/^\s*Chapter\s+\d+\s*[:\-–—]\s*/i', '') }}</span>
                </a>
              </li>
            @endforeach
          </ol>
        @endif
      </section>

      @include('public.partials.disclosure', ['inline' => true])
    </div>

    @if ($related->isNotEmpty())
      <aside class="story-side">
        <section class="panel sec" aria-labelledby="related-title" style="margin-top:36px">
          <div class="sec-head"><h2 id="related-title">You may also like</h2></div>
          <ul class="rank plain">
            @foreach ($related as $s)
              @include('public.partials.story-row', ['story' => $s])
            @endforeach
          </ul>
        </section>
      </aside>
    @endif
  </div>

{{-- Người đã đọc dở truyện này: nút chính đổi thành "Continue · Chapter N", và chương
     đó được đánh dấu trong danh sách. Lịch sử nằm ở localStorage (xem layout). --}}
<script>
(function () {
  var h = window.StoriesHistory && StoriesHistory.get(@json($story->slug));
  if (!h || !h.ch) return;
  var btn = document.getElementById('read-btn');
  var link = document.querySelector('.chapters a[data-ch="' + Number(h.ch) + '"]');
  if (btn && link) {
    btn.href = link.getAttribute('href');
    btn.textContent = 'Continue · Chapter ' + Number(h.ch);
  }
  document.querySelectorAll('.chapters a[data-ch]').forEach(function (a) {
    var n = Number(a.getAttribute('data-ch'));
    if (n < h.ch) a.classList.add('done');
    if (n === Number(h.ch)) {
      var tag = document.createElement('span');
      tag.className = 'here';
      tag.textContent = 'Last read';
      a.appendChild(tag);
    }
  });
})();
</script>
@endsection
