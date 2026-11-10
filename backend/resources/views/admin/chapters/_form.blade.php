<div class="row g-4">
    <div class="col-xl-8">
        <div class="card">
            <div class="card-header"><i class="bi bi-file-earmark-text"></i> Nội dung chương</div>
            <div class="card-body">
                <div class="row">
                    <div class="col-sm-3 mb-3">
                        <label class="form-label" for="number">Số chương</label>
                        <input type="number" name="number" id="number" min="1" value="{{ old('number', $chapter->number) }}" class="form-control" required>
                    </div>
                    <div class="col-sm-9 mb-3">
                        <label class="form-label" for="ch-title">Tiêu đề</label>
                        <input type="text" name="title" id="ch-title" value="{{ old('title', $chapter->title) }}" class="form-control" required placeholder="Chương 1: ...">
                    </div>
                </div>
                <label class="form-label" for="content">Nội dung</label>
                <textarea name="content" id="content" rows="20" class="form-control" style="line-height:1.65" required>{{ old('content', $chapter->content) }}</textarea>
            </div>
        </div>
    </div>

    <div class="col-xl-4">
        <div class="card">
            <div class="card-header"><i class="bi bi-headphones"></i> Audio chương (MP3)</div>
            <div class="card-body">
                @if ($chapter->audio_url)
                    <audio controls preload="none" src="{{ $chapter->audio_url }}" class="w-100 mb-2"></audio>
                    <div class="d-flex align-items-center gap-2 mb-3">
                        {{-- Form xóa audio nằm NGOÀI form chính (xem edit.blade.php) vì HTML không cho lồng form. --}}
                        <button type="submit" form="chapter-audio-delete" class="btn btn-sm btn-outline-danger"
                                onclick="return confirm('Xóa file audio của chương này?');"><i class="bi bi-trash3"></i> Xóa audio</button>
                        <span class="cell-sub text-truncate">{{ $chapter->audio_path }}</span>
                    </div>
                @endif

                @if ($chapter->exists)
                    {{-- Form generate nằm NGOÀI form chính (xem edit.blade.php) vì HTML không cho lồng form. --}}
                    <button type="submit" form="chapter-audio-generate" class="btn btn-sm btn-primary mb-2"
                            onclick="return confirm('Tạo giọng đọc AI cho chương này? Sẽ ghi đè audio hiện có và tốn phí OpenAI.');">
                        <i class="bi bi-mic"></i> Tạo giọng đọc AI
                    </button>
                    <div class="form-text mb-3">
                        Giọng chọn tự động theo thể loại truyện. Cần <code>OPENAI_API_KEY</code> trong <code>.env</code>.
                        Việc tạo chạy trong <strong>hàng đợi</strong>, tải lại trang để xem tiến trình.
                    </div>

                    {{-- Tiến trình của job sinh giọng đọc (chapters.audio_status). --}}
                    @if ($chapter->audio_status_label)
                        <div class="mb-3">
                            <span class="badge {{ $chapter->audio_status_badge }}">{{ $chapter->audio_status_label }}</span>
                            @if ($chapter->audio_status === 'queued')
                                <div class="form-text">Đang chờ worker xử lý (<code>php artisan queue:work</code>).</div>
                            @elseif ($chapter->audio_status === 'processing')
                                <div class="form-text">Đang gọi OpenAI TTS, có thể mất vài phút.</div>
                            @endif
                            @if ($chapter->audio_status === 'failed' && $chapter->audio_error)
                                <div class="alert alert-danger py-2 px-3 mt-2 mb-0 small">{{ $chapter->audio_error }}</div>
                            @endif
                        </div>
                    @endif
                @endif

                <label class="form-label" for="audio">Tải file MP3</label>
                <input type="file" name="audio" id="audio" accept="audio/mpeg,audio/mp3,.mp3" class="form-control">
                <div class="form-text">
                    Lưu vào <code>storage/app/public/audio</code>. Tải file mới sẽ thay file cũ.
                    Giới hạn upload của PHP hiện tại: <strong>{{ ini_get('upload_max_filesize') }}</strong>
                    (đổi trong php.ini nếu file MP3 lớn hơn).
                </div>
            </div>
        </div>
    </div>
</div>
