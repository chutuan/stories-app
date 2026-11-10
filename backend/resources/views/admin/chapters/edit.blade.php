@extends('admin.layout')

@section('title', 'Sửa chương')
@section('crumb')<a href="{{ route('admin.stories.index') }}">Truyện</a> / <a href="{{ route('admin.stories.chapters.index', $story) }}">{{ \Illuminate\Support\Str::limit($story->title, 50) }}</a> / Sửa chương @endsection
@section('heading', 'Chương ' . $chapter->number)

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

<form method="POST" action="{{ route('admin.stories.chapters.update', [$story, $chapter]) }}" enctype="multipart/form-data">
    @csrf @method('PUT')
    @include('admin.chapters._form')
    <div class="d-flex gap-2 mt-4">
        <button class="btn btn-primary"><i class="bi bi-check2"></i> Cập nhật</button>
        <a href="{{ route('admin.stories.chapters.index', $story) }}" class="btn btn-link">Hủy</a>
    </div>
</form>
@endsection
