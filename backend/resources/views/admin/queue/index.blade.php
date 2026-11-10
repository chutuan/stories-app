@extends('admin.layout')

@section('title', 'Kho truyện')
@section('heading', 'Kho truyện chờ đăng')

@section('content')
<div class="row g-3 section-gap">
    <div class="col-6 col-xl-3">
        <x-admin.stat icon="inboxes" label="Còn trong kho" :value="number_format($pendingCount)" />
    </div>
    <div class="col-6 col-xl-3">
        <x-admin.stat icon="calendar-check" tone="success" label="Mỗi ngày đăng" :value="$daily" hint="13:00 UTC · 20:00 giờ VN" />
    </div>
    <div class="col-6 col-xl-3">
        <x-admin.stat icon="hourglass-split" tone="info" label="Đủ đăng khoảng"
                      :value="$pendingCount > 0 ? (int) ceil($pendingCount / max(1, $daily)) . ' ngày' : '—'" />
    </div>
    <div class="col-6 col-xl-3">
        <x-admin.stat icon="exclamation-triangle" :tone="$failed->isNotEmpty() ? 'danger' : 'neutral'" label="Đăng lỗi" :value="$failed->count()" />
    </div>
</div>

@if ($failed->isNotEmpty())
    <div class="card section-gap" style="border-color:#f5cdc9">
        <div class="card-header text-danger"><i class="bi bi-exclamation-triangle" style="color:inherit"></i> Đăng lỗi ({{ $failed->count() }})</div>
        <ul class="list-group list-group-flush">
            @foreach ($failed as $item)
                <li class="list-group-item d-flex justify-content-between align-items-start gap-3">
                    <div>
                        <div class="fw-semibold">{{ $item->title }}</div>
                        <div class="small text-danger">{{ $item->error }}</div>
                    </div>
                    <form method="POST" action="{{ route('admin.queue.destroy', $item) }}">
                        @csrf
                        @method('DELETE')
                        <button class="btn btn-sm btn-outline-secondary">Bỏ</button>
                    </form>
                </li>
            @endforeach
        </ul>
    </div>
@endif

<div class="row g-4">
    <div class="col-xxl-8">
        <div class="card">
            <div class="card-header"><i class="bi bi-list-ol"></i> Sắp đăng <span class="card-header-aside">50 truyện đầu hàng</span></div>
            <div class="table-responsive">
                <table class="table table-hover align-middle">
                    <thead>
                        <tr>
                            <th class="text-end" style="width:64px">#</th>
                            <th>Tiêu đề</th>
                            <th>Nguồn</th>
                            <th class="text-end">Thao tác</th>
                        </tr>
                    </thead>
                    <tbody>
                        @forelse ($next as $i => $item)
                            <tr>
                                <td class="text-end text-muted tabular">{{ $i + 1 }}</td>
                                <td>
                                    <span class="fw-medium">{{ $item->title }}</span>
                                    @if ($item->noindex)
                                        <span class="badge bg-light ms-1">noindex</span>
                                    @endif
                                </td>
                                <td class="cell-sub">{{ $item->source }}</td>
                                <td class="text-end">
                                    <form method="POST" action="{{ route('admin.queue.destroy', $item) }}" class="d-inline"
                                          onsubmit="return confirm('Bỏ truyện này khỏi kho? Nó sẽ không bao giờ được đăng.')">
                                        @csrf
                                        @method('DELETE')
                                        <button class="btn btn-sm btn-outline-danger btn-icon" title="Bỏ khỏi kho" aria-label="Bỏ khỏi kho"><i class="bi bi-x-lg"></i></button>
                                    </form>
                                </td>
                            </tr>
                        @empty
                            <tr><td colspan="4" class="empty-row"><i class="bi bi-inbox"></i>Kho trống.</td></tr>
                        @endforelse
                    </tbody>
                </table>
            </div>
        </div>
    </div>

    <div class="col-xxl-4">
        <div class="card">
            <div class="card-header"><i class="bi bi-check2-circle"></i> Đã đăng gần đây</div>
            <ul class="list-group list-group-flush">
                @forelse ($released as $item)
                    <li class="list-group-item">
                        @if ($item->story)
                            <a href="{{ route('admin.stories.edit', $item->story) }}" class="cell-title">{{ $item->story->title }}</a>
                        @else
                            {{ $item->title }} <span class="text-muted small">(truyện đã bị xoá)</span>
                        @endif
                        <div class="cell-sub">{{ $item->released_at?->format('d/m/Y H:i') }} UTC</div>
                    </li>
                @empty
                    <li class="list-group-item text-muted">Chưa đăng truyện nào từ kho.</li>
                @endforelse
            </ul>
        </div>
    </div>
</div>
@endsection
