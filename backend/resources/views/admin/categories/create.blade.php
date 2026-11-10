@extends('admin.layout')

@section('title', 'Thêm thể loại')
@section('crumb')<a href="{{ route('admin.categories.index') }}">Thể loại</a> / Thêm thể loại @endsection
@section('heading', 'Thêm thể loại')

@section('content')
<form method="POST" action="{{ route('admin.categories.store') }}" class="card" style="max-width:560px">
    @csrf
    <div class="card-body">
        @include('admin.categories._form')
    </div>
    <div class="form-actions">
        <button class="btn btn-primary"><i class="bi bi-check2"></i> Lưu</button>
        <a href="{{ route('admin.categories.index') }}" class="btn btn-link">Hủy</a>
    </div>
</form>
@endsection
