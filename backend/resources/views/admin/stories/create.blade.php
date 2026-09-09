@extends('admin.layout')

@section('title', 'Thêm truyện')
@section('heading', 'Thêm truyện')

@section('content')
<div class="card shadow-sm">
    <div class="card-body">
        <form method="POST" action="{{ route('admin.stories.store') }}" enctype="multipart/form-data">
            @csrf
            @include('admin.stories._form')
            <button class="btn btn-primary" style="background:#534ab7;border-color:#534ab7;">Lưu</button>
            <a href="{{ route('admin.stories.index') }}" class="btn btn-link">Hủy</a>
        </form>
    </div>
</div>
@endsection
