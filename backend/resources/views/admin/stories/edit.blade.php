@extends('admin.layout')

@section('title', 'Sửa truyện')
@section('crumb')<a href="{{ route('admin.stories.index') }}">Truyện</a> / Sửa @endsection
@section('heading', $story->title)
@section('actions')
    <a href="{{ route('admin.stories.chapters.index', $story) }}" class="btn btn-outline-primary"><i class="bi bi-list-ol"></i> Quản lý chương</a>
@endsection

@section('content')
<form method="POST" action="{{ route('admin.stories.update', $story) }}" enctype="multipart/form-data">
    @csrf @method('PUT')
    @include('admin.stories._form')
    <div class="d-flex gap-2 mt-4">
        <button class="btn btn-primary"><i class="bi bi-check2"></i> Cập nhật</button>
        <a href="{{ route('admin.stories.index') }}" class="btn btn-link">Hủy</a>
    </div>
</form>
@endsection
