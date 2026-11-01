<?php

namespace App\Models;

use App\Console\Commands\DeriveCovers;
use App\Support\PublicFileUrl;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Casts\Attribute;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsToMany;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\Relations\HasManyThrough;
use Illuminate\Http\Exceptions\HttpResponseException;
use Illuminate\Support\Facades\Storage;

class Story extends Model
{
    protected $fillable = [
        'title',
        'hook',
        'slug',
        'author',
        'description',
        'thumbnail',
        'status',
        'free_chapters',
        'views',
        'is_featured',
        'noindex',
        'hidden_on_web',
    ];

    protected $casts = [
        'free_chapters' => 'integer',
        'views' => 'integer',
        'is_featured' => 'boolean',
        'noindex' => 'boolean',
        'hidden_on_web' => 'boolean',
    ];

    /**
     * Đổi slug thì slug cũ tự vào story_slug_redirects — từ lệnh stories:reslug, từ admin
     * sửa tiêu đề, hay từ bất cứ đâu. Để nơi gọi tự nhớ ghi là chắc chắn có lúc quên, và
     * mỗi lần quên là một loạt URL đã index thành 404.
     */
    protected static function booted(): void
    {
        static::updating(function (Story $story): void {
            if (! $story->isDirty('slug')) {
                return;
            }
            // Truyện lấy lại một slug cũ của chính nó: slug đó lại là URL thật, bỏ dòng chuyển hướng.
            StorySlugRedirect::query()->where('old_slug', $story->slug)->delete();

            $old = (string) $story->getOriginal('slug');
            if ($old !== '' && $old !== $story->slug) {
                StorySlugRedirect::query()->updateOrCreate(['old_slug' => $old], ['story_id' => $story->id]);
            }
        });
    }

    /**
     * Slug đã có chủ: đang là slug của truyện khác, HOẶC là slug cũ đang chuyển hướng về
     * truyện khác. Cấp slug cho truyện mới phải tránh cả hai — chiếm slug cũ là cướp URL
     * mà Google đang chuyển về truyện kia.
     */
    public static function slugTaken(string $slug, ?int $ignoreId = null): bool
    {
        return static::query()->where('slug', $slug)->when($ignoreId, fn ($q) => $q->whereKeyNot($ignoreId))->exists()
            || StorySlugRedirect::query()->where('old_slug', $slug)->when($ignoreId, fn ($q) => $q->where('story_id', '!=', $ignoreId))->exists();
    }

    /**
     * Route binding {story:slug} của bản web: không thấy slug thì tra slug cũ.
     *
     * GET/HEAD trả 301 sang đúng route đó với slug mới (giữ số chương, giữ query). Các
     * method khác (POST chấm điểm chương từ một trang còn nằm trong cache 10 phút) thì
     * nhận luôn truyện, vì chuyển hướng một POST là làm mất dữ liệu gửi lên.
     * Binding theo id của API (field null) không đi qua nhánh này.
     */
    public function resolveRouteBinding($value, $field = null)
    {
        $story = parent::resolveRouteBinding($value, $field);
        if ($story || $field !== 'slug') {
            return $story;
        }

        $target = StorySlugRedirect::query()->where('old_slug', $value)->first()?->story;
        $route = request()->route();
        if (! $target || ! $route || $route->getName() === null) {
            return $target;
        }

        if (! request()->isMethod('GET') && ! request()->isMethod('HEAD')) {
            return $target;
        }

        $url = route($route->getName(), ['story' => $target->slug] + $route->originalParameters());
        $query = request()->getQueryString();

        throw new HttpResponseException(redirect($url.($query ? '?'.$query : ''), 301));
    }

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

    /**
     * Truyện hiện trên web — mọi truy vấn của bản web đi qua đây. API cho app KHÔNG dùng:
     * truyện ẩn trên web vẫn hiện bình thường trong app. Xem migration add_hidden_on_web.
     */
    public function scopeVisibleOnWeb(Builder $query): Builder
    {
        return $query->where('stories.hidden_on_web', false);
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
     * Chỉ những truyện có lượt xem 7 ngày qua (hôm nay và 6 ngày trước, web lẫn app),
     * nhiều lượt nhất trước; hoà thì truyện có tổng `views` cao hơn đứng trước.
     *
     * Đây là định nghĩa "Featured" cho CẢ web lẫn app — sửa ở đây thì hai bên đổi cùng
     * lúc, đừng viết lại truy vấn riêng ở controller.
     */
    public function scopeTopThisWeek(Builder $query): Builder
    {
        $weekly = StoryView::query()
            ->select('story_id')
            ->selectRaw('sum(hits) as week_hits')
            ->where('viewed_on', '>=', now()->subDays(6)->toDateString())
            ->groupBy('story_id');

        // Chưa chọn cột nào thì phải ghim stories.*, nếu không join kéo theo cả cột của
        // bảng phụ. Gọi SAU withCount/withMax thì cột đã có sẵn, đừng ghi đè.
        if ($query->getQuery()->columns === null) {
            $query->select('stories.*');
        }

        return $query
            ->joinSub($weekly, 'week', 'week.story_id', '=', 'stories.id')
            ->orderByDesc('week.week_hits')
            ->orderByDesc('stories.views')
            ->orderByDesc('stories.id');
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

        foreach ([320, 480, 640] as $width) {
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

    /**
     * Ảnh cho nền mờ (blur 40px) ở đầu trang chủ và trang truyện: bản WebP 32w, chưa
     * tới 1 KB. Đừng đổi sang bản to hơn — ảnh to là Chrome lại lấy tấm nền làm LCP
     * (xem DeriveCovers::WIDTHS). Chưa sinh thì dùng bản 320w.
     */
    public function coverBlurUrl(): ?string
    {
        $source = (string) $this->thumbnail;
        $disk = Storage::disk('public');
        $path = DeriveCovers::derivedPath($source, 32);

        return $source !== '' && $disk->exists($path)
            ? $disk->url($path).'?v='.$disk->lastModified($path)
            : $this->coverSmallUrl();
    }

    protected function thumbnailUrl(): Attribute
    {
        // Gắn version theo thời điểm sửa file: khi ảnh bìa thay đổi mà tên file
        // giữ nguyên (vd seed lại), URL cũng đổi -> client không dùng ảnh cache cũ.
        return Attribute::get(fn () => PublicFileUrl::make($this->thumbnail, requireExists: false));
    }
}
