<div class="mb-0">
    <label class="form-label" for="name">Tên thể loại</label>
    <input type="text" name="name" id="name" value="{{ old('name', $category->name) }}" class="form-control" required autofocus>
    <div class="form-text">Slug sẽ được tạo tự động từ tên.</div>
</div>
