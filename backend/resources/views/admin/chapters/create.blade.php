@extends('admin.layout')

@section('title', 'Thêm chương')
@section('heading', 'Thêm chương · ' . $story->title)

@section('content')
<div class="card shadow-sm">
    <div class="card-body">
        <form method="POST" action="{{ route('admin.stories.chapters.store', $story) }}" enctype="multipart/form-data">
            @csrf
            @include('admin.chapters._form')
            <button class="btn btn-primary" style="background:#534ab7;border-color:#534ab7;">Lưu</button>
            <a href="{{ route('admin.stories.chapters.index', $story) }}" class="btn btn-link">Hủy</a>
        </form>
    </div>
</div>
@endsection
