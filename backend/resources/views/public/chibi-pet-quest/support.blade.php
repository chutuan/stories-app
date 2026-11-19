{{--
  Trang hỗ trợ của game Chibi Pet Quest (iPhone): Support URL trên App Store Connect của game
  trỏ về đây. Apple đòi trang này có cách liên hệ thật. Cùng quy tắc với trang privacy bên
  cạnh: không quảng cáo, không GA4, không quảng bá app Stories ($legalPage của layout).
--}}
@extends('public.layout')
@section('title', 'Chibi Pet Quest Support')
@section('description', 'Help and contact for Chibi Pet Quest, the cozy pet puzzle game for iPhone: how to play, hints, progress and privacy.')
@section('content')
  <h1>Chibi Pet Quest</h1>
  <p class="updated">Cozy pet puzzles for iPhone</p>

  <p>Guide three little pets through Sunny Park and Bubble Beach. Each pet has its own skill, and
    every level needs them to work together. No timers, no account.</p>

  <div class="card">
    <p style="margin:0;font-size:18px">
      <a href="mailto:{{ config('app.support_email') }}?subject=Chibi%20Pet%20Quest">{{ config('app.support_email') }}</a>
    </p>
    <p class="meta" style="margin:6px 0 0">
      Questions, bugs or ideas: write in English or Vietnamese. Tell us your device and the level
      number, and we usually reply within two days.
    </p>
  </div>

  <h2>How to play</h2>
  <ul>
    <li><strong>Mochi</strong> climbs trees and ladders.</li>
    <li><strong>Bunny</strong> jumps across gaps.</li>
    <li><strong>Bobo</strong> digs and pushes heavy things.</li>
  </ul>
  <p>Tap a pet's portrait to choose it, then tap where it should go or what it should use. Bring
    every pet to the goal to finish the level. The first levels show a short tip and point at what
    to tap.</p>

  <h2>Common questions</h2>
  <ul>
    <li><strong>I'm stuck.</strong> Tap the light bulb for a hint. Undo is always there, and every
      level can be solved without hints.</li>
    <li><strong>I ran out of hints.</strong> Tap the light bulb and watch a short video for two
      more. Ads are optional and never interrupt a level.</li>
    <li><strong>My progress is gone.</strong> Progress is stored only on your device, so deleting
      the game or moving to a new phone starts it again.</li>
    <li><strong>Sound and music.</strong> Pause the level and tap the speaker button to turn them off or on.</li>
  </ul>

  <h2>Privacy</h2>
  <p>No account, and the game itself collects nothing. Read the
    <a href="{{ route('public.chibi-pet-quest.privacy') }}">Chibi Pet Quest Privacy Policy</a>.</p>
@endsection
