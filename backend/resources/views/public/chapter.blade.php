@extends('public.layout')

@php
    // $heading và $metaDescription do controller dựng — xem ReadController::chapterDescription().
    // Trước đây mô tả lấy nguyên đoạn văn đầu tiên, khiến 55/75 trang có mô tả vô nghĩa.
    $dropCap = (bool) preg_match('/^\p{L}/u', $paragraphs[0] ?? '');
@endphp

{{-- Tiêu đề bỏ tên chương dài dòng: 47/75 trang từng vượt 60 ký tự nên bị
     Google cắt cụt trên trang kết quả. Tên truyện đứng trước vì đó mới là thứ
     người ta tìm; layout tự nối ' — Stories'. --}}
@section('title', $story->title.' · Chapter '.$chapter->number)
@section('description', $metaDescription)
@section('og_image', route('public.og', $story))
@section('og_image_w', '1200')
@section('og_image_h', '630')
@section('og_image_alt', $story->title.' — '.$heading)
@section('og_type', 'article')
@if ($story->noindex)
@section('robots', 'noindex, follow')
@endif

@push('meta')
<meta property="article:published_time" content="{{ $chapter->created_at?->toAtomString() }}">
<meta property="article:modified_time" content="{{ $chapter->updated_at?->toAtomString() }}">
<meta property="article:author" content="{{ config('app.editor_name') }}">
@foreach ($story->categories as $category)
<meta property="article:tag" content="{{ $category->name }}">
@endforeach
<meta name="author" content="{{ config('app.editor_name') }}">
@endpush

@push('head')
@foreach ($jsonLd as $block)
<script type="application/ld+json">{!! $block !!}</script>
@endforeach
@endpush

@section('content')
<div class="progress" id="readProgress"></div>

<div class="reader">
  <p class="crumbs">
    <a href="{{ route('public.home') }}">Home</a> ›
    <a href="{{ route('public.story', $story) }}">{{ Str::limit($story->title, 50) }}</a> ›
    Chapter {{ $chapter->number }}
  </p>

  {{-- Tên truyện làm dòng dẫn nhỏ phía trên: người đọc tới thẳng từ Google cần
       biết ngay đang đọc truyện nào, mà không để nó tranh chỗ với tên chương. --}}
  <p class="reader-kicker"><a href="{{ route('public.story', $story) }}">{{ $story->title }}</a></p>
  <h1>{{ $heading }}</h1>
  @php $totalWords = str_word_count(implode(' ', $paragraphs)); @endphp
  <p class="reader-by">
    Edited by <a href="{{ route('public.editor') }}">{{ config('app.editor_name') }}</a> ·
    Chapter {{ $chapter->number }} of {{ $chapterList->count() }}
    · {{ max(1, (int) round($totalWords / 230)) }} min read
  </p>

  {{-- Thanh công cụ đầu chương: nhảy thẳng tới chương bất kỳ, và hai nút lùi/tới.
       Ô chọn cần JS để chuyển trang; tắt JS thì vẫn còn "Chapter list" ở cuối chương. --}}
  <div class="reader-tools">
    <a class="icon-btn" href="{{ $prev ? route('public.chapter', [$story, $prev->number]) : '#' }}"
       @unless ($prev) aria-disabled="true" tabindex="-1" @endunless aria-label="Previous chapter">
      <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2.4" stroke-linecap="round" stroke-linejoin="round" aria-hidden="true"><path d="m15 5-7 7 7 7"/></svg>
    </a>
    <label class="jump">
      <span class="visually-hidden">Go to chapter</span>
      <select id="jump" autocomplete="off">
        @foreach ($chapterList as $c)
          @php $t = $c->cleanTitle(); @endphp
          <option value="{{ route('public.chapter', [$story, $c->number]) }}" @selected($c->number === $chapter->number)>
            {{ $c->number }}. {{ Str::limit($t !== '' ? $t : 'Chapter '.$c->number, 60) }}
          </option>
        @endforeach
      </select>
    </label>
    <a class="icon-btn" href="{{ $next ? route('public.chapter', [$story, $next->number]) : '#' }}"
       @unless ($next) aria-disabled="true" tabindex="-1" @endunless aria-label="Next chapter">
      <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2.4" stroke-linecap="round" stroke-linejoin="round" aria-hidden="true"><path d="m9 5 7 7-7 7"/></svg>
    </a>
  </div>

  <article class="chapter-body{{ $dropCap ? ' dropcap' : '' }}">
    @php $wordsSoFar = 0; $adShown = false; @endphp
    @foreach ($paragraphs as $i => $paragraph)
      <p>{{ $paragraph }}</p>
      @php $wordsSoFar += str_word_count($paragraph); @endphp

      {{-- Ô quảng cáo trong bài, đặt theo SỐ TỪ ĐÃ ĐỌC chứ không theo số đoạn.
           Bản cũ chèn sau đoạn thứ ba, mà truyện ở đây mở bằng nhiều câu rất ngắn
           ("He was not searching for her."), nên trung vị chỉ 54 từ trước ô quảng
           cáo, thấp nhất 15 từ. Google Publisher Policies cấm quảng cáo "cản trở
           việc đọc nội dung" và cấm màn hình "nhiều quảng cáo hơn nội dung" —
           chèn sau 15 từ là rơi thẳng vào đó.
           Ngưỡng 400 từ cho người đọc vào hẳn mạch truyện trước. Chương ngắn hơn
           600 từ thì không chèn ô nào giữa bài; vẫn còn ô cuối chương. --}}
      @if (! ($adShown ?? false) && ($wordsSoFar ?? 0) >= 400 && $totalWords > 600)
        @include('public.partials.ad')
        @php $adShown = true; @endphp
      @endif
    @endforeach

    @if ($paragraphs === [])
      <p class="empty">This chapter has no text yet.</p>
    @endif
  </article>

  @include('public.partials.reaction')

  {{-- Thẻ "chương sau" to, ngay dưới khối đánh giá: vừa hết chương là thấy lối đi tiếp.
       Hết truyện thì mời sang truyện khác. Ô quảng cáo đứng SAU cả cụm điều hướng, không
       chen giữa: quảng cáo sát nút bấm dễ bị bấm nhầm, và AdSense phạt đúng kiểu đó. --}}
  @if ($next)
    @php $nextTitle = $next->cleanTitle(); @endphp
    <a class="next-card" href="{{ route('public.chapter', [$story, $next->number]) }}" rel="next">
      <span>
        <small>Next · Chapter {{ $next->number }}</small>
        <strong>{{ $nextTitle !== '' ? $nextTitle : 'Continue the story' }}</strong>
      </span>
      <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2.4" stroke-linecap="round" stroke-linejoin="round" aria-hidden="true"><path d="M5 12h14M13 6l6 6-6 6"/></svg>
    </a>
  @else
    <a class="next-card end" href="{{ route('public.browse') }}">
      <span>
        <small>The end</small>
        <strong>Find your next story</strong>
      </span>
      <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2.4" stroke-linecap="round" stroke-linejoin="round" aria-hidden="true"><path d="M5 12h14M13 6l6 6-6 6"/></svg>
    </a>
    @if ($related->isNotEmpty())
      <section class="sec" aria-labelledby="after-title">
        <div class="sec-head"><h2 id="after-title">Read next</h2></div>
        <ul class="rank plain">
          @foreach ($related as $s)
            @include('public.partials.story-row', ['story' => $s])
          @endforeach
        </ul>
      </section>
    @endif
  @endif

  <nav class="pager" aria-label="Chapter navigation">
    @if ($prev)
      <a href="{{ route('public.chapter', [$story, $prev->number]) }}" rel="prev">‹ Chapter {{ $prev->number }}</a>
    @else
      <a href="{{ route('public.story', $story) }}">‹ Story page</a>
    @endif
    <a href="{{ route('public.story', $story) }}#chapters">Chapter list</a>
  </nav>

  @include('public.partials.ad')
