@extends('public.layout')

{{-- Câu mồi đi sau tựa ngắn: đó là câu người đọc từ Facebook gõ vào Google
     (xem migration add_hook_to_stories_table). Dấu chấm cuối bỏ đi, "…" thì giữ. --}}
@section('title', $story->hook ? $story->title.' — '.rtrim($story->hook, '.') : $story->title)
{{-- Controller cắt sẵn ≤155 ký tự ở ranh giới từ (ReadController::clip). --}}
@section('description', $metaDescription)
{{-- Ảnh chia sẻ dựng riêng khổ ngang 1200×630, KHÔNG dùng ảnh bìa dọc: ô xem
     trước của Facebook/Zalo là 1,91:1 nên ảnh dọc bị cắt lấy dải giữa, mất mặt
     nhân vật. Xem App\Services\SocialCardGenerator. --}}
@section('og_image', route('public.og', $story))
@section('og_image_w', '1200')
@section('og_image_h', '630')
@section('og_image_alt', $story->title.' — free short serial on Stories')
@section('og_type', 'book')
@section('wide', '1')
{{-- Truyện cũng đăng ở site khác của mình: không để Google thấy hai bản trùng. --}}
@if ($story->noindex)
@section('robots', 'noindex, follow')
@endif

@push('meta')
<meta property="book:author" content="{{ config('app.editor_name') }}">
<meta property="book:release_date" content="{{ $story->created_at?->toDateString() }}">
@foreach ($story->categories as $category)
<meta property="book:tag" content="{{ $category->name }}">
@endforeach
<meta name="author" content="{{ config('app.editor_name') }}">
@endpush

@push('head')
@foreach ($jsonLd as $block)
<script type="application/ld+json">{!! $block !!}</script>
@endforeach
@endpush

@php($first = $story->chapters->first())

@section('hero')
  <section class="story-hero">
    @if ($bg = $story->coverBlurUrl())
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
          {{-- Tên thật của người chịu trách nhiệm, không phải cột `author` (bút danh / "Tuan"
               từ lúc nạp): truyện soạn với công cụ AI, người ký tên là người duyệt đăng.
               Xem trang /editor và StructuredData::editor(). --}}
          <p class="sh-by">
            Edited by <a href="{{ route('public.editor') }}">{{ config('app.editor_name') }}</a>
            · <span class="status{{ $story->status === 'ongoing' ? ' live' : '' }}">{{ $story->status_label }}</span>
          </p>
        </div>

        {{-- Trên điện thoại khối này xuống dưới, trải hết bề ngang (xem .sh-main trong layout):
             cạnh bìa 116px không đủ chỗ cho ba con số và hai nút. --}}
        <div class="sh-more">
          {{-- Ở .sh-more chứ không ở .sh-head: trên điện thoại .sh-head nằm cạnh bìa 116px,
               câu dài gần 200 ký tự nhét vào đó thành một cột chữ hẹp dài ngoằng. --}}
          @if ($story->hook)
            <p class="sh-hook">{{ $story->hook }}</p>
          @endif
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

      {{-- Vài đoạn đầu chương 1 (ReadController::opening): trang truyện có thêm nội dung
           thật, người đọc thử giọng văn trước khi bấm. Mang class .synopsis để cùng kiểu chữ
           và cùng lớp chặn sao chép (partials/protect). --}}
      @if ($opening)
        <section class="sec" aria-labelledby="opening-title" style="margin-top:36px">
          <div class="sec-head"><h2 id="opening-title">Read the opening</h2></div>
          <div class="synopsis opening">
            @foreach ($opening as $p)
              <p>{{ $p }}</p>
            @endforeach
          </div>
          <p class="opening-more">
            <a href="{{ route('public.chapter', [$story, $story->chapters->first()->number]) }}">Keep reading chapter {{ $story->chapters->first()->number }} →</a>
          </p>
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
                  {{-- Bỏ tiền tố "Chapter N:" và tên kiểu "Part N" trùng số — xem Chapter::cleanTitle(). --}}
                  <span class="t">{{ $chapter->cleanTitle() ?: 'Chapter '.$chapter->number }}</span>
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
