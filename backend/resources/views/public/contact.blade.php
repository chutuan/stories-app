@extends('public.layout')

@section('title', 'Contact Stories')
@section('description', 'How to reach the people who publish Stories — corrections, takedown requests, story suggestions, privacy questions and advertising enquiries.')

@push('head')
@foreach ($jsonLd as $block)
<script type="application/ld+json">{!! $block !!}</script>
@endforeach
@endpush

@section('content')
  <p class="crumbs">
    <a href="{{ route('public.home') }}">Home</a> ›
    Contact
  </p>

  <h1>Contact</h1>
  <p class="updated">One address, and a real person reads it.</p>

  <div class="card">
    <p style="margin:0;font-size:18px">
      <a href="mailto:{{ config('app.support_email') }}">{{ config('app.support_email') }}</a>
    </p>
    <p class="meta" style="margin:6px 0 0">
      Write in English or Vietnamese. We read every message and reply to anything that needs a
      reply, usually within a few working days.
    </p>
  </div>

  <h2>What to write about</h2>
  <ul>
    <li><strong>Corrections.</strong> A typo, a name that changes spelling mid-story, chapters in
      the wrong order — send the link to the chapter and we will fix it. Corrections are the
      messages we act on fastest.</li>
    <li><strong>Copyright and takedown.</strong> If you believe something published here
      infringes your rights, tell us which page, what the material is, and what right you hold.
      We answer every claim that identifies a specific page.</li>
    <li><strong>Stories republished elsewhere.</strong> Everything here was written for this site
      and belongs to us. If you find one of our stories on another site or as a video, we want
      the link — see the <a href="{{ route('public.terms') }}">Terms of Service</a>.</li>
    <li><strong>What to write next.</strong> Requests for a genre, a setting or a kind of ending
      are welcome. They do shape what we commission.</li>
    <li><strong>Privacy.</strong> The site has no accounts, so there is no profile to export or
      delete. What the ads and analytics do collect is set out in the
      <a href="{{ route('public.privacy') }}">Privacy Policy</a>; ask us anything about it.</li>
    <li><strong>Advertising and business.</strong> Same address.</li>
    <li><strong>Something is broken.</strong> On the website or in the mobile app. Tell us the
      page or screen, the device and the browser — it turns a guess into a fix.</li>
  </ul>

  <h2>What helps us answer faster</h2>
  <p>The link to the page you are writing about, what you expected to happen, and what happened
    instead. A screenshot never hurts.</p>

  <h2>Who publishes Stories</h2>
  {{-- Tên và địa điểm lấy từ config: PUBLISHER_LOCATION bỏ trống thì mệnh đề
       "based in ..." biến mất hoàn toàn. Đừng viết cứng một địa chỉ ở đây — khai
       sai nơi đặt trụ sở là lỗi khai gian thông tin website, nặng hơn không khai. --}}
  <p>This site is published by <strong>{{ config('app.publisher_name') }}</strong>@if (filled(config('app.publisher_location'))), based in
    {{ config('app.publisher_location') }}@endif. The same library is also a mobile app.
    How the stories are commissioned, checked and corrected is written up in our
    <a href="{{ route('public.editorial') }}">editorial standards</a>; who we are and how the
    site pays for itself is on the <a href="{{ route('public.about') }}">About page</a>.</p>
@endsection
