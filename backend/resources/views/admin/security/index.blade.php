@extends('admin.layout')

@section('title', 'Bảo mật')
@section('heading', 'Bảo mật')

@section('content')
@php $enabled = $user->hasTwoFactorEnabled(); @endphp

@if ($recoveryCodes)
    {{-- Chỉ hiện đúng một lần, ngay sau khi bật / đổi điện thoại / tạo lại. --}}
    <div class="card section-gap" style="border-color:#f1c6b0">
        <div class="card-header"><i class="bi bi-key"></i> Mã khôi phục — lưu lại ngay</div>
        <div class="card-body">
            <p class="mb-3">Mỗi mã đăng nhập được <strong>một lần</strong> khi không có điện thoại bên cạnh. Trang này
                sẽ <strong>không hiện lại</strong> các mã này: chép vào trình quản lý mật khẩu hoặc in ra giấy.</p>
            <div class="recovery-grid mb-3" id="recovery-codes">
                @foreach ($recoveryCodes as $code)
                    <span>{{ $code }}</span>
                @endforeach
            </div>
            <div class="d-flex gap-2 no-print">
                <button type="button" class="btn btn-outline-secondary btn-sm" id="copy-codes"
                        data-codes="{{ implode("\n", $recoveryCodes) }}"><i class="bi bi-clipboard"></i> Chép</button>
                <button type="button" class="btn btn-outline-secondary btn-sm" onclick="window.print()"><i class="bi bi-printer"></i> In</button>
            </div>
        </div>
    </div>
@endif

