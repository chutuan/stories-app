@extends('public.layout')

@section('title', 'About Stories')
@section('description', 'Who runs Stories, what you will find here, how the stories are made, and how the site pays for itself.')

@push('head')
@foreach ($jsonLd as $block)
<script type="application/ld+json">{!! $block !!}</script>
@endforeach
@endpush

@section('content')
  <h1>About Stories</h1>
  <p class="updated">Short serialised fiction, free to read on the web.</p>

  <p>Stories publishes short serials you can finish in one sitting — drama about people who
    are underestimated, betrayed or written off, and what happens when the truth comes out.
    Right now the library holds <strong>{{ $storyCount }} stories</strong> across
    <strong>{{ $chapterCount }} chapters</strong>, about
    <strong>{{ number_format($wordCount) }} words</strong> in all. Every chapter is readable
    here for free, with no account and no payment.</p>

  <h2>What you will find</h2>
  <ul>
    <li><strong>Complete stories, not teasers.</strong> Each serial runs 3–10 chapters and
      reaches an actual ending. Nothing is cut off to make you pay.</li>
    <li><strong>Narrated audio on {{ $audioCount }} of them.</strong> Where a story has audio,
      every chapter has it, and you can listen from the reading screen.</li>
    <li><strong>A reader built for phones.</strong> Most people arrive on a phone, so the
      reading column, type size and line spacing are tuned for that first.</li>
  </ul>

  <h2>How the stories are made</h2>
  <p>We would rather tell you than have you guess. Stories here are drafted with the help of AI
    writing tools to a brief we set, then read and approved before publication. Author names
    such as Vivian Pryce or Marin Halloway are <strong>house pen names</strong> — they are not
    real people. Cover art is AI-generated; where a story is narrated, the voice is synthesised
    speech reading our own text.</p>
  <p>Nothing here is scraped, spun or copied from another site. Every story was written for
    this site and belongs to us. The longer version of this is in the
    <a href="{{ route('public.terms') }}">Terms of Service</a>.</p>

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
    <a href="mailto:{{ config('app.support_email') }}">{{ config('app.support_email') }}</a>.</p>

  <div class="card">
    <h2 style="margin-top:0">Start reading</h2>
    <p>Two stories are open end to end with full audio, if you want to see what this is like
      before anything else:</p>
    <ul>
      @foreach ($freeStories as $story)
        <li><a href="{{ route('public.story', $story) }}">{{ $story->title }}</a>
          — {{ $story->chapters_count }} chapters</li>
      @endforeach
    </ul>
    <p class="meta"><a href="{{ route('public.browse') }}">Browse the whole library →</a></p>
  </div>
@endsection
