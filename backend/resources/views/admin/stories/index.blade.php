@extends('admin.layout')

@section('title', 'Truyện')
@section('heading', 'Truyện')
@section('actions')
    <a href="{{ route('admin.stories.create') }}" class="btn btn-primary"><i class="bi bi-plus-lg"></i> Thêm truyện</a>
@endsection

@section('content')
<form method="GET" class="toolbar">
    <div class="search-box">
        <i class="bi bi-search"></i>
        <input type="search" name="search" value="{{ request('search') }}" class="form-control" placeholder="Tìm tiêu đề / tác giả…" aria-label="Tìm truyện">
    </div>
    <button class="btn btn-outline-secondary">Tìm</button>
    @if (request('search'))
        <a href="{{ route('admin.stories.index') }}" class="btn btn-link btn-sm">Xoá lọc</a>
    @endif
    <span class="ms-auto cell-sub">{{ number_format($stories->total()) }} truyện</span>
</form>

<div class="card">
    <div class="table-responsive">
        <table class="table table-hover align-middle">
            <thead>
                <tr>
                    <th style="width:56px"></th>
                    <th>Tiêu đề</th>
                    <th>Thể loại</th>
                    <th>Trạng thái</th>
                    <th class="text-end">Chương</th>
                    <th class="text-center" style="width:110px">Phản hồi</th>
                    <th class="text-end" style="width:150px">Lượt xem</th>
                    <th class="text-center">Nổi bật</th>
                    <th class="text-end">Thao tác</th>
                </tr>
            </thead>
            <tbody>
                @forelse ($stories as $story)
                    <tr>
                        <td>
                            @if ($story->thumbnail_url)
                                <img src="{{ $story->thumbnail_url }}" class="thumb" alt="" loading="lazy">
                            @else
                                <div class="thumb"></div>
                            @endif
                        </td>
                        <td style="min-width:260px">
                            <a href="{{ route('admin.stories.edit', $story) }}" class="cell-title">{{ $story->title }}</a>
                            <div class="cell-sub">
                                {{ $story->author ?: '—' }}
                                @if ($story->noindex)
                                    <span class="badge bg-light ms-1" title="Không cho Google lập chỉ mục">noindex</span>
                                @endif
                                @if ($story->hidden_on_web)
                                    <span class="badge bg-warning ms-1" title="Ẩn trên web, app vẫn hiện"><i class="bi bi-eye-slash"></i> ẩn web</span>
                                @endif
                            </div>
                        </td>
                        <td>
                            @foreach ($story->categories as $cat)
                                <span class="badge bg-light">{{ $cat->name }}</span>
                            @endforeach
                        </td>
                        <td>@include('admin.partials.story-status', ['story' => $story])</td>
                        <td class="text-end tabular">{{ $story->chapters_count }}</td>
                        <td class="text-center">
                            @include('admin.partials.score', ['count' => $story->reactions_count, 'avg' => $story->reactions_avg_score])
                        </td>
                        <td class="text-end">
                            @php $v = $views[$story->id] ?? null; @endphp
                            @if (! $v || $v['total'] === 0)
                                <span class="text-muted">—</span>
                            @else
                                <div class="fw-semibold tabular">{{ number_format($v['total']) }}</div>
                                <div class="cell-sub text-nowrap tabular">
                                    {{-- Tách nguồn: app di động và trình duyệt là hai nhóm
                                         người đọc khác nhau, gộp lại thì không biết nên đầu
                                         tư vào đâu. --}}
                                    App {{ number_format($v['app']) }}
                                    · Web {{ number_format($v['web_mobile'] + $v['web_desktop']) }}
                                </div>
                                <div class="cell-sub tabular">{{ number_format($v['unique']) }} người</div>
                            @endif
                        </td>
                        <td class="text-center">
                            @if ($story->is_featured)
                                <i class="bi bi-star-fill" style="color:#e8961b" title="Nổi bật"></i>
                            @else
                                <span class="text-muted">—</span>
                            @endif
                        </td>
                        <td class="text-end">
                            <div class="row-actions">
                                <a href="{{ route('admin.stories.chapters.index', $story) }}" class="btn btn-sm btn-outline-primary"><i class="bi bi-list-ol"></i> Chương</a>
                                <a href="{{ route('admin.stories.edit', $story) }}" class="btn btn-sm btn-outline-secondary btn-icon" title="Sửa truyện" aria-label="Sửa truyện"><i class="bi bi-pencil"></i></a>
                                <form method="POST" action="{{ route('admin.stories.destroy', $story) }}" onsubmit="return confirm('Xóa truyện này và toàn bộ chương?');">
                                    @csrf @method('DELETE')
                                    <button class="btn btn-sm btn-outline-danger btn-icon" title="Xóa truyện" aria-label="Xóa truyện"><i class="bi bi-trash3"></i></button>
                                </form>
                            </div>
                        </td>
                    </tr>
                @empty
                    <tr><td colspan="9" class="empty-row"><i class="bi bi-journal"></i>{{ request('search') ? 'Không có truyện nào khớp.' : 'Chưa có truyện nào.' }}</td></tr>
                @endforelse
            </tbody>
        </table>
    </div>
</div>
<div class="pager">{{ $stories->withQueryString()->links('pagination::bootstrap-5') }}</div>
@endsection
