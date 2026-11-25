{{--
  Chính sách quyền riêng tư của game Chibi Pet Quest (iPhone), một app khác của cùng người
  phát hành, chỉ mượn chỗ trên tunastory.com. App Store Connect của game trỏ Privacy Policy URL
  về trang này, nên đừng đổi đường dẫn. Nội dung phải khớp game (repo Chibi Pet Quest): từ
  25/11/2026 game không có quảng cáo, không có SDK bên thứ ba, không analytics, không server,
  không mua trong app, không link ra ngoài; App Privacy khai "Data Not Collected"; game nằm
  trong mục Kids. Thêm quảng cáo, mua trong app hay bất kỳ SDK nào thì sửa trang này trước.
  Không quảng cáo, không GA4, không quảng bá app Stories: route nằm trong $legalPage của layout.
--}}
@extends('public.layout')
@section('title', 'Chibi Pet Quest Privacy Policy')
@section('description', 'What the Chibi Pet Quest puzzle game for iPhone does with data: nothing. No ads, no analytics, no account; progress stays on your device.')
@section('content')
  <p class="crumbs">
    <a href="{{ route('public.chibi-pet-quest') }}">Chibi Pet Quest</a> ›
    Privacy Policy
  </p>

  <h1>Chibi Pet Quest Privacy Policy</h1>
  <p class="updated">Last updated: 25 November 2026</p>

  <p>This policy explains what the <strong>Chibi Pet Quest</strong> puzzle game for iPhone does
    with data. Chibi Pet Quest is made by Tuan Chu Duc, who also publishes {{ config('app.site_name') }}.
    In short: <strong>the game collects no data at all.</strong> Nothing you do in the game is sent
    to us or to anyone else.</p>

  <h2>No account, no sign-up</h2>
  <p>Chibi Pet Quest does not ask you to register or log in and never asks for your name, email
    address or phone number. We run no server for the game, we do not build a profile of you and
    we do not sell or share data with anyone.</p>

  <h2>What stays on your device</h2>
  <p>To remember your progress, the game stores the following only on your iPhone. It is never
    sent to us:</p>
  <ul>
    <li>which levels you have unlocked and completed</li>
    <li>which beginner tips you have already seen</li>
    <li>whether sound is on or off</li>
  </ul>
  <p>Uninstalling the game deletes all of it. It is not backed up to us and cannot be restored.</p>

  <h2>No ads, no analytics, no tracking</h2>
  <p>Chibi Pet Quest shows no advertising and contains no analytics, advertising,
    crash-reporting, social, login or other third-party SDK. It never asks for permission to track
    you across apps and websites owned by other companies, and it does not track you. The game
    does not need an internet connection: every level can be played offline.</p>

  <h2>What the game does not use</h2>
  <p>Chibi Pet Quest does not use the camera, microphone, photos, contacts or location services.
    It has no in-app purchases and no links that lead out of the game.</p>

  <h2>Children</h2>
  <p>Chibi Pet Quest is a gentle puzzle game made for children and the grown-ups who play with
    them. We do not collect any personal information from players of any age: there is no
    account, no chat, no sharing and no way to enter a name, photo or contact details.</p>
  <p>Parents and guardians: if you have a question about the game or about this policy, contact
    us below.</p>

  <h2>Your choices</h2>
  <ul>
    <li>Delete everything the game stores by uninstalling it.</li>
    <li>Ask us a question at
      <a href="mailto:{{ config('app.support_email') }}">{{ config('app.support_email') }}</a>.</li>
  </ul>

  <h2>Changes</h2>
  <p>If the game ever changes what it does with data, we will update this policy before that
    version is released, and change the date at the top of this page.</p>
@endsection
