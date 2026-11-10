<div class="row g-4">
    <div class="col-lg-8">
        <div class="card">
            <div class="card-header"><i class="bi bi-card-text"></i> Nội dung</div>
            <div class="card-body">
                <div class="mb-3">
                    <label class="form-label" for="title">Tiêu đề</label>
                    <input type="text" name="title" id="title" value="{{ old('title', $story->title) }}" class="form-control form-control-lg" required autofocus>
                </div>
                <div class="mb-3">
                    <label class="form-label" for="hook">Câu mồi (phụ đề)</label>
                    <input type="text" name="hook" id="hook" value="{{ old('hook', $story->hook) }}" class="form-control" maxlength="255">
                    <div class="form-text">Hiện dưới tựa trên web và nối sau tựa trong kết quả Google. Viết như câu người đọc sẽ gõ tìm, ví dụ "My husband slapped me in a crowded restaurant to defend his first love…". Để trống nếu không cần.</div>
                </div>
                <div class="mb-3">
                    <label class="form-label" for="author">Tác giả</label>
                    <input type="text" name="author" id="author" value="{{ old('author', $story->author) }}" class="form-control">
                </div>
                <div class="mb-3">
                    <label class="form-label" for="description">Mô tả</label>
                    <textarea name="description" id="description" rows="6" class="form-control">{{ old('description', $story->description) }}</textarea>
                </div>
                <div class="row">
                    <div class="col-sm-6 mb-3 mb-sm-0">
                        <label class="form-label" for="status">Trạng thái</label>
                        <select name="status" id="status" class="form-select">
                            <option value="ongoing" @selected(old('status', $story->status) === 'ongoing')>Đang ra</option>
                            <option value="completed" @selected(old('status', $story->status) === 'completed')>Hoàn thành</option>
                        </select>
                    </div>
                    <div class="col-sm-6">
                        <label class="form-label" for="free_chapters">Số chương miễn phí</label>
                        <input type="number" name="free_chapters" id="free_chapters" min="0" value="{{ old('free_chapters', $story->free_chapters ?? 1) }}" class="form-control" required>
                    </div>
                </div>
            </div>
        </div>
    </div>

    <div class="col-lg-4">
        <div class="card mb-4">
            <div class="card-header"><i class="bi bi-image"></i> Ảnh bìa</div>
            <div class="card-body">
                @if ($story->thumbnail_url)
                    <div class="mb-3 text-center">
                        <img src="{{ $story->thumbnail_url }}" class="thumb-lg" alt="Ảnh bìa hiện tại">
                    </div>
                @endif
                <input type="file" name="thumbnail" accept="image/*" class="form-control" aria-label="Chọn ảnh bìa">
                <div class="form-text">Lưu vào storage/app/public/stories.</div>
            </div>
        </div>

        <div class="card mb-4">
            <div class="card-header"><i class="bi bi-tags"></i> Thể loại</div>
            <div class="card-body">
                <select name="categories[]" class="form-select" multiple size="8" aria-label="Thể loại">
                    @foreach ($categories as $category)
                        <option value="{{ $category->id }}" @selected(in_array($category->id, old('categories', $selectedCategories)))>{{ $category->name }}</option>
                    @endforeach
                </select>
                <div class="form-text">Giữ Ctrl/Cmd để chọn nhiều.</div>
            </div>
        </div>

        <div class="card">
            <div class="card-header"><i class="bi bi-toggles"></i> Hiển thị</div>
            <div class="card-body pb-2">
                <div class="option-tile form-check">
                    <input type="hidden" name="is_featured" value="0">
                    <input type="checkbox" name="is_featured" value="1" class="form-check-input ms-0" id="is_featured" @checked(old('is_featured', $story->is_featured))>
                    <div>
                        <label class="form-check-label" for="is_featured">Nổi bật</label>
                        <div class="form-text">Đưa lên hero banner trang chủ.</div>
                    </div>
                </div>
                <div class="option-tile form-check">
                    <input type="hidden" name="noindex" value="0">
                    <input type="checkbox" name="noindex" value="1" class="form-check-input ms-0" id="noindex" @checked(old('noindex', $story->noindex))>
                    <div>
                        <label class="form-check-label" for="noindex">Không cho Google lập chỉ mục (noindex)</label>
                        <div class="form-text">Dùng cho truyện đã đăng ở site khác: vẫn đọc được trên web và app, nhưng có thẻ noindex và không nằm trong sitemap.</div>
                    </div>
                </div>
                <div class="option-tile form-check">
                    <input type="hidden" name="hidden_on_web" value="0">
                    <input type="checkbox" name="hidden_on_web" value="1" class="form-check-input ms-0" id="hidden_on_web" @checked(old('hidden_on_web', $story->hidden_on_web))>
                    <div>
                        <label class="form-check-label" for="hidden_on_web">Ẩn trên web (app vẫn hiện)</label>
                        <div class="form-text">Dùng cho truyện có nội dung nhà quảng cáo chặn: web trả 404, không ở danh sách, tìm kiếm hay sitemap. App không đổi.</div>
                    </div>
                </div>
            </div>
        </div>
    </div>
</div>
