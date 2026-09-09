@extends('admin.layout')

@section('title', 'Thể loại')
@section('heading', 'Thể loại')
@section('actions')
    <a href="{{ route('admin.categories.create') }}" class="btn btn-primary" style="background:#534ab7;border-color:#534ab7;">+ Thêm thể loại</a>
@endsection

@section('content')
<div class="card shadow-sm">
    <div class="table-responsive">
        <table class="table align-middle mb-0">
            <thead class="table-light">
                <tr>
                    <th>Tên</th>
                    <th>Slug</th>
                    <th class="text-center">Số truyện</th>
                    <th class="text-end">Thao tác</th>
                </tr>
            </thead>
            <tbody>
                @forelse ($categories as $category)
                    <tr>
                        <td>{{ $category->name }}</td>
                        <td><code>{{ $category->slug }}</code></td>
                        <td class="text-center">{{ $category->stories_count }}</td>
                        <td class="text-end">
                            <a href="{{ route('admin.categories.edit', $category) }}" class="btn btn-sm btn-outline-secondary">Sửa</a>
                            <form method="POST" action="{{ route('admin.categories.destroy', $category) }}" class="d-inline" onsubmit="return confirm('Xóa thể loại này?');">
                                @csrf @method('DELETE')
                                <button class="btn btn-sm btn-outline-danger">Xóa</button>
                            </form>
                        </td>
                    </tr>
                @empty
                    <tr><td colspan="4" class="text-center text-muted py-4">Chưa có thể loại nào.</td></tr>
                @endforelse
            </tbody>
        </table>
    </div>
</div>
<div class="mt-3">{{ $categories->links() }}</div>
@endsection
