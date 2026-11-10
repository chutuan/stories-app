@extends('admin.layout')

@section('title', 'Sửa thể loại')
@section('crumb')<a href="{{ route('admin.categories.index') }}">Thể loại</a> / Sửa thể loại @endsection
@section('heading', 'Sửa thể loại')

@section('content')
<form method="POST" action="{{ route('admin.categories.update', $category) }}" class="card" style="max-width:560px">
    @csrf @method('PUT')
    <div class="card-body">
        @include('admin.categories._form')
    </div>
    <div class="form-actions">
        <button class="btn btn-primary"><i class="bi bi-check2"></i> Cập nhật</button>
        <a href="{{ route('admin.categories.index') }}" class="btn btn-link">Hủy</a>
    </div>
</form>
@endsection
