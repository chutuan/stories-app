<div class="row g-4">
    <div class="col-md-8">
        <div class="mb-3">
            <label class="form-label">Tiêu đề</label>
            <input type="text" name="title" value="{{ old('title', $story->title) }}" class="form-control" required autofocus>
        </div>
        <div class="mb-3">
            <label class="form-label">Tác giả</label>
            <input type="text" name="author" value="{{ old('author', $story->author) }}" class="form-control">
        </div>
        <div class="mb-3">
            <label class="form-label">Mô tả</label>
            <textarea name="description" rows="5" class="form-control">{{ old('description', $story->description) }}</textarea>
        </div>
        <div class="row">
            <div class="col-sm-6 mb-3">
                <label class="form-label">Trạng thái</label>
                <select name="status" class="form-select">
                    <option value="ongoing" @selected(old('status', $story->status) === 'ongoing')>Đang ra</option>
                    <option value="completed" @selected(old('status', $story->status) === 'completed')>Hoàn thành</option>
                </select>
            </div>
            <div class="col-sm-6 mb-3">
                <label class="form-label">Số chương miễn phí</label>
                <input type="number" name="free_chapters" min="0" value="{{ old('free_chapters', $story->free_chapters ?? 1) }}" class="form-control" required>
            </div>
        </div>
        <div class="form-check mb-3">
            <input type="hidden" name="is_featured" value="0">
            <input type="checkbox" name="is_featured" value="1" class="form-check-input" id="is_featured" @checked(old('is_featured', $story->is_featured))>
            <label class="form-check-label" for="is_featured">Đánh dấu nổi bật (hero banner trang chủ)</label>
        </div>
        <div class="form-check mb-3">
            <input type="hidden" name="noindex" value="0">
            <input type="checkbox" name="noindex" value="1" class="form-check-input" id="noindex" @checked(old('noindex', $story->noindex))>
            <label class="form-check-label" for="noindex">Không cho Google lập chỉ mục (noindex)</label>
            <div class="form-text">Dùng cho truyện đã đăng ở site khác: vẫn đọc được trên web và app, nhưng có thẻ noindex và không nằm trong sitemap.</div>
        </div>
        <div class="form-check mb-3">
            <input type="hidden" name="hidden_on_web" value="0">
            <input type="checkbox" name="hidden_on_web" value="1" class="form-check-input" id="hidden_on_web" @checked(old('hidden_on_web', $story->hidden_on_web))>
            <label class="form-check-label" for="hidden_on_web">Ẩn trên web (app vẫn hiện)</label>
            <div class="form-text">Dùng cho truyện có nội dung nhà quảng cáo chặn: web trả 404, không ở danh sách, tìm kiếm hay sitemap. App không đổi.</div>
        </div>
    </div>

    <div class="col-md-4">
        <div class="mb-3">
            <label class="form-label">Thể loại</label>
            <select name="categories[]" class="form-select" multiple size="8">
                @foreach ($categories as $category)
                    <option value="{{ $category->id }}" @selected(in_array($category->id, old('categories', $selectedCategories)))>{{ $category->name }}</option>
                @endforeach
            </select>
            <div class="form-text">Giữ Ctrl/Cmd để chọn nhiều.</div>
        </div>
        <div class="mb-3">
            <label class="form-label">Ảnh bìa</label>
            @if ($story->thumbnail_url)
                <div class="mb-2">
                    <img src="{{ $story->thumbnail_url }}" class="thumb-lg" alt="thumbnail">
                </div>
            @endif
            <input type="file" name="thumbnail" accept="image/*" class="form-control">
            <div class="form-text">Lưu vào storage/app/public/stories.</div>
        </div>
    </div>
</div>
