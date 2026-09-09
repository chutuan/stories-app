@extends('admin.layout')

@section('title', 'Chương · ' . $story->title)
@section('heading', 'Chương: ' . $story->title)
@section('actions')
    <a href="{{ route('admin.stories.chapters.create', $story) }}" class="btn btn-primary" style="background:#534ab7;border-color:#534ab7;">+ Thêm chương</a>
    <a href="{{ route('admin.stories.edit', $story) }}" class="btn btn-outline-secondary">Sửa truyện</a>
@endsection

@section('content')
<p class="text-muted">Miễn phí {{ $story->free_chapters }} chương đầu. Tổng {{ $story->chapters()->count() }} chương.</p>

<div class="card shadow-sm">
    <div class="table-responsive">
        <table class="table align-middle mb-0">
            <thead class="table-light">
                <tr>
                    <th style="width:90px;">Chương</th>
                    <th>Tiêu đề</th>
                    <th class="text-center">Trạng thái</th>
                    <th class="text-center">Audio</th>
                    <th class="text-end">Thao tác</th>
                </tr>
            </thead>
            <tbody>
                @forelse ($chapters as $chapter)
                    <tr>
                        <td>#{{ $chapter->number }}</td>
                        <td>{{ $chapter->title }}</td>
                        <td class="text-center">
                            @if ($chapter->number <= $story->free_chapters)
                                <span class="badge" style="background:#1d9e75;">Miễn phí</span>
                            @else
                                <span class="badge" style="background:#efb027;color:#3a2c00;">🔒 Khóa</span>
                            @endif
                        </td>
                        <td class="text-center">
                            @if ($chapter->has_audio)
                                <span class="badge bg-dark">🔊 Có</span>
                            @else
                                <span class="text-muted small">—</span>
                            @endif
                        </td>
                        <td class="text-end">
                            <a href="{{ route('admin.stories.chapters.edit', [$story, $chapter]) }}" class="btn btn-sm btn-outline-secondary">Sửa</a>
                            <form method="POST" action="{{ route('admin.stories.chapters.destroy', [$story, $chapter]) }}" class="d-inline" onsubmit="return confirm('Xóa chương này?');">
                                @csrf @method('DELETE')
                                <button class="btn btn-sm btn-outline-danger">Xóa</button>
                            </form>
                        </td>
                    </tr>
                @empty
                    <tr><td colspan="5" class="text-center text-muted py-4">Chưa có chương nào.</td></tr>
                @endforelse
            </tbody>
        </table>
    </div>
</div>
<div class="mt-3">{{ $chapters->links() }}</div>
@endsection
