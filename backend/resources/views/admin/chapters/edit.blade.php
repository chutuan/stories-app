@extends('admin.layout')

@section('title', 'Sửa chương')
@section('heading', 'Sửa chương · ' . $story->title)

@section('content')
{{-- Form xóa audio: đặt ngoài form chính, nút bấm ở trong _form trỏ tới bằng thuộc tính form="". --}}
@if ($chapter->audio_url)
    <form id="chapter-audio-delete" method="POST"
          action="{{ route('admin.stories.chapters.audio.destroy', [$story, $chapter]) }}" class="d-none">
        @csrf @method('DELETE')
    </form>
@endif

{{-- Form đưa việc tạo giọng đọc AI vào hàng đợi. Luôn có mặt, kể cả khi chương chưa có audio. --}}
<form id="chapter-audio-generate" method="POST"
      action="{{ route('admin.stories.chapters.audio.generate', [$story, $chapter]) }}" class="d-none">
    @csrf
</form>

<div class="card shadow-sm">
    <div class="card-body">
        <form method="POST" action="{{ route('admin.stories.chapters.update', [$story, $chapter]) }}" enctype="multipart/form-data">
            @csrf @method('PUT')
            @include('admin.chapters._form')
            <button class="btn btn-primary" style="background:#534ab7;border-color:#534ab7;">Cập nhật</button>
            <a href="{{ route('admin.stories.chapters.index', $story) }}" class="btn btn-link">Hủy</a>
        </form>
    </div>
</div>
@endsection
