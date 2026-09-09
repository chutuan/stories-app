@extends('public.layout')
@section('title', 'Read hidden-billionaire fiction')
@section('content')
  <h1>Stories</h1>
  <p class="updated">Serialised fiction for your phone — the janitor who owns the tower,
    the broke husband with a hidden empire, the beggar at the board meeting.</p>

  <div class="card">
    <h2 style="margin-top:0">What you get</h2>
    <ul>
      <li>Hand-picked serials across Billionaire, CEO, Secret Identity, Romance, Revenge and more</li>
      <li>A reader you can tune — font size, line spacing, brightness, and four page themes</li>
      <li>Listen to chapters as audio when a narrated version is available</li>
      <li>Earn coins by checking in daily and watching rewarded ads, then spend them to unlock chapters</li>
      <li>No account required — just open the app and read</li>
    </ul>
  </div>

  <h2>Contact</h2>
  <p>Questions, bug reports or content requests:
    <a href="mailto:{{ config('app.support_email') }}">{{ config('app.support_email') }}</a></p>
@endsection
