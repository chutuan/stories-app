<?php

namespace App\Models;

use App\Support\PublicFileUrl;
use Illuminate\Database\Eloquent\Casts\Attribute;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsToMany;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\Relations\HasManyThrough;

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
    ];

    protected $casts = [
        'free_chapters' => 'integer',
        'views' => 'integer',
        'is_featured' => 'boolean',
    ];

    public function categories(): BelongsToMany
    {
        return $this->belongsToMany(Category::class, 'category_story');
    }

    /**
     * Phiếu hài lòng của MỌI chương trong truyện, qua quan hệ bắc cầu.
     *
     * Dùng cho cột điểm trung bình ở danh sách truyện trong trang quản trị.
     * hasManyThrough cho phép withAvg/withCount gộp bằng truy vấn con, thay vì
     * nạp hết chương rồi cộng trong PHP.
     */
    public function reactions(): HasManyThrough
    {
        return $this->hasManyThrough(ChapterReaction::class, Chapter::class);
    }

    public function chapters(): HasMany
    {
        return $this->hasMany(Chapter::class)->orderBy('number', 'asc');
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
