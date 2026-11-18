{{--
  Chính sách quyền riêng tư của game Tiny Seasons (iPhone/iPad), một app khác của cùng
  người phát hành, chỉ mượn chỗ trên tunastory.com. App Store Connect của Tiny Seasons
  trỏ Privacy Policy URL về trang này. Nội dung phải khớp khai báo App Privacy của game
  (repo Tiny Seasons, store/APP_STORE.md): chỉ Google Mobile Ads SDK thu dữ liệu, không gắn
  danh tính, không tracking. Đổi game mà đổi dữ liệu thì sửa cả hai nơi.
  Không quảng cáo, không GA4, không quảng bá app Stories: route nằm trong $legalPage của layout.
--}}
@extends('public.layout')
@section('title', 'Tiny Seasons Privacy Policy')
@section('description', 'What the Tiny Seasons puzzle game for iPhone and iPad does with data: nothing leaves your device except what Google\'s ad SDK needs for the optional video ads.')
@section('content')
  <p class="crumbs">
    <a href="{{ route('public.tiny-seasons') }}">Tiny Seasons</a> ›
    Privacy Policy
  </p>

  <h1>Tiny Seasons Privacy Policy</h1>
  <p class="updated">Last updated: 18 November 2026</p>

  <p>This policy explains what the <strong>Tiny Seasons</strong> puzzle game for iPhone and iPad
    does with data. Tiny Seasons is made by Tuan Chu Duc, who also publishes {{ config('app.site_name') }}.
    The game itself collects nothing about you; the only data that leaves your device is what
    Google's advertising SDK sends to Google to show the optional video ads.</p>

  <h2>No account, no sign-up</h2>
  <p>Tiny Seasons does not ask you to register or log in and never asks for your name, email
    address or phone number. We run no server for the game, we do not build a profile of you and
    we do not sell data to anyone.</p>

  <h2>What stays on your device</h2>
  <p>The game stores the following only on your phone or tablet. It is never sent to us:</p>
  <ul>
    <li>which levels you have solved and the stars you earned</li>
    <li>how many hints you have left</li>
    <li>your sound and music settings</li>
  </ul>
  <p>Uninstalling the game deletes all of it. It is not backed up to us and cannot be restored.</p>

  <h2>Advertising</h2>
  <p>When you run out of hints you can choose to watch a short video ad to get two more. These ads
    are served by Google AdMob. Google's Mobile Ads SDK runs inside the game and sends data directly
    to Google so that Google can serve and measure ads, prevent fraud and analyse how its SDK
    performs. According to Google, that data is:</p>
  <ul>
    <li><strong>Device identifiers</strong> — an identifier scoped to the app or to the developer.
      The game never asks for tracking permission, so on iOS your advertising identifier is not
      available to it.</li>
    <li><strong>Approximate location</strong> — estimated by Google from your IP address, at the
      level of a country or city. The game never asks for or reads your precise location.</li>
    <li><strong>Advertising data</strong> — which ads were shown, and whether they were watched or
      tapped.</li>
    <li><strong>Product interaction</strong> — such as app launches and taps on ads.</li>
    <li><strong>Diagnostics</strong> — crash logs, performance data and other technical data about
      how the ad SDK is running.</li>
  </ul>
  <p>All of this happens inside Google's SDK. We do not receive or store any of it; we only see
    Google's aggregated reports of ad earnings. Google's practices are described in
    <a href="https://policies.google.com/technologies/partner-sites" rel="noopener">How Google uses
    information from sites or apps that use our services</a>.</p>
  <p>Every ad is requested in <strong>non-personalised</strong> mode and with a content rating
    suitable for all ages. We do not track you across other apps or websites, so the game never
    asks for App Tracking Transparency permission. Watching an ad is always your choice: ads never
    interrupt a level, and every level can be solved without hints.</p>
  <p>In the European Economic Area, the United Kingdom and Switzerland, Google's consent form
    appears the first time you open the game, as the law requires. Deleting and reinstalling the
    game shows it again, so you can change your choice.</p>

  <h2>What the game does not use</h2>
  <p>Apart from the Google Mobile Ads SDK described above, Tiny Seasons contains no analytics,
    crash-reporting, social, login or other third-party SDK, and it does not use the camera,
    microphone, photos, contacts or location services.</p>

  <h2>Who else receives data</h2>
  <p>Only Google, for advertising as described above, under the
    <a href="https://policies.google.com/privacy" rel="noopener">Google Privacy Policy</a>.
    We share data with no one else and never sell it.</p>

  <h2>Children</h2>
  <p>Tiny Seasons is a puzzle game suitable for all ages, but it is not designed for or directed
    at children under 13, and we do not knowingly collect personal information from them. Ads are
    requested with a content rating suitable for all ages and are never personalised.</p>

  <h2>Your choices</h2>
  <ul>
    <li>Delete everything the game stores by uninstalling it; that also stops all ad data
      collection.</li>
    <li>On iOS the game never asks for tracking permission, so your advertising identifier is never
      available to it.</li>
    <li>Ask us a question, or ask us to delete data, at
      <a href="mailto:{{ config('app.support_email') }}">{{ config('app.support_email') }}</a>.</li>
  </ul>

  <h2>Changes</h2>
  <p>If this policy changes we will update the date at the top of this page.</p>
@endsection
