@extends('public.layout')

@section('title', 'Editorial standards')
@section('description', 'How stories on this site are commissioned, drafted, checked before publication and corrected afterwards — and what we will not publish.')

@push('head')
@foreach ($jsonLd as $block)
<script type="application/ld+json">{!! $block !!}</script>
@endforeach
@endpush

@section('content')
  <p class="crumbs">
    <a href="{{ route('public.home') }}">Home</a> ›
    Editorial standards
  </p>

  <h1>Editorial standards</h1>
  <p class="updated">How a story gets here, what we check before it goes out, and what we
    refuse to publish. Last updated: {{ config('app.legal_updated') }}</p>

  <p>Every publisher owes readers a plain answer to one question: where did this come from?
    Here is ours, in full, including the parts that are easier to leave vague.</p>

  <h2>How a story is made</h2>
  <ol>
    <li><strong>The brief.</strong> We decide the genre, the length, the shape of the arc and
      the ending before a word is written. Serials here run 3–10 chapters because that is a
      story you can finish in one sitting, not because a longer one would carry more ads.</li>
    <li><strong>The draft.</strong> Stories are drafted with the help of AI writing tools
      against that brief. We say so on every story page, on the
      <a href="{{ route('public.about') }}">About page</a> and in the
      <a href="{{ route('public.terms') }}">Terms</a>. We would rather tell you than have you
      work it out.</li>
    <li><strong>The read-through.</strong> Every chapter is read and approved by us before it
      is published. A draft that fails the checks below is rewritten or dropped — not
      published and patched later.</li>
    <li><strong>Publication.</strong> Chapters go out in order under a fixed URL. Nothing is
      held back behind a payment on this website: every chapter is free to read here.</li>
  </ol>

  <h2>What we check before publishing</h2>
  <ul>
    <li><strong>It is a story, and it ends.</strong> No serial is cut off to force you
      somewhere else, and no chapter is padded to hit a word count.</li>
    <li><strong>Continuity holds.</strong> Names, ages, jobs, places and the timeline have to
      survive from the first chapter to the last.</li>
    <li><strong>Nobody real is in it.</strong> Characters, companies and events are invented.
      Any resemblance to a living person or a real business is coincidence, not material.</li>
    <li><strong>Nothing is copied.</strong> Every story was written for this site. Nothing here
      is scraped, spun, translated from someone else's work or republished from another
      site.</li>
    <li><strong>It reads on a phone.</strong> Most readers arrive on one, so paragraph length
      and chapter length are judged on a phone screen.</li>
  </ul>

  <h2>What we will not publish</h2>
  <ul>
    <li>Sexual content involving minors, in any form, under any framing.</li>
    <li>Hate, slurs or degradation aimed at a real group of people.</li>
    <li>Named real people placed in invented situations that would damage them.</li>
    <li>Medical, legal or financial instruction dressed up as fiction.</li>
    <li>Chapters that exist only to carry an ad — recaps of the previous chapter, filler scenes,
      or a serial stretched past its ending.</li>
  </ul>

  <h2>Pen names</h2>
  <p>Author names such as Vivian Pryce, Nora Calloway or Marin Halloway are
    <strong>house pen names</strong>. They are not real people and do not stand for anyone
    living. Pen names are ordinary in fiction; hiding the fact that they are pen names is not,
    so we say it on the story page itself and not only here.</p>

  <h2>Cover art and narration</h2>
  <p>Cover images are generated with an image model from a brief written against each story,
    then reviewed by us. Where a story is narrated, the voice is synthesised speech reading our
    own text — no human narrator is credited or implied. If we change the words of a narrated
    chapter, the old narration is deleted automatically and re-recorded, so the audio can never
    drift away from the text on the page.</p>

  <h2>Corrections</h2>
  <p>Send a correction to
    <a href="mailto:{{ config('app.support_email') }}">{{ config('app.support_email') }}</a> —
    see the <a href="{{ route('public.contact') }}">contact page</a> for what to include.
    Typos and continuity slips are fixed in place. A change that alters what actually happens
    in a published story is a change to the story, so we say so in the chapter rather than
    quietly swapping it.</p>

  <h2>Advertising does not touch the stories</h2>
  <p>Advertising is the only thing that pays for this site — there is no subscription, no
    paywall and nothing to buy. Advertisers have no say in what we commission or publish, and
    no story, chapter or character is paid placement.</p>
  <p>Where ads appear is an editorial decision too, so it is written down: a chapter carries at
    most one ad inside the text, it appears only after at least 400 words of reading, and
    chapters shorter than 600 words carry none inside the text at all. Nothing is placed where
    it interrupts a scene you are in the middle of.</p>

  <h2>Reader feedback</h2>
  <p>Every chapter ends with a one-tap satisfaction scale. It is anonymous, it takes a second,
    and we read the scores — stories that readers abandon at chapter three tell us more than
    any of our own opinions about the brief.</p>

  <p class="meta">Questions about any of this:
    <a href="mailto:{{ config('app.support_email') }}">{{ config('app.support_email') }}</a>.</p>
@endsection
