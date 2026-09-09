@extends('admin.layout')

@section('title', 'Sửa thể loại')
@section('heading', 'Sửa thể loại')

@section('content')
<div class="card shadow-sm">
    <div class="card-body">
        <form method="POST" action="{{ route('admin.categories.update', $category) }}">
            @csrf @method('PUT')
            @include('admin.categories._form')
            <button class="btn btn-primary" style="background:#534ab7;border-color:#534ab7;">Cập nhật</button>
            <a href="{{ route('admin.categories.index') }}" class="btn btn-link">Hủy</a>
        </form>
    </div>
</div>
@endsection
