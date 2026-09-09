@extends('admin.layout')

@section('title', 'Đăng nhập')

@section('content')
<div class="d-flex align-items-center justify-content-center" style="min-height:100vh;background:#17132b;">
    <div class="card shadow" style="width:100%;max-width:400px;">
        <div class="card-body p-4">
            <h1 class="h4 text-center mb-1">📚 Stories Admin</h1>
            <p class="text-muted text-center mb-4">Đăng nhập để quản trị</p>

            @if ($errors->any())
                <div class="alert alert-danger">
                    {{ $errors->first() }}
                </div>
            @endif

            <form method="POST" action="{{ route('admin.login.attempt') }}">
                @csrf
                <div class="mb-3">
                    <label class="form-label">Email</label>
                    <input type="email" name="email" value="{{ old('email') }}" class="form-control" required autofocus>
                </div>
                <div class="mb-3">
                    <label class="form-label">Mật khẩu</label>
                    <input type="password" name="password" class="form-control" required>
                </div>
                <div class="form-check mb-3">
                    <input type="checkbox" name="remember" value="1" class="form-check-input" id="remember">
                    <label class="form-check-label" for="remember">Ghi nhớ đăng nhập</label>
                </div>
                <button class="btn btn-primary w-100" style="background:#534ab7;border-color:#534ab7;">Đăng nhập</button>
            </form>
        </div>
    </div>
</div>
@endsection
