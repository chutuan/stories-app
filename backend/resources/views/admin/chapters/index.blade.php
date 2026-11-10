@extends('admin.layout')

@section('title', 'Chương · ' . $story->title)
@section('crumb')<a href="{{ route('admin.stories.index') }}">Truyện</a> / Chương @endsection
@section('heading', $story->title)
@section('actions')
    <a href="{{ route('admin.stories.edit', $story) }}" class="btn btn-outline-secondary"><i class="bi bi-pencil"></i> Sửa truyện</a>
    <a href="{{ route('admin.stories.chapters.create', $story) }}" class="btn btn-primary"><i class="bi bi-plus-lg"></i> Thêm chương</a>
@endsection

@section('content')
<p class="page-intro">Miễn phí <strong>{{ $story->free_chapters }}</strong> chương đầu · Tổng <strong>{{ $story->chapters()->count() }}</strong> chương.</p>

<div class="card">
    <div class="table-responsive">
        <table class="table table-hover align-middle">
            <thead>
                <tr>
                    <th style="width:80px">Chương</th>
                    <th>Tiêu đề</th>
                    <th class="text-center">Trạng thái</th>
                    <th class="text-center">Audio</th>
                    {{-- Phản hồi của người đọc trên bản web: thang 5 mặt cười ở
                         cuối mỗi chương. Cột này để thấy chương nào đắt, chương
                         nào nhạt — xem App\Http\Controllers\Web\ReactionController. --}}
                    <th class="text-center" style="width:120px">Phản hồi</th>
                    <th class="text-end">Thao tác</th>
                </tr>
            </thead>
            <tbody>
                @forelse ($chapters as $chapter)
                    <tr>
                        <td class="fw-semibold tabular text-muted">#{{ $chapter->number }}</td>
                        <td><a href="{{ route('admin.stories.chapters.edit', [$story, $chapter]) }}" class="cell-title">{{ $chapter->title }}</a></td>
                        <td class="text-center">
                            @if ($chapter->number <= $story->free_chapters)
                                <span class="badge bg-success">Miễn phí</span>
                            @else
                                <span class="badge bg-warning"><i class="bi bi-lock-fill"></i> Khóa</span>
                            @endif
                        </td>
                        <td class="text-center">
                            @if ($chapter->has_audio)
                                <span class="badge bg-dark"><i class="bi bi-volume-up-fill"></i> Có</span>
                            @elseif (! $chapter->audio_status_label)
                                <span class="text-muted">—</span>
                            @endif
                            {{-- Tiến trình sinh giọng đọc AI (job chạy trong hàng đợi). --}}
                            @if ($chapter->audio_status_label && $chapter->audio_status !== 'done')
                                <span class="badge {{ $chapter->audio_status_badge }}"
                                      @if ($chapter->audio_status === 'failed') title="{{ $chapter->audio_error }}" @endif>
                                    {{ $chapter->audio_status_label }}
                                </span>
                            @endif
                        </td>
                        <td class="text-center">
                            @include('admin.partials.score', ['count' => $chapter->reactions_count, 'avg' => $chapter->reactions_avg_score])
                        </td>
                        <td class="text-end">
                            <div class="row-actions">
                                <a href="{{ route('admin.stories.chapters.edit', [$story, $chapter]) }}" class="btn btn-sm btn-outline-secondary btn-icon" title="Sửa chương" aria-label="Sửa chương"><i class="bi bi-pencil"></i></a>
                                <form method="POST" action="{{ route('admin.stories.chapters.destroy', [$story, $chapter]) }}" onsubmit="return confirm('Xóa chương này?');">
                                    @csrf @method('DELETE')
                                    <button class="btn btn-sm btn-outline-danger btn-icon" title="Xóa chương" aria-label="Xóa chương"><i class="bi bi-trash3"></i></button>
                                </form>
                            </div>
                        </td>
                    </tr>
                @empty
                    <tr><td colspan="6" class="empty-row"><i class="bi bi-file-earmark-text"></i>Chưa có chương nào.</td></tr>
                @endforelse
            </tbody>
        </table>
    </div>
</div>
<div class="pager">{{ $chapters->links('pagination::bootstrap-5') }}</div>
@endsection
