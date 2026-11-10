@extends('admin.layout')

@section('title', 'Yêu cầu audio')
@section('heading', 'Yêu cầu audio')

@section('content')
{{-- Mỗi lần người dùng app bấm nút tai nghe ở một chương được ghi lại (kể cả chương chưa có
     file MP3). Bảng xếp chương theo số NGƯỜI bấm; nút "Tạo audio" đưa đúng chương đó vào hàng
     đợi tạo giọng đọc AI. Xem App\Http\Controllers\Admin\AudioRequestController. --}}
<div class="row g-3 section-gap">
    <div class="col-6 col-xl-3">
        <x-admin.stat icon="people" label="Người bấm nghe" :value="number_format((int) $totals->people)"
                      :hint="'iOS ' . number_format((int) $totals->ios) . ' · Android ' . number_format((int) $totals->android)" />
    </div>
    <div class="col-6 col-xl-3">
        <x-admin.stat icon="hand-index" tone="info" label="Lượt bấm" :value="number_format((int) $totals->clicks)" />
    </div>
    <div class="col-6 col-xl-3">
        <x-admin.stat icon="play-btn" tone="success" label="Lần xem quảng cáo mở audio" :value="number_format((int) $totals->unlocks)" />
    </div>
    <div class="col-6 col-xl-3">
        <x-admin.stat icon="headphones" tone="warning" label="Chương được yêu cầu" :value="number_format((int) $totals->chapters)"
                      :hint="number_format($missing) . ' chương chưa có audio'" />
    </div>
</div>

@unless ($openAiReady)
    <div class="alert alert-warning"><i class="bi bi-exclamation-triangle-fill"></i><div>Chưa cấu hình <code>OPENAI_API_KEY</code> trong backend/.env nên chưa tạo được audio.</div></div>
@endunless

<form method="GET" class="toolbar">
    <div>
        <select name="audio" class="form-select" onchange="this.form.submit()" aria-label="Lọc theo audio">
            <option value="all"     @selected($audio === 'all')>Mọi chương</option>
            <option value="missing" @selected($audio === 'missing')>Chỉ chương chưa có audio</option>
            <option value="has"     @selected($audio === 'has')>Chỉ chương đã có audio</option>
        </select>
    </div>
    <div>
        <select name="story" class="form-select" onchange="this.form.submit()" aria-label="Lọc theo truyện" style="max-width:320px">
            <option value="">— Tất cả truyện —</option>
            @foreach ($stories as $s)
                <option value="{{ $s->id }}" @selected($storyId === $s->id)>{{ $s->title }}</option>
            @endforeach
        </select>
    </div>
    <div>
        <select name="sort" class="form-select" onchange="this.form.submit()" aria-label="Sắp xếp">
            <option value="people" @selected($sort === 'people')>Nhiều người bấm nhất</option>
            <option value="clicks" @selected($sort === 'clicks')>Nhiều lượt bấm nhất</option>
            <option value="recent" @selected($sort === 'recent')>Bấm gần đây nhất</option>
        </select>
    </div>
</form>

<div class="card">
    <div class="table-responsive">
        <table class="table table-hover align-middle">
            <thead>
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
                            <a href="{{ route('admin.stories.chapters.index', $chapter->story_id) }}" class="cell-title">{{ $chapter->story_title }}</a>
                            <div class="cell-sub">Chương {{ $chapter->number }}@if ($chapter->cleanTitle() !== '') · {{ $chapter->cleanTitle() }}@endif</div>
                        </td>
                        <td class="text-end fw-bold tabular">{{ number_format((int) $chapter->people) }}</td>
                        <td class="text-end tabular">{{ number_format((int) $chapter->clicks) }}</td>
                        <td class="text-end tabular">{{ number_format((int) $chapter->unlocks) }}</td>
                        <td class="cell-sub text-nowrap">{{ \Illuminate\Support\Carbon::parse($chapter->last_request)->diffForHumans() }}</td>
                        <td class="text-center">
                            @if ($chapter->audio_path)
                                <span class="badge bg-dark"><i class="bi bi-volume-up-fill"></i> Có audio</span>
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
                                    <button class="btn btn-sm btn-primary" @disabled(! $openAiReady)>
                                        <i class="bi bi-mic"></i> {{ $chapter->audio_status === 'failed' ? 'Thử lại' : 'Tạo audio' }}
                                    </button>
                                </form>
                            @endif
                        </td>
                    </tr>
                @empty
                    <tr><td colspan="7" class="empty-row"><i class="bi bi-headphones"></i>Chưa có ai bấm nghe chương nào{{ $audio !== 'all' || $storyId ? ' khớp bộ lọc này' : '' }}.</td></tr>
                @endforelse
            </tbody>
        </table>
    </div>
</div>
<div class="pager">{{ $chapters->links('pagination::bootstrap-5') }}</div>
<p class="small text-muted mt-3">Tạo xong thì tải lại trang sau ít phút. Chương đang chờ/đang tạo không bấm lại được; chương đã có audio muốn đọc lại thì vào trang chương của truyện.</p>
@endsection
