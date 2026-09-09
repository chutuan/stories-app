@extends('admin.layout')

@section('title', 'Sửa truyện')
@section('heading', 'Sửa truyện')
@section('actions')
    <a href="{{ route('admin.stories.chapters.index', $story) }}" class="btn btn-outline-primary">Quản lý chương</a>
@endsection

@section('content')
<div class="card shadow-sm">
    <div class="card-body">
        <form method="POST" action="{{ route('admin.stories.update', $story) }}" enctype="multipart/form-data">
            @csrf @method('PUT')
            @include('admin.stories._form')
            <button class="btn btn-primary" style="background:#534ab7;border-color:#534ab7;">Cập nhật</button>
            <a href="{{ route('admin.stories.index') }}" class="btn btn-link">Hủy</a>
        </form>
    </div>
</div>
@endsection
