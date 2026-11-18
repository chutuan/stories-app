{{--
  Trang hỗ trợ của game Tiny Seasons (iPhone/iPad): Support URL trên App Store Connect của
  game trỏ về đây. Apple đòi trang này có cách liên hệ thật. Cùng quy tắc với trang privacy
  bên cạnh: không quảng cáo, không GA4, không quảng bá app Stories ($legalPage của layout).
--}}
@extends('public.layout')
@section('title', 'Tiny Seasons Support')
@section('description', 'Help and contact for Tiny Seasons, the cozy weather puzzle game for iPhone and iPad: how to play, hints, progress and privacy.')
@section('content')
  <h1>Tiny Seasons</h1>
  <p class="updated">Cozy weather garden puzzles for iPhone and iPad</p>

  <p>Change the weather to guide little seeds across floating islands and make them bloom.
    150 handcrafted levels, no timers, no account.</p>

  <div class="card">
    <p style="margin:0;font-size:18px">
      <a href="mailto:{{ config('app.support_email') }}?subject=Tiny%20Seasons">{{ config('app.support_email') }}</a>
    </p>
    <p class="meta" style="margin:6px 0 0">
      Questions, bugs or ideas: write in English or Vietnamese. Tell us your device and the level
      number, and we usually reply within two days.
    </p>
  </div>

  <h2>How to play</h2>
  <ul>
    <li><strong>Wind</strong> moves every loose seed one tile north, east, south or west.</li>
    <li><strong>Rain</strong> turns a seed resting on soil into a bud.</li>
    <li><strong>Sun</strong> makes every bud bloom, and melts the ice.</li>
    <li><strong>Frost</strong> freezes the lake so seeds can walk across it.</li>
    <li><strong>Mushrooms</strong> grow tall in the rain and block the way, then shrink in the sun.</li>
  </ul>
  <p>Bloom a flower on every glowing soil tile, using exactly the weather cards you are given. The
    "i" button on the title screen explains every rule with a small animation.</p>

  <h2>Common questions</h2>
  <ul>
    <li><strong>I'm stuck.</strong> Tap the light bulb for the next correct card. Undo and restart
      are unlimited, and every level can be solved without hints.</li>
    <li><strong>I ran out of hints.</strong> Tap the light bulb and watch a short video for two
      more. Ads are optional and never interrupt a level.</li>
    <li><strong>My progress is gone.</strong> Progress is stored only on your device, so deleting
      the game or moving to a new phone starts it again.</li>
    <li><strong>Sound or music.</strong> Turn either off with the gear on the title screen.</li>
  </ul>

  <h2>Privacy</h2>
  <p>No account, no tracking, and the game itself collects nothing. Read the
    <a href="{{ route('public.tiny-seasons.privacy') }}">Tiny Seasons Privacy Policy</a>.</p>
@endsection
