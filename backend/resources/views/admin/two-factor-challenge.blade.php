@extends('admin.layout')

@section('title', 'Xác thực 2 bước')

@section('content')
@php $useRecovery = $errors->has('recovery_code'); @endphp
<div class="auth-page">
    <div class="auth-card">
        <div class="auth-brand">
            <img src="/icons/icon-192.png" alt="">
            <h1>Xác thực 2 bước</h1>
            <p id="otp-help" @class(['d-none' => $useRecovery])>Mở ứng dụng xác thực trên điện thoại và nhập mã 6 số của <strong>TunaStory Admin</strong>.</p>
            <p id="recovery-help" @class(['d-none' => ! $useRecovery])>Nhập một trong các mã khôi phục bạn đã lưu khi bật 2FA. Mỗi mã chỉ dùng được một lần.</p>
        </div>

        @if ($errors->any())
            <div class="alert alert-danger"><i class="bi bi-x-circle-fill"></i><div>{{ $errors->first() }}</div></div>
        @endif

        <form method="POST" action="{{ route('admin.two-factor.verify') }}" id="otp-form" @class(['d-none' => $useRecovery])>
            @csrf
            <label class="form-label visually-hidden" for="code">Mã xác thực</label>
            <input type="text" name="code" id="code" class="form-control otp-input mb-3"
                   inputmode="numeric" pattern="[0-9 ]*" maxlength="7" autocomplete="one-time-code"
                   placeholder="000000" @if (! $useRecovery) autofocus @endif required>
            <button class="btn btn-primary w-100">Xác nhận</button>
        </form>

        <form method="POST" action="{{ route('admin.two-factor.verify') }}" id="recovery-form" @class(['d-none' => ! $useRecovery])>
            @csrf
            <label class="form-label" for="recovery_code">Mã khôi phục</label>
            <input type="text" name="recovery_code" id="recovery_code" class="form-control mb-3"
                   autocomplete="off" autocapitalize="off" spellcheck="false" placeholder="xxxxx-xxxxx"
                   @if ($useRecovery) autofocus @endif>
            <button class="btn btn-primary w-100">Đăng nhập bằng mã khôi phục</button>
        </form>

        <div class="auth-foot">
            <button type="button" class="btn btn-link btn-sm p-0" id="toggle-recovery">
                {{ $useRecovery ? 'Dùng mã 6 số từ ứng dụng' : 'Mất điện thoại? Dùng mã khôi phục' }}
            </button>
            <div class="mt-2"><a href="{{ route('admin.login') }}" class="text-muted">← Quay lại đăng nhập</a></div>
        </div>
    </div>
</div>
@endsection

@push('scripts')
<script>
    (function () {
        var btn = document.getElementById('toggle-recovery');
        var ids = ['otp-form', 'recovery-form', 'otp-help', 'recovery-help'];
        btn.addEventListener('click', function () {
            ids.forEach(function (id) { document.getElementById(id).classList.toggle('d-none'); });
            var recovery = !document.getElementById('recovery-form').classList.contains('d-none');
            document.getElementById('recovery_code').required = recovery;
            document.getElementById('code').required = !recovery;
            btn.textContent = recovery ? 'Dùng mã 6 số từ ứng dụng' : 'Mất điện thoại? Dùng mã khôi phục';
            document.getElementById(recovery ? 'recovery_code' : 'code').focus();
        });
    })();
</script>
@endpush
