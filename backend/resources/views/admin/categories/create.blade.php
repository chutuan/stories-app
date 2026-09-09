@extends('admin.layout')

@section('title', 'Thêm thể loại')
@section('heading', 'Thêm thể loại')

@section('content')
<div class="card shadow-sm">
    <div class="card-body">
        <form method="POST" action="{{ route('admin.categories.store') }}">
            @csrf
            @include('admin.categories._form')
            <button class="btn btn-primary" style="background:#534ab7;border-color:#534ab7;">Lưu</button>
            <a href="{{ route('admin.categories.index') }}" class="btn btn-link">Hủy</a>
        </form>
    </div>
</div>
@endsection
