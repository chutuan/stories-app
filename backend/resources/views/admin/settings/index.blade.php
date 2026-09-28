@extends('admin.layout')

@section('title', 'Cấu hình')
@section('heading', 'Cấu hình app')

@section('content')
<form method="POST" action="{{ route('admin.settings.update') }}" class="card shadow-sm" style="max-width: 760px">
    @csrf
    @method('PUT')
    <div class="card-body">
        <h2 class="h5">Khi không có quảng cáo thưởng</h2>
        <p class="text-muted">
            Người đọc bấm <strong>Watch ad · +90 coins</strong> nhưng AdMob không trả quảng cáo
            (hết hàng, chặn quảng cáo, lỗi mạng tới Google).
        </p>

        <div class="form-check form-switch mb-2">
            <input type="hidden" name="ad_fallback_enabled" value="0">
            <input class="form-check-input" type="checkbox" role="switch" id="ad_fallback_enabled"
                   name="ad_fallback_enabled" value="1" @checked(old('ad_fallback_enabled', $settings['ad_fallback_enabled']))>
            <label class="form-check-label" for="ad_fallback_enabled">
                Cấp bù xu cho người <strong>đang thiếu xu</strong> ở màn chương bị khoá
            </label>
        </div>
        <ul class="small text-muted mb-4">
            <li>Chỉ cấp vừa đủ để mở <strong>một</strong> chương (tối đa 30 xu), và chỉ khi số dư dưới 30.</li>
            <li>Ở tab Rewards, không có quảng cáo thì <strong>không bao giờ</strong> cấp xu.</li>
            <li>Tắt đi thì người thiếu xu mà không có quảng cáo chỉ còn điểm danh và mốc thời gian đọc.
                Đây là tình huống làm Apple từ chối bản 1.0(3) (Guideline 2.1(a)), nên chỉ tắt khi đã
                chắc AdMob có đủ quảng cáo.</li>
        </ul>

        <label for="ad_fallback_daily_limit" class="form-label fw-semibold">Số lần cấp bù tối đa mỗi ngày (mỗi máy)</label>
        <input type="number" class="form-control" style="max-width: 140px" id="ad_fallback_daily_limit"
               name="ad_fallback_daily_limit" min="0" max="{{ $limitMax }}" required
               value="{{ old('ad_fallback_daily_limit', $settings['ad_fallback_daily_limit']) }}">
        <div class="form-text">0 – {{ $limitMax }}. Mặc định 2 = tối đa 2 chương mỗi ngày không cần quảng cáo.
            Lượt xem quảng cáo thật vẫn +90 xu, 4 lượt/ngày như cũ.</div>

        <div class="alert alert-warning small mt-4 mb-0">
            Đây là chính sách chung cho <strong>mọi người dùng, kể cả Apple</strong>. Đừng bật/tắt theo lịch
            duyệt của Apple: app chạy khác đi trong lúc duyệt là vi phạm Guideline 2.3.1. Nếu tắt hẳn, sửa
            luôn câu về quảng cáo trong Description trên App Store cho khớp.
        </div>
    </div>
    <div class="card-footer bg-white">
        <button type="submit" class="btn btn-primary">Lưu</button>
        <span class="small text-muted ms-2">App nhận cấu hình mới khi mở lại, hoặc trong vòng 10 phút.</span>
    </div>
</form>
@endsection
