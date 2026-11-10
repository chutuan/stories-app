@extends('admin.layout')

@section('title', 'Thêm chương')
@section('crumb')<a href="{{ route('admin.stories.index') }}">Truyện</a> / <a href="{{ route('admin.stories.chapters.index', $story) }}">{{ \Illuminate\Support\Str::limit($story->title, 50) }}</a> / Thêm chương @endsection
@section('heading', 'Thêm chương')

@section('content')
<form method="POST" action="{{ route('admin.stories.chapters.store', $story) }}" enctype="multipart/form-data">
    @csrf
    @include('admin.chapters._form')
    <div class="d-flex gap-2 mt-4">
        <button class="btn btn-primary"><i class="bi bi-check2"></i> Lưu chương</button>
        <a href="{{ route('admin.stories.chapters.index', $story) }}" class="btn btn-link">Hủy</a>
    </div>
</form>
@endsection
