<?php

namespace App\Models;

use App\Console\Commands\DeriveCovers;
use App\Support\PublicFileUrl;
use Illuminate\Database\Eloquent\Casts\Attribute;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsToMany;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\Relations\HasManyThrough;
use Illuminate\Support\Facades\Storage;

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

    /**
     * Bộ ảnh bìa WebP đã thu nhỏ, dạng srcset. Trả null nếu chưa sinh bản nào.
     *
     * CHỈ dùng cho bản web. `thumbnail_url` ở dưới vẫn trả ảnh gốc và vẫn là thứ
     * app di động đọc — đừng gộp hai cái làm một.
     *
     * Sinh bằng `php artisan covers:derive`.
     */
    public function coverSrcset(): ?string
    {
        $source = (string) $this->thumbnail;

        if ($source === '') {
            return null;
        }

        $disk = Storage::disk('public');
        $parts = [];

        foreach ([320, 640] as $width) {
            $path = DeriveCovers::derivedPath($source, $width);

            if ($disk->exists($path)) {
                $parts[] = $disk->url($path).'?v='.$disk->lastModified($path).' '.$width.'w';
            }
        }

        return $parts === [] ? null : implode(', ', $parts);
    }

    /**
     * Một URL ảnh bìa nhỏ (bản WebP 320w nếu đã sinh, không thì ảnh gốc).
     *
     * Dùng ở những chỗ chỉ cần một ảnh cố định, không qua <picture>: nền mờ phía sau
     * đầu trang truyện, ảnh thu nhỏ trong bảng xếp hạng, và mục "Continue reading"
     * lưu ở localStorage. Ảnh gốc 600×800 cho một ô 56px là phí gấp mười lần.
     */
    public function coverSmallUrl(): ?string
    {
        $source = (string) $this->thumbnail;

        if ($source === '') {
            return null;
        }

        $disk = Storage::disk('public');
        $path = DeriveCovers::derivedPath($source, 320);

        return $disk->exists($path)
            ? $disk->url($path).'?v='.$disk->lastModified($path)
            : $this->thumbnail_url;
    }

    protected function thumbnailUrl(): Attribute
    {
        // Gắn version theo thời điểm sửa file: khi ảnh bìa thay đổi mà tên file
        // giữ nguyên (vd seed lại), URL cũng đổi -> client không dùng ảnh cache cũ.
        return Attribute::get(fn () => PublicFileUrl::make($this->thumbnail, requireExists: false));
    }
}
