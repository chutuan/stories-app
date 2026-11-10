@extends('admin.layout')

@section('title', 'Đăng nhập')

@section('content')
<div class="auth-page">
    <div class="auth-card">
        <div class="auth-brand">
            <img src="/icons/icon-192.png" alt="">
            <h1>TunaStory Admin</h1>
            <p>Đăng nhập để quản trị nội dung</p>
        </div>

        @if ($errors->any())
            <div class="alert alert-danger"><i class="bi bi-x-circle-fill"></i><div>{{ $errors->first() }}</div></div>
        @endif

        <form method="POST" action="{{ route('admin.login.attempt') }}">
            @csrf
            <div class="mb-3">
                <label class="form-label" for="email">Email</label>
                <input type="email" name="email" id="email" value="{{ old('email') }}" class="form-control" required autofocus autocomplete="username">
            </div>
            <div class="mb-3">
                <label class="form-label" for="password">Mật khẩu</label>
                <input type="password" name="password" id="password" class="form-control" required autocomplete="current-password">
            </div>
            <div class="form-check mb-4">
                <input type="checkbox" name="remember" value="1" class="form-check-input" id="remember">
                <label class="form-check-label" for="remember">Ghi nhớ đăng nhập trên máy này</label>
            </div>
            <button class="btn btn-primary w-100">Tiếp tục <i class="bi bi-arrow-right"></i></button>
        </form>
        <div class="auth-foot"><i class="bi bi-shield-lock"></i> Có xác thực 2 bước</div>
    </div>
</div>
@endsection
