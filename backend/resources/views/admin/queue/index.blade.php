@extends('admin.layout')

@section('title', 'Kho truyện')
@section('heading', 'Kho truyện chờ đăng')

@section('content')
<p class="text-muted">
    Mỗi ngày lúc 13:00 UTC (20:00 giờ Việt Nam) đăng <strong>{{ $daily }}</strong> truyện đầu hàng.
    Kho còn <strong>{{ $pendingCount }}</strong> truyện
    @if ($pendingCount > 0)
        — đủ khoảng <strong>{{ (int) ceil($pendingCount / max(1, $daily)) }}</strong> ngày.
    @endif
</p>

@if ($failed->isNotEmpty())
    <div class="card border-danger shadow-sm mb-4">
        <div class="card-header bg-white fw-semibold text-danger">Đăng lỗi ({{ $failed->count() }})</div>
        <ul class="list-group list-group-flush">
            @foreach ($failed as $item)
                <li class="list-group-item d-flex justify-content-between align-items-start gap-3">
                    <div>
                        <div>{{ $item->title }}</div>
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

<div class="card shadow-sm mb-4">
    <div class="card-header bg-white fw-semibold">Sắp đăng (50 truyện đầu hàng)</div>
    <div class="table-responsive">
        <table class="table align-middle mb-0">
            <thead class="table-light">
                <tr>
                    <th class="text-end" style="width: 70px">#</th>
                    <th>Tiêu đề</th>
                    <th>Nguồn</th>
                    <th class="text-end">Thao tác</th>
                </tr>
            </thead>
            <tbody>
                @forelse ($next as $i => $item)
                    <tr>
                        <td class="text-end text-muted">{{ $i + 1 }}</td>
                        <td>
                            {{ $item->title }}
                            @if ($item->noindex)
                                <span class="badge text-bg-light border ms-1">noindex</span>
                            @endif
                        </td>
                        <td class="text-muted">{{ $item->source }}</td>
                        <td class="text-end">
                            <form method="POST" action="{{ route('admin.queue.destroy', $item) }}"
                                  onsubmit="return confirm('Bỏ truyện này khỏi kho? Nó sẽ không bao giờ được đăng.')">
                                @csrf
                                @method('DELETE')
                                <button class="btn btn-sm btn-outline-danger">Bỏ khỏi kho</button>
                            </form>
                        </td>
                    </tr>
                @empty
                    <tr><td colspan="4" class="text-center text-muted py-4">Kho trống.</td></tr>
                @endforelse
            </tbody>
        </table>
    </div>
</div>

<div class="card shadow-sm">
    <div class="card-header bg-white fw-semibold">Đã đăng gần đây</div>
    <ul class="list-group list-group-flush">
        @forelse ($released as $item)
            <li class="list-group-item d-flex justify-content-between">
                <span>
                    @if ($item->story)
                        <a href="{{ route('admin.stories.edit', $item->story) }}">{{ $item->story->title }}</a>
                    @else
                        {{ $item->title }} <span class="text-muted small">(truyện đã bị xoá)</span>
                    @endif
                </span>
                <span class="text-muted small">{{ $item->released_at?->format('d/m/Y H:i') }} UTC</span>
            </li>
        @empty
            <li class="list-group-item text-muted">Chưa đăng truyện nào từ kho.</li>
        @endforelse
    </ul>
</div>
@endsection
