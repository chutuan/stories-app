@extends('admin.layout')

@section('title', 'Phiếu đánh giá')
@section('heading', 'Phiếu đánh giá')

@section('content')
@php
    $votes    = (int) ($totals->votes ?? 0);
    $avgAll   = $votes ? round((float) $totals->average, 2) : null;
    $badge    = fn ($a) => $a >= 4 ? 'success' : ($a >= 3 ? 'secondary' : 'danger');
@endphp

<div class="row g-3 mb-4">
    <div class="col-md-3">
        <div class="card text-bg-light shadow-sm"><div class="card-body">
            <div class="text-muted">Tổng phiếu</div>
            <div class="display-6 fw-bold">{{ number_format($votes) }}</div>
        </div></div>
    </div>
    <div class="col-md-3">
        <div class="card text-bg-light shadow-sm"><div class="card-body">
            <div class="text-muted">Điểm trung bình</div>
            <div class="display-6 fw-bold">{{ $avgAll !== null ? number_format($avgAll, 2) : '—' }}</div>
        </div></div>
    </div>
    <div class="col-md-3">
        <div class="card text-bg-light shadow-sm"><div class="card-body">
            <div class="text-muted">Chương có phiếu</div>
            <div class="display-6 fw-bold">{{ number_format((int) ($totals->chapters ?? 0)) }}</div>
        </div></div>
    </div>
    <div class="col-md-3">
        <div class="card text-bg-light shadow-sm"><div class="card-body">
            <div class="text-muted">Người bỏ phiếu</div>
            <div class="display-6 fw-bold">{{ number_format((int) ($totals->voters ?? 0)) }}</div>
        </div></div>
    </div>
</div>

<div class="card shadow-sm mb-4">
    <div class="card-header bg-white fw-semibold">Phân bố</div>
    <div class="card-body">
        @if ($votes === 0)
            <div class="text-muted">Chưa có phiếu nào.</div>
        @else
            @foreach (array_reverse($labels, true) as $score => $label)
                @php $n = (int) ($spread[$score] ?? 0); $pct = $votes ? round($n * 100 / $votes) : 0; @endphp
                <div class="d-flex align-items-center gap-2 mb-2">
                    <div style="width:110px;" class="small text-muted text-nowrap">{{ $score }} · {{ $label }}</div>
                    <div class="progress flex-grow-1" style="height:18px;">
                        <div class="progress-bar bg-{{ $badge($score) }}" style="width:{{ $pct }}%"></div>
                    </div>
                    <div style="width:90px;" class="small text-end text-nowrap">{{ $n }} ({{ $pct }}%)</div>
                </div>
            @endforeach
        @endif
    </div>
</div>

<form method="GET" class="row g-2 mb-3">
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
            <option value="recent" @selected($sort === 'recent')>Mới bỏ phiếu nhất</option>
            <option value="low"    @selected($sort === 'low')>Điểm thấp nhất trước</option>
            <option value="high"   @selected($sort === 'high')>Điểm cao nhất trước</option>
            <option value="votes"  @selected($sort === 'votes')>Nhiều phiếu nhất</option>
        </select>
    </div>
</form>

<div class="card shadow-sm">
    <div class="card-header bg-white fw-semibold">Theo chương</div>
    <div class="table-responsive">
        <table class="table align-middle mb-0">
            <thead class="table-light">
                <tr>
                    <th>Truyện</th>
                    <th>Chương</th>
                    <th class="text-center" style="width:90px;">Phiếu</th>
                    <th class="text-center" style="width:100px;">Trung bình</th>
                    <th style="width:230px;">Phân bố</th>
                    <th class="text-end text-nowrap">Phiếu gần nhất</th>
                </tr>
            </thead>
            <tbody>
                @forelse ($chapters as $row)
                    @php $a = round((float) $row->average, 1); @endphp
                    <tr>
                        <td class="small">{{ $row->story_title }}</td>
                        <td>
                            <div class="fw-semibold">Chương {{ $row->chapter_number }}</div>
                            <a class="text-muted small text-decoration-none"
                               href="https://tunastory.com/story/{{ $row->story_slug }}/chapter/{{ $row->chapter_number }}"
                               target="_blank" rel="noopener">Xem trên web ↗</a>
                        </td>
                        <td class="text-center">{{ $row->votes }}</td>
                        <td class="text-center">
                            <span class="badge bg-{{ $badge($a) }}">{{ number_format($a, 1) }}/5</span>
                        </td>
                        <td>
                            <div class="progress" style="height:14px;">
                                @foreach ([1,2,3,4,5] as $sc)
                                    @php $n = (int) $row->{'s'.$sc}; @endphp
                                    @if ($n)
                                        <div class="progress-bar bg-{{ $badge($sc) }}"
                                             style="width:{{ round($n * 100 / max(1, (int) $row->votes)) }}%"
                                             title="{{ $labels[$sc] }}: {{ $n }}"></div>
                                    @endif
                                @endforeach
                            </div>
                        </td>
                        <td class="text-end text-muted small text-nowrap">
                            {{ \Carbon\Carbon::parse($row->last_vote)->diffForHumans() }}
                        </td>
                    </tr>
                @empty
                    <tr><td colspan="6" class="text-center text-muted py-4">Chưa có phiếu nào.</td></tr>
                @endforelse
            </tbody>
        </table>
    </div>
</div>
<div class="mt-3">{{ $chapters->links() }}</div>

<div class="card shadow-sm mt-4">
    <div class="card-header bg-white fw-semibold">50 phiếu gần nhất</div>
    <div class="table-responsive">
        <table class="table align-middle mb-0">
            <thead class="table-light">
                <tr>
                    <th>Khi nào</th>
                    <th>Truyện / chương</th>
                    <th class="text-center" style="width:140px;">Điểm</th>
                    <th style="width:110px;">Người đọc</th>
                    <th class="text-end" style="width:90px;">Thao tác</th>
                </tr>
            </thead>
            <tbody>
                @forelse ($recent as $vote)
                    <tr>
                        <td class="small text-muted text-nowrap">{{ $vote->created_at->diffForHumans() }}</td>
                        <td class="small">
                            {{ $vote->chapter?->story?->title ?? '—' }}
                            <span class="text-muted">· chương {{ $vote->chapter?->number ?? '?' }}</span>
                        </td>
                        <td class="text-center">
                            <span class="badge bg-{{ $badge($vote->score) }}">{{ $vote->score }} · {{ $labels[$vote->score] }}</span>
                        </td>
                        {{-- Tám ký tự đầu của mã băm: đủ để thấy cùng một người bỏ
                             nhiều phiếu, mà không phơi thứ gì truy ngược được. --}}
                        <td><code class="small text-muted">{{ substr($vote->visitor_hash, 0, 8) }}</code></td>
                        <td class="text-end">
                            <form method="POST" action="{{ route('admin.votes.destroy', $vote) }}"
                                  onsubmit="return confirm('Xoá phiếu này?');">
                                @csrf @method('DELETE')
                                <button class="btn btn-sm btn-outline-danger">Xoá</button>
                            </form>
                        </td>
                    </tr>
                @empty
                    <tr><td colspan="5" class="text-center text-muted py-4">Chưa có phiếu nào.</td></tr>
                @endforelse
            </tbody>
        </table>
    </div>
</div>
@endsection
