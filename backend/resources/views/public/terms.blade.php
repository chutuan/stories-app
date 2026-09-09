@extends('public.layout')
@section('title', 'Terms of Service')
@section('description', 'The rules for using the Stories app.')
@section('content')
  <h1>Terms of Service</h1>
  <p class="updated">Last updated: {{ config('app.legal_updated') }}</p>

  <p>By installing or using the <strong>Stories</strong> app you agree to these terms. If you do not
    agree, please do not use the app.</p>

  <h2>The service</h2>
  <p>Stories provides serialised fiction for reading and, where a narrated version exists, listening.
    We may add, change or remove stories, chapters and features at any time.</p>

  <h2>Coins</h2>
  <p>Coins are a virtual item used inside the app to unlock chapters. Please note:</p>
  <ul>
    <li>Coins <strong>cannot be bought with money</strong> — you earn them by checking in and by
      watching rewarded ads.</li>
    <li>Coins have <strong>no monetary value</strong>, cannot be exchanged for cash, and cannot be
      transferred to another person or device.</li>
    <li>Coins and unlocked chapters are stored on your device. If you uninstall the app, change
      device or clear its data, <strong>they are lost and cannot be restored</strong>.</li>
    <li>We may change how many coins a chapter costs or an ad rewards.</li>
  </ul>

  <h2>Acceptable use</h2>
  <ul>
    <li>Do not copy, republish or redistribute the stories.</li>
    <li>Do not attempt to bypass chapter locks, tamper with the app, or interfere with our servers.</li>
    <li>Do not use automated tools to bulk-download content.</li>
  </ul>

  <h2>Content</h2>
  <p>All stories in the app are works of fiction. Names, characters, businesses and events are
    products of the imagination; any resemblance to real people or companies is coincidental.
    Content in the app remains the property of its respective rights holders.</p>

  <h2>Advertising</h2>
  <p>The app is supported by ads. We do not control which specific ads Google serves and are not
    responsible for the content of third-party advertisements or the sites they link to.</p>

  <h2>Availability and warranty</h2>
  <p>The app is provided "as is". We do not guarantee that it will be uninterrupted or error-free,
    and we are not liable for lost coins, lost reading progress, or damages arising from use of the
    app, to the extent permitted by law.</p>

  <h2>Contact</h2>
  <p><a href="mailto:{{ config('app.support_email') }}">{{ config('app.support_email') }}</a></p>
@endsection
