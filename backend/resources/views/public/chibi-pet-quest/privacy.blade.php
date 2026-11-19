{{--
  Chính sách quyền riêng tư của game Chibi Pet Quest (iPhone), một app khác của cùng người
  phát hành, chỉ mượn chỗ trên tunastory.com. Thông điệp GDPR trong AdMob và App Store Connect
  của game trỏ Privacy Policy URL về trang này, nên đừng đổi đường dẫn. Nội dung phải khớp
  code quảng cáo của game (repo Chibi Pet Quest, scripts/Ads.gd): Google UMP rồi ATT rồi mới
  chạy Google Mobile Ads SDK. Khác Tiny Seasons: game này CÓ xin quyền ATT, nên quảng cáo có
  thể được cá nhân hoá khi người chơi cho phép. Đổi game mà đổi dữ liệu thì sửa cả hai nơi.
  Không quảng cáo, không GA4, không quảng bá app Stories: route nằm trong $legalPage của layout.
--}}
@extends('public.layout')
@section('title', 'Chibi Pet Quest Privacy Policy')
@section('description', 'What the Chibi Pet Quest puzzle game for iPhone does with data: progress stays on your device; only Google\'s ad SDK sends data, for the optional video ads.')
@section('content')
  <p class="crumbs">
    <a href="{{ route('public.chibi-pet-quest') }}">Chibi Pet Quest</a> ›
    Privacy Policy
  </p>

  <h1>Chibi Pet Quest Privacy Policy</h1>
  <p class="updated">Last updated: 19 November 2026</p>

  <p>This policy explains what the <strong>Chibi Pet Quest</strong> puzzle game for iPhone does
    with data. Chibi Pet Quest is made by Tuan Chu Duc, who also publishes {{ config('app.site_name') }}.
    The game itself collects nothing about you; the only data that leaves your device is what
    Google's advertising SDK sends to Google to show the optional video ads.</p>

  <h2>No account, no sign-up</h2>
  <p>Chibi Pet Quest does not ask you to register or log in and never asks for your name, email
    address or phone number. We run no server for the game, we do not build a profile of you and
    we do not sell data to anyone.</p>

  <h2>What stays on your device</h2>
  <p>The game stores the following only on your iPhone. It is never sent to us:</p>
  <ul>
    <li>which levels you have unlocked and completed</li>
    <li>how many hints you have left</li>
    <li>which beginner tips you have already seen</li>
    <li>whether sound is on or off</li>
  </ul>
  <p>Uninstalling the game deletes all of it. It is not backed up to us and cannot be restored.</p>

  <h2>Advertising</h2>
  <p>When you run out of hints you can choose to watch a short video ad to get two more. These ads
    are served by Google AdMob. Google's Mobile Ads SDK runs inside the game and sends data directly
    to Google so that Google can serve and measure ads, prevent fraud and analyse how its SDK
    performs. According to Google, that data is:</p>
  <ul>
    <li><strong>Device identifiers</strong> — an identifier scoped to the app or to the developer
      and, only if you allow tracking (see below), your device's advertising identifier.</li>
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
  <p>Watching an ad is always your choice: ads never interrupt a level, and every level can be
    solved without hints.</p>

  <h2>Consent and tracking permission</h2>
  <p>Before any ad is loaded, the game asks for your choices:</p>
  <ul>
    <li><strong>Europe, the United Kingdom and Switzerland.</strong> The first time you open the
      game, Google's consent form appears, as the law requires. You can consent, decline, or pick
      purposes one by one under "Manage options". If you decline, Google shows only
      non-personalised or limited ads.</li>
    <li><strong>App Tracking Transparency.</strong> iOS then asks whether Chibi Pet Quest may track
      you across apps and websites owned by other companies. If you tap "Allow", Google may use your
      device's advertising identifier to show ads that fit you better and to measure them. If you
      tap "Ask App Not to Track", the advertising identifier is never available to the game or to
      Google, and ads are not personalised with it.</li>
  </ul>
  <p>You can change the tracking choice at any time in iOS Settings › Privacy &amp; Security ›
    Tracking, or in Settings › Chibi Pet Quest. To see the European consent form again, delete and
    reinstall the game.</p>

  <h2>What the game does not use</h2>
  <p>Apart from the Google Mobile Ads SDK described above, Chibi Pet Quest contains no analytics,
    crash-reporting, social, login or other third-party SDK, and it does not use the camera,
    microphone, photos, contacts or location services.</p>

  <h2>Who else receives data</h2>
  <p>Only Google, for advertising as described above, under the
    <a href="https://policies.google.com/privacy" rel="noopener">Google Privacy Policy</a>.
    We share data with no one else and never sell it.</p>

  <h2>Children</h2>
  <p>Chibi Pet Quest is a gentle puzzle game suitable for all ages, but it is not designed for or
    directed at children under 13, and we do not knowingly collect personal information from
    them. If you believe a child has used the game and want data removed, contact us below.</p>

  <h2>Your choices</h2>
  <ul>
    <li>Delete everything the game stores by uninstalling it; that also stops all ad data
      collection.</li>
    <li>Turn tracking off at any time in iOS Settings › Privacy &amp; Security › Tracking.</li>
    <li>Ask us a question, or ask us to delete data, at
      <a href="mailto:{{ config('app.support_email') }}">{{ config('app.support_email') }}</a>.</li>
  </ul>

  <h2>Changes</h2>
  <p>If this policy changes we will update the date at the top of this page.</p>
@endsection
