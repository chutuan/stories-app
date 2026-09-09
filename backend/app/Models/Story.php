<?php

namespace App\Models;

use App\Support\PublicFileUrl;
use Illuminate\Database\Eloquent\Casts\Attribute;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsToMany;
use Illuminate\Database\Eloquent\Relations\HasMany;

class Story extends Model
{
    protected $fillable = [
        'title',
        'slug',
        'author',
        'description',
        'thumbnail',
        'status',
        'free_chapters',
        'views',
        'is_featured',
        'publish_every_hours',
        'publish_start_at',
    ];

    protected $casts = [
        'free_chapters' => 'integer',
        'views' => 'integer',
        'is_featured' => 'boolean',
        'publish_every_hours' => 'integer',
        'publish_start_at' => 'datetime',
    ];

    public function categories(): BelongsToMany
    {
        return $this->belongsToMany(Category::class, 'category_story');
    }

    /** MỌI chương, kể cả chương chưa tới giờ đăng. Dùng cho admin và luồng nạp. */
    public function chapters(): HasMany
    {
        return $this->hasMany(Chapter::class)->orderBy('number', 'asc');
    }

    /**
     * Chỉ chương ĐÃ tới giờ đăng — quan hệ dành cho MỌI truy vấn công khai.
     *
     * Cố ý tách thành quan hệ riêng thay vì ràng buộc thẳng vào `chapters()`:
     * đổi ý nghĩa của `chapters()` sẽ âm thầm giấu chương khỏi cả admin lẫn luồng
     * sinh audio, mà những chỗ đó cần thấy đủ để chuẩn bị trước ngày đăng.
     */
    public function publishedChapters(): HasMany
    {
        return $this->hasMany(Chapter::class)->published()->orderBy('number', 'asc');
    }

    /** Chương còn đang chờ tới giờ. Dùng để suy ra truyện đã trọn bộ hay chưa. */
    public function pendingChapters(): HasMany
    {
        return $this->hasMany(Chapter::class)->pending();
    }

    /**
     * Trạng thái HIỂN THỊ cho người đọc.
     *
     * Truyện được nộp đủ chương nên cột `status` luôn là 'completed'; nhưng chừng
     * nào còn chương chưa tới giờ thì với người đọc nó vẫn đang ra dần -> 'ongoing'.
     * Ưu tiên dùng số đếm đã nạp sẵn (withCount) để không sinh truy vấn N+1.
     */
    public function publicStatus(): string
    {
        $pending = $this->pending_chapters_count ?? $this->pendingChapters()->count();

        return $pending > 0 ? 'ongoing' : (string) $this->status;
    }

    public function publicStatusLabel(): string
    {
        return match ($this->publicStatus()) {
            'ongoing' => 'Ongoing',
            'completed' => 'Completed',
            default => (string) $this->status,
        };
    }

    protected function statusLabel(): Attribute
    {
        return Attribute::get(fn () => match ($this->status) {
            'ongoing' => 'Ongoing',
            'completed' => 'Completed',
            default => $this->status,
        });
    }

    protected function thumbnailUrl(): Attribute
    {
        // Gắn version theo thời điểm sửa file: khi ảnh bìa thay đổi mà tên file
        // giữ nguyên (vd seed lại), URL cũng đổi -> client không dùng ảnh cache cũ.
        return Attribute::get(fn () => PublicFileUrl::make($this->thumbnail, requireExists: false));
    }
}