</div>

{{-- Bảng chỉnh chữ. Nút "Aa" nổi ở góc để chỉnh được giữa chương mà không phải cuộn
     lên đầu. Ba lựa chọn: cỡ chữ, phông, nền — lưu ở localStorage, layout nạp lại
     trước khi trang vẽ nên chương sau mở ra đúng như đã chỉnh. --}}
<button class="fab" type="button" id="aa" aria-expanded="false" aria-controls="rset" aria-label="Reading settings">
  <span class="aa">Aa</span>
</button>
<div class="rset" id="rset" role="dialog" aria-label="Reading settings" hidden>
  <h3>Text size</h3>
  <div class="size-row">
    <button type="button" data-size="-1" aria-label="Smaller text">A</button>
    <output id="size-out">20</output>
    <button type="button" class="big" data-size="1" aria-label="Larger text">A</button>
  </div>
  <h3>Font</h3>
  <div class="seg" data-group="font">
    <button type="button" class="serif" data-value="serif">Serif</button>
    <button type="button" data-value="sans">Sans</button>
  </div>
  <h3>Page</h3>
  <div class="seg" data-group="theme">
    <button type="button" data-value="light"><span class="swatch" style="background:#fff"></span>Light</button>
    <button type="button" data-value="sepia"><span class="swatch" style="background:#F3EAD7"></span>Sepia</button>
    <button type="button" data-value="dark"><span class="swatch" style="background:#1D191B"></span>Dark</button>
  </div>
</div>

