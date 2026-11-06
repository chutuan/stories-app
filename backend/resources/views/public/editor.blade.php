@extends('public.layout')

{{-- Trang người biên tập: byline "Edited by …" trên mọi truyện/chương trỏ về đây.
     CHỈ ghi điều đúng — không thêm tiểu sử, nơi ở, nghề nghiệp nếu Tuấn chưa xác nhận.
     Không dùng đại từ he/she cho người biên tập: gọi bằng tên. --}}
@section('title', config('app.editor_name').', editor')
@section('description', config('app.editor_name').' runs '.config('app.site_name').' and decides what is published on it. What the editor does, what the AI tools do, and how to get in touch.')

@push('head')
@foreach ($jsonLd as $block)
<script type="application/ld+json">{!! $block !!}</script>
@endforeach
@endpush

@section('content')
  <p class="crumbs">
    <a href="{{ route('public.home') }}">Home</a> ›
    <a href="{{ route('public.about') }}">About</a> ›
    {{ config('app.editor_name') }}
  </p>

  <h1>{{ config('app.editor_name') }}</h1>
  <p class="updated">Editor of {{ config('app.site_name') }}</p>

  <p>{{ config('app.editor_name') }} runs {{ config('app.site_name') }} — this website and the Stories app for iPhone and iPad
    — and is the person responsible for everything published here. Every story page says
    <em>Edited by {{ config('app.editor_name') }}</em> and links to this page, so you know who
    that is and what it means.</p>

  <h2>What the editor does</h2>
  <ul>
    <li><strong>Sets the brief.</strong> The genre, the length, the shape of the story and how
      it ends are decided before anything is drafted.</li>
    <li><strong>Decides what is published.</strong> Drafts that fail the checks in our
      <a href="{{ route('public.editorial') }}">editorial standards</a> are rewritten or dropped,
      and some finished stories are kept off this website altogether.</li>
    <li><strong>Fixes what readers report.</strong> Corrections, continuity slips and takedown
      requests sent to the contact address are handled by the editor directly.</li>
  </ul>

  <h2>What the editor does not do</h2>
  <p>The stories are drafted with the help of AI writing tools; they are not typed out line by
    line by hand. "Edited by" means exactly that: the brief, the choice of what goes out, the
    checks and the responsibility belong to a person, and the first draft of the prose does not.
    Cover art is AI-generated too, and where a story has narration in the app, the voice is
    synthesised speech.</p>

  <h2>Where else these stories appear</h2>
  <p>Some stories here first appeared on other blogs that {{ config('app.editor_name') }} also
    runs. This site is now their main home. Where a story is still published on one of those
    blogs as well, only one of the two copies is left open to search engines, so nobody finds
    the same story twice.</p>

  @if ($latest->isNotEmpty())
    <section class="sec" aria-labelledby="latest-title">
      <div class="sec-head"><h2 id="latest-title">Recently published</h2></div>
      <ul class="rank plain">
        @foreach ($latest as $s)
          @include('public.partials.story-row', ['story' => $s])
        @endforeach
      </ul>
      <p class="meta"><a href="{{ route('public.browse') }}">All {{ $storyCount }} stories →</a></p>
    </section>
  @endif

  <h2>Contact</h2>
  <p><a href="mailto:{{ config('app.support_email') }}">{{ config('app.support_email') }}</a>
    — read by the editor. What to include so it can be answered quickly is on the
    <a href="{{ route('public.contact') }}">contact page</a>.</p>
@endsection
