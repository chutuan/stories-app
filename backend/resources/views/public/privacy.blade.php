@extends('public.layout')
@section('title', 'Privacy Policy')
@section('description', 'How the Stories app handles your data.')
@section('content')
  <h1>Privacy Policy</h1>
  <p class="updated">Last updated: {{ config('app.legal_updated') }}</p>

  <p>This policy explains what the <strong>Stories</strong> mobile app and the
    <strong>tunastory.com</strong> website do with data. We have kept it short because the app
    deliberately collects very little.</p>

  <h2>No account, no sign-up</h2>
  <p>Stories does not ask you to register, log in, or provide a name, email address or phone number.
    We do not build a profile of you and we do not sell data to anyone.</p>

  <h2>What stays on your device</h2>
  <p>The following is stored only in the app's local storage on your phone. It is never transmitted
    to our servers:</p>
  <ul>
    <li>Your coin balance</li>
    <li>Which chapters you have unlocked</li>
    <li>Stories you saved to your library</li>
    <li>Reading preferences — font size, line spacing, brightness and page theme</li>
    <li>Daily check-in and rewarded-ad progress</li>
  </ul>
  <p>Because this data lives only on your device, uninstalling the app deletes it permanently.
    It is not backed up to us and cannot be restored or transferred to another device.</p>

  <h2>What our servers receive</h2>
  <p>The app only reads from our API — it never uploads personal information. When it fetches a
    story list, a chapter or a cover image, our server receives what any web server receives:</p>
  <ul>
    <li>IP address, device/browser user agent and the time of the request, written to standard
      server logs and kept for a limited period for security and troubleshooting</li>
    <li>An anonymous, aggregate view counter on each story — a total number, not tied to any person
      or device</li>
  </ul>

  <h2>Advertising</h2>
  <p>Stories shows banner ads and optional rewarded video ads through
    <strong>Google AdMob</strong>. To serve and measure ads, Google's SDK may access device
    identifiers such as your advertising ID. This happens inside Google's SDK — we do not receive
    or store those identifiers.</p>
  <ul>
    <li>Google's practices are described in
      <a href="https://policies.google.com/technologies/partner-sites" rel="noopener">How Google uses
      information from sites or apps that use our services</a>.</li>
    <li>Ads are always requested in non-personalised mode. We do not track you across other
      apps or websites, so the app never asks for App Tracking Transparency permission.</li>
    <li>On Android you can reset or delete your advertising ID in
      <em>Settings → Google → Ads</em>.</li>
  </ul>
  <p>Watching a rewarded ad is always your choice. You can read every free chapter without watching
    one.</p>

  <h2>Advertising and analytics on this website</h2>
  <p>Separately from the app, the <strong>tunastory.com</strong> website serves ads through
    <strong>Google AdSense</strong> and measures traffic with <strong>Google Analytics</strong>.
    Google and its partners may set or read cookies in your browser to serve and measure those
    ads, and Google Analytics records which pages are opened, roughly where in the world the
    visit came from, and which browser was used. We use it only to see which stories people
    read; we never attach a name, an email address or any other identity to it, because we do
    not hold one.</p>
  <p>This applies only to the website you are reading now. It does <strong>not</strong> change
    what the mobile app does: the app contains no analytics SDK at all, and still requests every
    ad in non-personalised mode.</p>
  <ul>
    <li>You can review and turn off personalised advertising for your Google account at
      <a href="https://myadcenter.google.com/" rel="noopener">My Ad Center</a>.</li>
    <li>You can block or delete cookies in your browser settings at any time. The website remains
      readable without them.</li>
  </ul>

  <h2>What the app does not use</h2>
  <p>The mobile app contains no analytics SDK, no crash-reporting SDK and no social-network SDK.
    Advertising is the only third-party component in the app. The analytics described in the
    previous section runs on the website only and is never present in the app.</p>

  <h2>Children</h2>
  <p>Stories is not directed at children under 13 and we do not knowingly collect information from
    them. Some stories contain themes intended for a general adult audience.</p>

  <h2>Your choices</h2>
  <ul>
    <li>Delete everything the app knows about you by uninstalling it.</li>
    <li>Limit ad personalisation using the iOS or Android settings described above.</li>
    <li>Ask us a question at
      <a href="mailto:{{ config('app.support_email') }}">{{ config('app.support_email') }}</a>.</li>
  </ul>

  <h2>Changes</h2>
  <p>If this policy changes we will update the date at the top of this page. Continuing to use the
    app after a change means you accept the updated policy.</p>
@endsection
