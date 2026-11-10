@extends('admin.layout')

@section('title', 'Thể loại')
@section('heading', 'Thể loại')
@section('actions')
    <a href="{{ route('admin.categories.create') }}" class="btn btn-primary"><i class="bi bi-plus-lg"></i> Thêm thể loại</a>
@endsection

@section('content')
<div class="card" style="max-width:900px">
    <div class="table-responsive">
        <table class="table table-hover align-middle">
            <thead>
                <tr>
                    <th>Tên</th>
                    <th>Slug</th>
                    <th class="text-end">Số truyện</th>
                    <th class="text-end">Thao tác</th>
                </tr>
            </thead>
            <tbody>
                @forelse ($categories as $category)
                    <tr>
                        <td><a href="{{ route('admin.categories.edit', $category) }}" class="cell-title">{{ $category->name }}</a></td>
                        <td><code>{{ $category->slug }}</code></td>
                        <td class="text-end tabular">{{ $category->stories_count }}</td>
                        <td class="text-end">
                            <div class="row-actions">
                                <a href="{{ route('admin.categories.edit', $category) }}" class="btn btn-sm btn-outline-secondary btn-icon" title="Sửa thể loại" aria-label="Sửa thể loại"><i class="bi bi-pencil"></i></a>
                                <form method="POST" action="{{ route('admin.categories.destroy', $category) }}" onsubmit="return confirm('Xóa thể loại này?');">
                                    @csrf @method('DELETE')
                                    <button class="btn btn-sm btn-outline-danger btn-icon" title="Xóa thể loại" aria-label="Xóa thể loại"><i class="bi bi-trash3"></i></button>
                                </form>
                            </div>
                        </td>
                    </tr>
                @empty
                    <tr><td colspan="4" class="empty-row"><i class="bi bi-tags"></i>Chưa có thể loại nào.</td></tr>
                @endforelse
            </tbody>
        </table>
    </div>
</div>
<div class="pager" style="max-width:900px">{{ $categories->links('pagination::bootstrap-5') }}</div>
@endsection
