<div class="mb-3">
    <label class="form-label">Tên thể loại</label>
    <input type="text" name="name" value="{{ old('name', $category->name) }}" class="form-control" required autofocus>
    <div class="form-text">Slug sẽ được tạo tự động từ tên.</div>
</div>
