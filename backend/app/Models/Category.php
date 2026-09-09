<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsToMany;

class Category extends Model
{
    protected $fillable = [
        'name',
        'slug',
    ];

    public function stories(): BelongsToMany
    {
        return $this->belongsToMany(Story::class, 'category_story');
    }
}
