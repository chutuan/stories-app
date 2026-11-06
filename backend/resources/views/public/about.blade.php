@extends('public.layout')

@section('title', 'About '.config('app.site_name'))
@section('description', 'What '.config('app.site_name').' is, who runs it, how the stories are made, and how the site pays for itself.')

@push('head')
@foreach ($jsonLd as $block)
<script type="application/ld+json">{!! $block !!}</script>
@endforeach
@endpush

@section('content')
  <h1>About {{ config('app.site_name') }}</h1>
  <p class="updated">Short serialised fiction, free to read on the web.</p>

  <p>{{ config('app.site_name') }} publishes short serials you can finish in one sitting — drama about people who
    are underestimated, betrayed or written off, and what happens when the truth comes out.
    Right now the library holds <strong>{{ $storyCount }} stories</strong> across
    <strong>{{ $chapterCount }} chapters</strong>, about
    <strong>{{ number_format($wordCount) }} words</strong> in all. Every chapter is readable
    here for free, with no account and no payment.</p>

  <h2>What you will find</h2>
  <ul>
    {{-- Câu cũ "Each serial … reaches an actual ending" sai với các truyện còn 'ongoing'
         (17 truyện gốc dừng ở chương 3–6 từ tháng 9/2026). Số lấy động. --}}
    <li><strong>Complete stories, not teasers.</strong> Most serials run 3–10 chapters and
      reach an actual ending.@if ($ongoingCount > 0) The {{ $ongoingCount }} still being written
      are marked <em>Ongoing</em> until their last chapter is out.@endif Nothing is cut off to
      make you pay.</li>
    {{-- Trước đây câu này viết "you can listen from the reading screen" trong mục
         mô tả CHÍNH TRANG WEB, nơi không có lấy một thẻ <audio> nào. Đúng cho app,
         sai cho web. Với site đang xin AdSense thì mô tả sai là thứ không nên để. --}}
    <li><strong>Narrated audio on {{ $audioCount }} of them.</strong> Where a story has audio,
      every chapter has it. Listening is in the mobile app for now — on the web these stories
      are text.</li>
    <li><strong>A reader built for phones.</strong> Most people arrive on a phone, so the
      reading column, type size and line spacing are tuned for that first.</li>
  </ul>

  <h2>Who runs {{ config('app.site_name') }}</h2>
  <p>{{ config('app.site_name') }} is run by <a href="{{ route('public.editor') }}">{{ config('app.editor_name') }}</a>,
    who sets the brief for each story, decides what is published and reads the contact address.
    The line <em>Edited by {{ config('app.editor_name') }}</em> on every story page refers to
    that person — a real name, not a pen name.</p>

  <h2>How the stories are made</h2>
  <p>We would rather tell you than have you guess. Stories here are drafted with the help of AI
    writing tools to a brief we set, then checked and approved before publication. Cover art is
    AI-generated; where a story is narrated, the voice is synthesised speech reading our own
    text.</p>
  <p>Nothing here is scraped or copied from anyone else: every story is our own. Some first
    appeared on other blogs we run. This site is now their main home, and where a story is still
    published on one of those blogs as well, only one of the two copies is left open to search
    engines. The full version — what we check before a chapter goes out, what we refuse to
    publish, and how corrections work — is in our
    <a href="{{ route('public.editorial') }}">editorial standards</a>; the legal wording is in
    the <a href="{{ route('public.terms') }}">Terms of Service</a>.</p>

  <h2>How the site pays for itself</h2>
  <p>Advertising, and nothing else. There is no subscription, no paywall and nothing to buy.
    We do not sell data — we do not hold any to sell, because the site has no accounts and
    asks for nothing about you. What the ads and analytics on this site do collect is set out
    in the <a href="{{ route('public.privacy') }}">Privacy Policy</a>.</p>

  <h2>There is also an app</h2>
  <p>The same library exists as a mobile app, with offline-friendly reading settings, saved
    stories and audio. The app works differently from this website: there, later chapters are
    unlocked with coins you earn for free. On the web, everything is simply open.</p>

  <h2>Contact</h2>
  <p>Questions, corrections, takedown requests or content suggestions — one address, and a
    real person reads it:
    <a href="mailto:{{ config('app.support_email') }}">{{ config('app.support_email') }}</a>.
    What to include so we can answer quickly is on the
    <a href="{{ route('public.contact') }}">contact page</a>.</p>

  {{-- Câu cũ "Two stories are open end to end with full audio" viết cứng: danh sách là
       truyện miễn phí trọn vẹn TRONG APP (free_chapters ≥ số chương), số lượng và việc có
       audio hay không đều thay đổi. Không hứa gì ngoài cái danh sách. --}}
  <div class="card">
    <h2 style="margin-top:0">Start reading</h2>
    @if ($freeStories->isNotEmpty())
      <p>Not sure where to begin? Try one of these:</p>
      <ul>
        @foreach ($freeStories as $story)
          <li><a href="{{ route('public.story', $story) }}">{{ $story->title }}</a>
            — {{ $story->chapters_count }} {{ Str::plural('chapter', $story->chapters_count) }}</li>
        @endforeach
      </ul>
    @endif
    <p class="meta"><a href="{{ route('public.browse') }}">Browse the whole library →</a></p>
  </div>
@endsection