<script>
(function () {
  var root = document.documentElement;

  // Thanh tiến độ: đọc một chương 1.800 từ trên điện thoại là cuộn rất lâu, không
  // có mốc nào cho biết còn bao xa. Passive listener + requestAnimationFrame để không
  // làm khựng thao tác cuộn.
  var bar = document.getElementById('readProgress');
  var ticking = false;
  function update() {
    var max = document.documentElement.scrollHeight - innerHeight;
    bar.style.width = (max > 0 ? Math.min(1, scrollY / max) * 100 : 0) + '%';
    ticking = false;
  }
  addEventListener('scroll', function () {
    if (!ticking) { ticking = true; requestAnimationFrame(update); }
  }, { passive: true });
  addEventListener('resize', update, { passive: true });
  update();

  // Nhảy chương
  var jump = document.getElementById('jump');
  jump.addEventListener('change', function () { location.href = jump.value; });

  // Phím ← → để lật chương (bỏ qua khi đang gõ trong ô nhập)
  document.addEventListener('keydown', function (e) {
    if (e.altKey || e.ctrlKey || e.metaKey || e.shiftKey) return;
    if (/^(INPUT|TEXTAREA|SELECT)$/.test(e.target.tagName)) return;
    var rel = e.key === 'ArrowLeft' ? 'prev' : e.key === 'ArrowRight' ? 'next' : null;
    var a = rel && document.querySelector('.pager a[rel="' + rel + '"], .next-card[rel="' + rel + '"]');
    if (a) location.href = a.href;
  });

  // Ghi lịch sử đọc cho "Continue reading" ở trang chủ và nút "Continue" ở trang truyện.
  if (window.StoriesHistory) {
    var prevRead = StoriesHistory.get(@json($story->slug));
    StoriesHistory.save({
      slug: @json($story->slug),
      title: @json($story->title),
      cover: @json($story->coverSmallUrl()),
      // Js::from chứ không phải json: directive đó tách đối số theo dấu phẩy nên vỡ với route(..., [...], false).
      url: {{ Js::from(route('public.chapter', [$story, $chapter->number], false)) }},
      ch: {{ (int) $chapter->number }},
      {{-- Tên đã làm sạch (Chapter::cleanTitle): tên chỉ là số phần thì để trống, ô đọc tiếp đã ghi số chương. --}}
      chTitle: @json($chapter->cleanTitle()),
      // Chương xa nhất từng mở — để biết đã đọc hết chưa (StoriesHistory.finished).
      max: Math.max({{ (int) $chapter->number }}, prevRead ? Number(prevRead.max || prevRead.ch) || 0 : 0),
      total: {{ (int) $chapterList->count() }},
      at: Date.now()
    });
  }

  // Bảng chỉnh chữ
  var KEY = 'stories.reader', fab = document.getElementById('aa'), panel = document.getElementById('rset');
  var out = document.getElementById('size-out');
  function prefs() { try { return JSON.parse(localStorage.getItem(KEY) || '{}'); } catch (e) { return {}; } }
  function store(p) { try { localStorage.setItem(KEY, JSON.stringify(p)); } catch (e) {} }
  function currentSize() {
    return parseInt(getComputedStyle(document.querySelector('.chapter-body')).fontSize, 10) || 20;
  }
  function currentTheme() {
    var t = root.getAttribute('data-theme');
    return t || (matchMedia('(prefers-color-scheme: dark)').matches ? 'dark' : 'light');
  }
  function paint() {
    out.value = out.textContent = currentSize();
    var font = root.getAttribute('data-reader-font') === 'sans' ? 'sans' : 'serif';
    panel.querySelectorAll('[data-group="font"] button').forEach(function (b) { b.setAttribute('aria-pressed', b.dataset.value === font); });
    var theme = currentTheme();
    panel.querySelectorAll('[data-group="theme"] button').forEach(function (b) { b.setAttribute('aria-pressed', b.dataset.value === theme); });
  }
  function toggle(open) {
    panel.hidden = !open;
    fab.setAttribute('aria-expanded', open);
    if (open) paint();
  }
  fab.addEventListener('click', function (e) { e.stopPropagation(); toggle(panel.hidden); });
  document.addEventListener('click', function (e) { if (!panel.hidden && !panel.contains(e.target)) toggle(false); });
  document.addEventListener('keydown', function (e) { if (e.key === 'Escape' && !panel.hidden) { toggle(false); fab.focus(); } });
  document.addEventListener('stories:theme', paint);

  panel.addEventListener('click', function (e) {
    var b = e.target.closest('button');
    if (!b) return;
    var p = prefs();
    if (b.dataset.size) {
      p.size = Math.max(15, Math.min(28, currentSize() + Number(b.dataset.size)));
      root.style.setProperty('--rs', p.size + 'px');
      store(p);
    } else if (b.parentNode.dataset.group === 'font') {
      p.font = b.dataset.value;
      if (p.font === 'sans') root.setAttribute('data-reader-font', 'sans'); else root.removeAttribute('data-reader-font');
      store(p);
    } else if (b.parentNode.dataset.group === 'theme') {
      root.setAttribute('data-theme', b.dataset.value);
      try { localStorage.setItem('stories.theme', b.dataset.value); } catch (err) {}
    }
    paint();
  });
})();
</script>
@endsection
