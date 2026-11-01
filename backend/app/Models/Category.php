<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsToMany;

class Category extends Model
{
    /**
     * Thể loại ít truyện hơn số này thì trên web không lên menu, không vào sitemap và
     * trang của nó có noindex. Trang "Mecha · 1 story" là trang mỏng: người soát AdSense
     * bấm vào thấy một bìa duy nhất, và Google coi đó là nội dung giá trị thấp.
     */
    public const WEB_MIN_STORIES = 5;

    protected $fillable = [
        'name',
        'slug',
    ];

    public function stories(): BelongsToMany
    {
        return $this->belongsToMany(Story::class, 'category_story');
    }

    /** `stories_count` chỉ đếm truyện hiện trên web (Story::scopeVisibleOnWeb). */
    public function scopeWithWebStoryCount(Builder $query): Builder
    {
        return $query->withCount(['stories' => fn ($q) => $q->visibleOnWeb()]);
    }
}
