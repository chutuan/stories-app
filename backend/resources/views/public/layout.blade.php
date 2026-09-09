<!DOCTYPE html>
<html lang="en">
<head>
<meta charset="utf-8">
<meta name="viewport" content="width=device-width, initial-scale=1">
<title>@yield('title', 'Stories') — Stories</title>
<meta name="description" content="@yield('description', 'Stories — read hidden-billionaire fiction on your phone.')">
<style>
  :root{--bg:#FDF8F4;--surface:#fff;--text:#1A1614;--muted:#6E635C;
        --accent:#FF9052;--accent-deep:#F2703A;--border:rgba(26,22,20,.10)}
  *{box-sizing:border-box}
  body{margin:0;background:var(--bg);color:var(--text);line-height:1.7;
       font:16px/1.7 -apple-system,BlinkMacSystemFont,"Segoe UI",Roboto,Helvetica,Arial,sans-serif}
  .wrap{max-width:760px;margin:0 auto;padding:0 20px}
  header{border-bottom:1px solid var(--border);background:var(--surface)}
  header .wrap{display:flex;align-items:center;justify-content:space-between;height:64px}
  .logo{font-size:22px;font-weight:800;letter-spacing:-.02em;text-decoration:none;color:var(--text)}
  .logo span{color:var(--accent-deep)}
  nav a{margin-left:18px;color:var(--muted);text-decoration:none;font-size:14px}
  nav a:hover{color:var(--accent-deep)}
  main{padding:44px 0 72px}
  h1{font-size:32px;line-height:1.25;margin:0 0 8px;letter-spacing:-.02em}
  h2{font-size:19px;margin:34px 0 10px;letter-spacing:-.01em}
  .updated{color:var(--muted);font-size:14px;margin:0 0 28px}
  p,li{color:#2E2724}
  ul{padding-left:20px}
  li{margin:6px 0}
  a{color:var(--accent-deep)}
  .card{background:var(--surface);border:1px solid var(--border);border-radius:16px;padding:22px 24px;margin:22px 0}
  footer{border-top:1px solid var(--border);padding:26px 0;color:var(--muted);font-size:14px}
  footer a{color:var(--muted);margin-right:16px}
</style>
</head>
<body>
<header><div class="wrap">
  <a class="logo" href="/">Sto<span>ries</span></a>
  <nav><a href="/">Home</a><a href="/privacy">Privacy</a><a href="/terms">Terms</a></nav>
</div></header>
<main><div class="wrap">@yield('content')</div></main>
<footer><div class="wrap">
  <a href="/privacy">Privacy Policy</a><a href="/terms">Terms of Service</a>
  <a href="mailto:{{ config('app.support_email') }}">{{ config('app.support_email') }}</a>
  <div style="margin-top:10px">&copy; {{ date('Y') }} Stories</div>
</div></footer>
</body>
</html>