<div class="row g-4">
    <div class="col-xl-7">
        <div class="card">
            <div class="card-header">
                <i class="bi bi-shield-lock"></i> Xác thực 2 bước
                <span class="card-header-aside">
                    @if ($enabled)
                        <span class="status-pill on"><i class="bi bi-check-circle-fill"></i> Đang bật</span>
                    @else
                        <span class="status-pill off"><i class="bi bi-exclamation-circle-fill"></i> Chưa bật</span>
                    @endif
                </span>
            </div>
            <div class="card-body">
                @if ($setup)
                    <p class="text-muted mb-4">
                        @if ($enabled)
                            Đang chuyển sang ứng dụng/điện thoại mới. Mã trên điện thoại cũ <strong>vẫn dùng được</strong>
                            cho tới khi bạn xác nhận bước 3.
                        @else
                            Sau khi bật, mỗi lần đăng nhập admin cần thêm mã 6 số đổi mỗi 30 giây trên điện thoại.
                            Người có mật khẩu mà không có điện thoại của bạn thì không vào được.
                        @endif
                    </p>
                    <ol class="step-list">
                        <li>
                            <div class="step-title">Cài ứng dụng xác thực</div>
                            <div class="text-muted small">Google Authenticator, Microsoft Authenticator, 1Password, Authy… ứng dụng nào cũng được.</div>
                        </li>
                        <li>
                            <div class="step-title">Quét mã QR</div>
                            <div class="d-flex flex-wrap gap-4 align-items-start">
                                <div class="qr-box">{!! $setup['qr'] !!}</div>
                                <div style="flex:1;min-width:220px">
                                    <div class="text-muted small mb-2">Không quét được? Chọn “Nhập khoá thiết lập” trong ứng dụng và gõ:</div>
                                    <div class="secret-key mb-2">{{ $setup['secret'] }}</div>
                                    <div class="text-muted small">Tên tài khoản: <strong>{{ $user->email }}</strong> · Loại: theo thời gian</div>
                                </div>
                            </div>
                        </li>
                        <li class="mb-0">
                            <div class="step-title">Nhập mã 6 số đang hiện trong ứng dụng</div>
                            <form method="POST" action="{{ route('admin.security.confirm') }}" class="d-flex flex-wrap gap-2 align-items-start">
                                @csrf
                                <input type="text" name="code" class="form-control otp-input" style="max-width:220px;font-size:1.25rem!important"
                                       inputmode="numeric" pattern="[0-9 ]*" maxlength="7" autocomplete="one-time-code"
                                       placeholder="000000" required autofocus>
                                <button class="btn btn-primary" style="height:46px">{{ $enabled ? 'Xác nhận đổi' : 'Bật xác thực 2 bước' }}</button>
                            </form>
                            @if ($enabled)
                                <form method="POST" action="{{ route('admin.security.cancel') }}" class="mt-2">
                                    @csrf
                                    <button class="btn btn-link btn-sm p-0">Huỷ, giữ điện thoại cũ</button>
                                </form>
                            @endif
                        </li>
                    </ol>
                @else
                    <dl class="row mb-0">
                        <dt class="col-sm-4 text-muted fw-normal">Bật từ</dt>
                        <dd class="col-sm-8">{{ $user->two_factor_confirmed_at->timezone('Asia/Ho_Chi_Minh')->format('d/m/Y H:i') }} (giờ VN)</dd>
                        <dt class="col-sm-4 text-muted fw-normal">Mã khôi phục còn lại</dt>
                        <dd class="col-sm-8">
                            {{ $user->recoveryCodesLeft() }} / {{ \App\Models\User::RECOVERY_CODE_COUNT }}
                            @if ($user->recoveryCodesLeft() <= 2)
                                <span class="badge bg-warning ms-1">Nên tạo bộ mới</span>
                            @endif
                        </dd>
                    </dl>
                @endif
            </div>
        </div>
    </div>

    @if ($enabled)
        <div class="col-xl-5">
            <div class="card mb-4">
                <div class="card-header"><i class="bi bi-phone"></i> Đổi điện thoại</div>
                <div class="card-body">
                    <p class="text-muted small">Chuyển mã sang ứng dụng hoặc điện thoại khác. Khoá cũ chỉ bị thay sau khi
                        ứng dụng mới nhập đúng mã; bộ mã khôi phục cũng được cấp lại.</p>
                    <form method="POST" action="{{ route('admin.security.setup') }}">
                        @csrf
                        <label class="form-label" for="pw-setup">Mật khẩu hiện tại</label>
                        <div class="d-flex gap-2">
                            <input type="password" name="password" id="pw-setup" class="form-control" required autocomplete="current-password">
                            <button class="btn btn-outline-primary text-nowrap">Tiếp tục</button>
                        </div>
                    </form>
                </div>
            </div>
            <div class="card">
                <div class="card-header"><i class="bi bi-key"></i> Mã khôi phục</div>
                <div class="card-body">
                    <p class="text-muted small">Tạo bộ {{ \App\Models\User::RECOVERY_CODE_COUNT }} mã mới. Bộ cũ — kể cả mã chưa dùng — sẽ hết hiệu lực.</p>
                    <form method="POST" action="{{ route('admin.security.recovery-codes') }}">
                        @csrf
                        <label class="form-label" for="pw-codes">Mật khẩu hiện tại</label>
                        <div class="d-flex gap-2">
                            <input type="password" name="password" id="pw-codes" class="form-control" required autocomplete="current-password">
                            <button class="btn btn-outline-secondary text-nowrap">Tạo mã mới</button>
                        </div>
                    </form>
                </div>
            </div>
            <p class="text-muted small mt-3 mb-0"><i class="bi bi-info-circle"></i> Mất cả điện thoại lẫn mã khôi phục: chạy
                <code>php artisan admin:2fa-reset {{ $user->email }}</code> trên server rồi đăng nhập lại để bật lại.</p>
        </div>
    @endif
</div>
@endsection

@push('scripts')
<script>
    (function () {
        var btn = document.getElementById('copy-codes');
        if (!btn || !navigator.clipboard) return;
        btn.addEventListener('click', function () {
            navigator.clipboard.writeText(btn.dataset.codes).then(function () {
                btn.innerHTML = '<i class="bi bi-check2"></i> Đã chép';
            });
        });
    })();
</script>
@endpush
