@extends('admin.layout')

@section('title', 'Tổng quan')
@section('heading', 'Tổng quan')
@section('actions')
    <a href="{{ route('admin.stories.create') }}" class="btn btn-primary"><i class="bi bi-plus-lg"></i> Thêm truyện</a>
@endsection

@section('content')
<div class="row g-3 section-gap">
    <div class="col-6 col-xl-3">
        <x-admin.stat icon="book" label="Truyện" :value="number_format($storiesCount)" :href="route('admin.stories.index')" />
    </div>
    <div class="col-6 col-xl-3">
        <x-admin.stat icon="file-earmark-text" tone="info" label="Chương" :value="number_format($chaptersCount)" />
    </div>
    <div class="col-6 col-xl-3">
        <x-admin.stat icon="tags" tone="neutral" label="Thể loại" :value="number_format($categoriesCount)" :href="route('admin.categories.index')" />
    </div>
    <div class="col-6 col-xl-3">
        <x-admin.stat icon="inboxes" tone="success" label="Kho chờ đăng" :value="number_format($queueCount)"
                      :hint="$queueCount > 0 ? 'Mỗi ngày đăng tự động vài truyện' : 'Kho trống'" :href="route('admin.queue.index')" />
    </div>
</div>

<div class="card">
    <div class="card-header">
        <i class="bi bi-clock-history"></i> Truyện cập nhật gần đây
        <a href="{{ route('admin.stories.index') }}" class="card-header-aside text-decoration-none">Xem tất cả →</a>
    </div>
    <div class="table-responsive">
        <table class="table table-hover align-middle">
            <thead>
                <tr>
                    <th style="width:56px"></th>
                    <th>Tiêu đề</th>
                    <th>Trạng thái</th>
                    <th class="text-end">Chương</th>
                    <th>Cập nhật</th>
                    <th class="text-end">Thao tác</th>
                </tr>
            </thead>
            <tbody>
                @forelse ($recentStories as $story)
                    <tr>
                        <td>
                            @if ($story->thumbnail_url)
                                <img src="{{ $story->thumbnail_url }}" class="thumb" alt="" loading="lazy">
                            @else
                                <div class="thumb"></div>
                            @endif
                        </td>
                        <td><a href="{{ route('admin.stories.edit', $story) }}" class="cell-title">{{ $story->title }}</a></td>
                        <td>@include('admin.partials.story-status', ['story' => $story])</td>
                        <td class="text-end tabular">{{ $story->chapters_count }}</td>
                        <td class="cell-sub text-nowrap">{{ $story->updated_at?->diffForHumans() }}</td>
                        <td class="text-end">
                            <div class="row-actions">
                                <a href="{{ route('admin.stories.chapters.index', $story) }}" class="btn btn-sm btn-outline-primary"><i class="bi bi-list-ol"></i> Chương</a>
                                <a href="{{ route('admin.stories.edit', $story) }}" class="btn btn-sm btn-outline-secondary btn-icon" title="Sửa truyện" aria-label="Sửa truyện"><i class="bi bi-pencil"></i></a>
                            </div>
                        </td>
                    </tr>
                @empty
                    <tr><td colspan="6" class="empty-row"><i class="bi bi-journal"></i>Chưa có truyện nào.</td></tr>
                @endforelse
            </tbody>
        </table>
    </div>
</div>
@endsection
