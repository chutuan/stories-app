@extends('admin.layout')

@section('title', 'Bảng điều khiển')
@section('heading', 'Bảng điều khiển')

@section('content')
<div class="row g-3 mb-4">
    <div class="col-md-4">
        <div class="card text-bg-light shadow-sm">
            <div class="card-body">
                <div class="text-muted">Truyện</div>
                <div class="display-6 fw-bold">{{ $storiesCount }}</div>
            </div>
        </div>
    </div>
    <div class="col-md-4">
        <div class="card text-bg-light shadow-sm">
            <div class="card-body">
                <div class="text-muted">Chương</div>
                <div class="display-6 fw-bold">{{ $chaptersCount }}</div>
            </div>
        </div>
    </div>
    <div class="col-md-4">
        <div class="card text-bg-light shadow-sm">
            <div class="card-body">
                <div class="text-muted">Thể loại</div>
                <div class="display-6 fw-bold">{{ $categoriesCount }}</div>
            </div>
        </div>
    </div>
</div>

<div class="card shadow-sm">
    <div class="card-header bg-white fw-semibold">Truyện cập nhật gần đây</div>
    <div class="table-responsive">
        <table class="table align-middle mb-0">
            <thead class="table-light">
                <tr>
                    <th>Tiêu đề</th>
                    <th>Trạng thái</th>
                    <th class="text-center">Số chương</th>
                    <th class="text-end">Thao tác</th>
                </tr>
            </thead>
            <tbody>
                @forelse ($recentStories as $story)
                    <tr>
                        <td>{{ $story->title }}</td>
                        <td><span class="badge bg-secondary">{{ $story->status_label }}</span></td>
                        <td class="text-center">{{ $story->chapters_count }}</td>
                        <td class="text-end">
                            <a href="{{ route('admin.stories.chapters.index', $story) }}" class="btn btn-sm btn-outline-primary">Chương</a>
                            <a href="{{ route('admin.stories.edit', $story) }}" class="btn btn-sm btn-outline-secondary">Sửa</a>
                        </td>
                    </tr>
                @empty
                    <tr><td colspan="4" class="text-center text-muted py-4">Chưa có truyện nào.</td></tr>
                @endforelse
            </tbody>
        </table>
    </div>
</div>
@endsection
