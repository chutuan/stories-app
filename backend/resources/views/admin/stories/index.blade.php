@extends('admin.layout')

@section('title', 'Truyện')
@section('heading', 'Truyện')
@section('actions')
    <a href="{{ route('admin.stories.create') }}" class="btn btn-primary" style="background:#534ab7;border-color:#534ab7;">+ Thêm truyện</a>
@endsection

@section('content')
<form method="GET" class="mb-3">
    <div class="input-group" style="max-width:360px;">
        <input type="text" name="search" value="{{ request('search') }}" class="form-control" placeholder="Tìm tiêu đề / tác giả...">
        <button class="btn btn-outline-secondary">Tìm</button>
    </div>
</form>

<div class="card shadow-sm">
    <div class="table-responsive">
        <table class="table align-middle mb-0">
            <thead class="table-light">
                <tr>
                    <th style="width:60px;">Ảnh</th>
                    <th>Tiêu đề</th>
                    <th>Thể loại</th>
                    <th>Trạng thái</th>
                    <th class="text-center">Chương</th>
                    <th class="text-center">Nổi bật</th>
                    <th class="text-end">Thao tác</th>
                </tr>
            </thead>
            <tbody>
                @forelse ($stories as $story)
                    <tr>
                        <td>
                            @if ($story->thumbnail_url)
                                <img src="{{ $story->thumbnail_url }}" class="thumb" alt="">
                            @else
                                <div class="thumb"></div>
                            @endif
                        </td>
                        <td>
                            <div class="fw-semibold">{{ $story->title }}</div>
                            <div class="text-muted small">{{ $story->author ?: '—' }}</div>
                        </td>
                        <td>
                            @foreach ($story->categories as $cat)
                                <span class="badge bg-light text-dark border">{{ $cat->name }}</span>
                            @endforeach
                        </td>
                        <td><span class="badge bg-secondary">{{ $story->status_label }}</span></td>
                        <td class="text-center">{{ $story->chapters_count }}</td>
                        <td class="text-center">{!! $story->is_featured ? '⭐' : '—' !!}</td>
                        <td class="text-end text-nowrap">
                            <a href="{{ route('admin.stories.chapters.index', $story) }}" class="btn btn-sm btn-outline-primary">Chương</a>
                            <a href="{{ route('admin.stories.edit', $story) }}" class="btn btn-sm btn-outline-secondary">Sửa</a>
                            <form method="POST" action="{{ route('admin.stories.destroy', $story) }}" class="d-inline" onsubmit="return confirm('Xóa truyện này và toàn bộ chương?');">
                                @csrf @method('DELETE')
                                <button class="btn btn-sm btn-outline-danger">Xóa</button>
                            </form>
                        </td>
                    </tr>
                @empty
                    <tr><td colspan="7" class="text-center text-muted py-4">Chưa có truyện nào.</td></tr>
                @endforelse
            </tbody>
        </table>
    </div>
</div>
<div class="mt-3">{{ $stories->links() }}</div>
@endsection
