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
        <div class="alert alert-info small">
            Chỉ có tác dụng với app từ bản <strong>1.0.1 (build 8)</strong> trở đi. Bản 1.0 (build 7) không
            đọc cấu hình này: không có quảng cáo thì vẫn cấp +90 xu, tối đa 4 lần/ngày.
        </div>

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
            <p class="mb-2">Đây là chính sách chung cho <strong>mọi người dùng, kể cả Apple</strong>.
                Chốt giá trị TRƯỚC khi nộp bản mới và giữ nguyên tới khi bản đó phát hành. Review Notes
                gửi Apple ghi số lần hiện tại; đổi số này thì ghi lại ở lần nộp kế tiếp.</p>
            <p class="mb-1">Không bao giờ (Guideline 2.3.1, 2.1(a), 3.2.2):</p>
            <ul class="mb-0">
                <li>bật/tắt hay đổi số theo lịch duyệt của Apple — rộng tay lúc duyệt rồi siết lại ngay sau đó;</li>
                <li>tắt cấp bù khi chưa chắc AdMob có đủ quảng cáo — người mới cài sẽ kẹt, đúng lỗi bản 1.0(3).</li>
            </ul>
        </div>
    </div>
    <div class="card-footer bg-white">
        <button type="submit" class="btn btn-primary">Lưu</button>
        <span class="small text-muted ms-2">App đọc cấu hình mỗi lần mở app, và khi quay lại app sau hơn 10 phút.</span>
    </div>
</form>
@endsection
