@extends('admin.layout')

@section('title', 'Thêm truyện')
@section('crumb')<a href="{{ route('admin.stories.index') }}">Truyện</a> / Thêm mới @endsection
@section('heading', 'Thêm truyện')

@section('content')
<form method="POST" action="{{ route('admin.stories.store') }}" enctype="multipart/form-data">
    @csrf
    @include('admin.stories._form')
    <div class="d-flex gap-2 mt-4">
        <button class="btn btn-primary"><i class="bi bi-check2"></i> Lưu truyện</button>
        <a href="{{ route('admin.stories.index') }}" class="btn btn-link">Hủy</a>
    </div>
</form>
@endsection
