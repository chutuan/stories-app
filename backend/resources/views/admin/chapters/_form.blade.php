<div class="row">
    <div class="col-sm-3 mb-3">
        <label class="form-label">Số chương</label>
        <input type="number" name="number" min="1" value="{{ old('number', $chapter->number) }}" class="form-control" required>
    </div>
    <div class="col-sm-9 mb-3">
        <label class="form-label">Tiêu đề</label>
        <input type="text" name="title" value="{{ old('title', $chapter->title) }}" class="form-control" required placeholder="Chương 1: ...">
    </div>
</div>
<div class="mb-3">
    <label class="form-label">Nội dung</label>
    <textarea name="content" rows="16" class="form-control" required>{{ old('content', $chapter->content) }}</textarea>
</div>
<div class="mb-3">
    <label class="form-label">Audio chương (MP3)</label>
    @if ($chapter->audio_url)
        <div class="d-flex flex-wrap align-items-center gap-2 mb-2">
            <audio controls preload="none" src="{{ $chapter->audio_url }}" style="max-width:320px;"></audio>
            {{-- Form xóa audio nằm NGOÀI form chính (xem edit.blade.php) vì HTML không cho lồng form. --}}
            <button type="submit" form="chapter-audio-delete" class="btn btn-sm btn-outline-danger"
                    onclick="return confirm('Xóa file audio của chương này?');">Xóa audio</button>
            <span class="text-muted small">{{ $chapter->audio_path }}</span>
        </div>
    @endif
    @if ($chapter->exists)
        <div class="d-flex align-items-center gap-2 mb-2">
            {{-- Form generate nằm NGOÀI form chính (xem edit.blade.php) vì HTML không cho lồng form. --}}
            <button type="submit" form="chapter-audio-generate" class="btn btn-sm btn-primary"
                    onclick="return confirm('Tạo giọng đọc AI cho chương này? Sẽ ghi đè audio hiện có và tốn phí OpenAI.');">
                🎙️ Tạo giọng đọc AI
            </button>
            <span class="text-muted small">
                Giọng chọn tự động theo thể loại truyện. Cần <code>OPENAI_API_KEY</code> trong <code>.env</code>.
                Việc tạo chạy trong <strong>hàng đợi</strong>, tải lại trang để xem tiến trình.
            </span>
        </div>

        {{-- Tiến trình của job sinh giọng đọc (chapters.audio_status). --}}
        @if ($chapter->audio_status_label)
            <div class="mb-2">
                <span class="badge {{ $chapter->audio_status_badge }}">{{ $chapter->audio_status_label }}</span>
                @if ($chapter->audio_status === 'queued')
                    <span class="text-muted small">Đang chờ worker xử lý (<code>php artisan queue:work</code>).</span>
                @elseif ($chapter->audio_status === 'processing')
                    <span class="text-muted small">Đang gọi OpenAI TTS, có thể mất vài phút.</span>
                @endif
                @if ($chapter->audio_status === 'failed' && $chapter->audio_error)
                    <div class="alert alert-danger py-2 px-3 mt-2 mb-0 small">{{ $chapter->audio_error }}</div>
                @endif
            </div>
        @endif
    @endif

    <input type="file" name="audio" accept="audio/mpeg,audio/mp3,.mp3" class="form-control">
    <div class="form-text">
        Lưu vào <code>storage/app/public/audio</code>. Tải file mới sẽ thay file cũ.
        Giới hạn upload của PHP hiện tại: <strong>{{ ini_get('upload_max_filesize') }}</strong>
        (đổi trong php.ini nếu file MP3 lớn hơn).
    </div>
</div>
