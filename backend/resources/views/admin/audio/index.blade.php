@extends('admin.layout')

@section('title', 'Yêu cầu audio')
@section('heading', 'Yêu cầu audio')

@section('content')
{{-- Mỗi lần người dùng app bấm nút tai nghe ở một chương được ghi lại (kể cả chương chưa có
     file MP3). Bảng xếp chương theo số NGƯỜI bấm; nút "Tạo audio" đưa đúng chương đó vào hàng
     đợi tạo giọng đọc AI. Xem App\Http\Controllers\Admin\AudioRequestController. --}}
<div class="row g-3 mb-4">
    <div class="col-md-3">
        <div class="card text-bg-light shadow-sm"><div class="card-body">
            <div class="text-muted">Người bấm nghe</div>
            <div class="display-6 fw-bold">{{ number_format((int) $totals->people) }}</div>
            <div class="small text-muted">iOS {{ number_format((int) $totals->ios) }} · Android {{ number_format((int) $totals->android) }}</div>
        </div></div>
    </div>
    <div class="col-md-3">
        <div class="card text-bg-light shadow-sm"><div class="card-body">
            <div class="text-muted">Lượt bấm</div>
            <div class="display-6 fw-bold">{{ number_format((int) $totals->clicks) }}</div>
        </div></div>
    </div>
    <div class="col-md-3">
        <div class="card text-bg-light shadow-sm"><div class="card-body">
            <div class="text-muted">Lần xem quảng cáo mở audio</div>
            <div class="display-6 fw-bold">{{ number_format((int) $totals->unlocks) }}</div>
        </div></div>
    </div>
    <div class="col-md-3">
        <div class="card text-bg-light shadow-sm"><div class="card-body">
            <div class="text-muted">Chương được yêu cầu</div>
            <div class="display-6 fw-bold">{{ number_format((int) $totals->chapters) }}</div>
            <div class="small text-muted">{{ number_format($missing) }} chương chưa có audio</div>
        </div></div>
    </div>
</div>

@unless ($openAiReady)
    <div class="alert alert-warning">Chưa cấu hình <code>OPENAI_API_KEY</code> trong backend/.env nên chưa tạo được audio.</div>
@endunless

<form method="GET" class="row g-2 mb-3">
    <div class="col-auto">
        <select name="audio" class="form-select" onchange="this.form.submit()">
            <option value="all"     @selected($audio === 'all')>Mọi chương</option>
            <option value="missing" @selected($audio === 'missing')>Chỉ chương chưa có audio</option>
            <option value="has"     @selected($audio === 'has')>Chỉ chương đã có audio</option>
        </select>
    </div>
    <div class="col-auto">
        <select name="story" class="form-select" onchange="this.form.submit()">
            <option value="">— Tất cả truyện —</option>
            @foreach ($stories as $s)
                <option value="{{ $s->id }}" @selected($storyId === $s->id)>{{ $s->title }}</option>
            @endforeach
        </select>
    </div>
    <div class="col-auto">
        <select name="sort" class="form-select" onchange="this.form.submit()">
            <option value="people" @selected($sort === 'people')>Nhiều người bấm nhất</option>
            <option value="clicks" @selected($sort === 'clicks')>Nhiều lượt bấm nhất</option>
            <option value="recent" @selected($sort === 'recent')>Bấm gần đây nhất</option>
        </select>
    </div>
</form>

<div class="card shadow-sm">
    <div class="table-responsive">
        <table class="table align-middle mb-0">
            <thead class="table-light">
                <tr>
                    <th style="width:48px;">#</th>
                    <th>Truyện · chương</th>
                    <th class="text-end" title="Số máy khác nhau đã bấm nghe chương này">Người</th>
                    <th class="text-end" title="Tổng số lần bấm, kể cả bấm lại">Lượt bấm</th>
                    <th class="text-end" title="Số lần xem hết quảng cáo để mở audio chương này (mỗi lần mở 24 giờ)">Xem QC</th>
                    <th>Bấm gần nhất</th>
                    <th class="text-center" style="width:170px;">Audio</th>
                </tr>
            </thead>
            <tbody>
                @forelse ($chapters as $i => $chapter)
                    <tr>
                        <td class="text-muted">{{ $chapters->firstItem() + $i }}</td>
                        <td>
                            <a href="{{ route('admin.stories.chapters.index', $chapter->story_id) }}" class="fw-semibold text-decoration-none">{{ $chapter->story_title }}</a>
                            <div class="small text-muted">Chương {{ $chapter->number }}@if ($chapter->cleanTitle() !== '') · {{ $chapter->cleanTitle() }}@endif</div>
                        </td>
                        <td class="text-end fw-bold">{{ number_format((int) $chapter->people) }}</td>
                        <td class="text-end">{{ number_format((int) $chapter->clicks) }}</td>
                        <td class="text-end">{{ number_format((int) $chapter->unlocks) }}</td>
                        <td class="small text-muted text-nowrap">{{ \Illuminate\Support\Carbon::parse($chapter->last_request)->diffForHumans() }}</td>
                        <td class="text-center">
                            @if ($chapter->audio_path)
                                <span class="badge bg-dark">🔊 Có audio</span>
                            @elseif (in_array($chapter->audio_status, ['queued', 'processing'], true))
                                <span class="badge {{ $chapter->audio_status_badge }}">{{ $chapter->audio_status_label }}</span>
                            @else
                                @if ($chapter->audio_status === 'failed')
                                    <div><span class="badge bg-danger mb-1" title="{{ $chapter->audio_error }}">Lỗi lần trước</span></div>
                                @endif
                                <form method="POST"
                                      action="{{ route('admin.stories.chapters.audio.generate', [$chapter->story_id, $chapter->id]) }}"
                                      onsubmit="return confirm('Tạo giọng đọc AI cho chương {{ $chapter->number }} của «{{ addslashes($chapter->story_title) }}»? Mỗi lần tạo là một lần trả phí OpenAI.');">
                                    @csrf
                                    <button class="btn btn-sm btn-primary" style="background:#534ab7;border-color:#534ab7;" @disabled(! $openAiReady)>
                                        {{ $chapter->audio_status === 'failed' ? 'Thử lại' : 'Tạo audio' }}
                                    </button>
                                </form>
                            @endif
                        </td>
                    </tr>
                @empty
                    <tr><td colspan="7" class="text-center text-muted py-4">Chưa có ai bấm nghe chương nào{{ $audio !== 'all' || $storyId ? ' khớp bộ lọc này' : '' }}.</td></tr>
                @endforelse
            </tbody>
        </table>
    </div>
</div>
<div class="mt-3">{{ $chapters->links() }}</div>
<p class="small text-muted mt-3">Tạo xong thì tải lại trang sau ít phút. Chương đang chờ/đang tạo không bấm lại được; chương đã có audio muốn đọc lại thì vào trang chương của truyện.</p>
@endsection
