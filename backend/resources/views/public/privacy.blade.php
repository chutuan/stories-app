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
      server logs for security and troubleshooting</li>
    <li>Reading counts for each story and chapter, recorded separately for the mobile app and for
      the website. So that opening the same page three times in one afternoon is not counted as
      three readers, each count is stored with a daily keyed hash computed from your IP address,
      your browser or device user agent and the current date. <strong>Your IP address itself is
      never stored in that table</strong>, the hash is never shared or used for advertising, and
      because the date is part of the input it changes every day — so it is not used to
      recognise you from one day to the next, or to build any profile of you.</li>
  </ul>

  <h2>Rating a chapter</h2>
  <p>At the end of every chapter on the website there is a one-tap satisfaction scale. If you use
    it, we store the score, which chapter it was for, and an identifier for you — so that tapping
    again changes your existing rating instead of adding a second one.</p>
  <p>That identifier is a random string generated in your browser and kept in a cookie named
    <code>sid</code> for two years. It is not linked to any account, name, email or IP address, and
    it is not shared with anyone. Before the value is written to our database it is passed through
    a keyed one-way hash, so the stored rating cannot be traced back to the cookie in your browser
    even by us. Clearing your cookies ends the connection permanently — you would simply be able to
    rate those chapters again.</p>
  <p>Rating is entirely optional. Nothing on the site is withheld if you never tap it, and the
    mobile app has no rating feature at all.</p>

  <h2>Advertising in the app</h2>
  <p>Stories shows banner ads and optional rewarded video ads through
    <strong>Google AdMob</strong>. Google's Mobile Ads SDK runs inside the app and sends data
    directly to Google so that Google can serve and measure ads, prevent fraud and analyse how
    its SDK performs. According to Google, that data is:</p>
  <ul>
    <li><strong>Device identifiers</strong> — an identifier scoped to the app or to the developer.
      The app never asks for tracking permission, so on iOS your advertising identifier is not
      available to it.</li>
    <li><strong>Approximate location</strong> — estimated by Google from your IP address, at the
      level of a country or city. The app never asks for or reads your precise location.</li>
    <li><strong>Advertising data</strong> — which ads were shown, and whether they were watched or
      tapped.</li>
    <li><strong>Product interaction</strong> — such as app launches and taps.</li>
    <li><strong>Diagnostics</strong> — crash logs, performance data such as launch time, and other
      technical data about how the ad SDK is running.</li>
  </ul>
  <p>All of this happens inside Google's SDK. We do not receive or store any of it; we only see
    Google's aggregated reports of ad earnings.</p>
  <ul>
    <li>Google's practices are described in
      <a href="https://policies.google.com/technologies/partner-sites" rel="noopener">How Google uses
      information from sites or apps that use our services</a>.</li>
    <li>Ads are always requested in non-personalised mode. We do not track you across other
      apps or websites, so the app never asks for App Tracking Transparency permission.</li>
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
  <p>This applies only to the website. It does <strong>not</strong> change what the mobile app
    does: the app contains no analytics SDK of its own, and still requests every ad in
    non-personalised mode. The Privacy Policy, Terms of Service and Contact pages, which the app
    opens in an in-app browser, carry no ads and no analytics.</p>
  <p>Google Analytics sets its own cookies in your browser and may use web beacons — tiny
    invisible images embedded in a page — to record that a page was opened. We do not combine
    any of it with a name, an email address or any other identity, because we hold none.</p>
  <ul>
    <li>You can review and turn off personalised advertising for your Google account at
      <a href="https://myadcenter.google.com/" rel="noopener">My Ad Center</a>.</li>
    <li>You can opt out of Google Analytics on every website at once by installing the
      <a href="https://tools.google.com/dlpage/gaoptout" rel="noopener">Google Analytics
      Opt-out Browser Add-on</a>.</li>
    <li>You can block or delete cookies and web beacons in your browser settings at any time.
      The website remains fully readable without them.</li>
  </ul>

  <h2>What the app does not use</h2>
  <p>Apart from the Google Mobile Ads SDK described above — which itself collects crash and
    performance diagnostics about its own operation — the mobile app contains no analytics SDK,
    no crash-reporting SDK and no social-network SDK. Advertising is the only third-party
    component in the app.</p>

  <h2>Who else receives data</h2>
  <p>We share data only with the service providers below, and only for the purposes described in
    this policy. Each of them processes it under its own privacy policy and its agreements with
    us, which provide the same or equal protection of user data as this policy.</p>
  <ul>
    <li><strong>Google</strong> — advertising in the app (AdMob) and on the website (AdSense), and
      website analytics, as described above. See the
      <a href="https://policies.google.com/privacy" rel="noopener">Google Privacy Policy</a>.</li>
    <li><strong>Cloudflare</strong> — our website and API are delivered through Cloudflare, which
      processes IP addresses and request data to deliver the service and protect it from abuse.
      See the <a href="https://www.cloudflare.com/privacypolicy/" rel="noopener">Cloudflare Privacy
      Policy</a>.</li>
    <li><strong>DigitalOcean</strong> — hosts our servers, including the server logs described
      above.</li>
  </ul>
  <p>We never sell data, and we share it with no one else.</p>

  <h2>How long we keep data, and how to delete it</h2>
  <ul>
    <li>Server access logs (IP address, user agent, time of request) are rotated daily and
      deleted automatically after about two weeks.</li>
    <li>Reading counts are kept as statistics. The hash stored with each count changes every day
      and contains no IP address, and we never use it to identify anyone.</li>
    <li>Website ratings are kept as statistics. Clearing your <code>sid</code> cookie permanently
      disconnects them from you.</li>
    <li>Everything the app stores on your phone is deleted when you uninstall the app.</li>
    <li>Data collected by Google is kept under
      <a href="https://policies.google.com/technologies/retention" rel="noopener">Google's
      retention policy</a>.</li>
  </ul>
  <p>To stop all data collection by the app, uninstall it. To withdraw consent or ask us to delete
    data we hold about you, write to
    <a href="mailto:{{ config('app.support_email') }}">{{ config('app.support_email') }}</a>.
    Because we keep no accounts, tell us what to look for — for example the date and the chapter
    you rated.</p>

  <h2>Children</h2>
  <p>Stories is not directed at children under 13 and we do not knowingly collect information from
    them. Some stories contain themes intended for a general adult audience.</p>

  <h2>Your choices</h2>
  <ul>
    <li>Delete everything the app stores about you by uninstalling it.</li>
    <li>On iOS the app never asks for tracking permission, so your advertising identifier is never
      available to it. On Android you can reset or delete your advertising ID in
      <em>Settings → Google → Ads</em>.</li>
    <li>Ask us a question, or ask us to delete data, at
      <a href="mailto:{{ config('app.support_email') }}">{{ config('app.support_email') }}</a>.</li>
  </ul>

  <h2>Changes</h2>
  <p>If this policy changes we will update the date at the top of this page. Continuing to use the
    app after a change means you accept the updated policy.</p>
@endsection
