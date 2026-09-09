<?php

namespace App\Http\Resources;

use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

class CategoryResource extends JsonResource
{
    public function toArray(Request $request): array
    {
        $data = [
            'id' => $this->id,
            'name' => $this->name,
            'slug' => $this->slug,
        ];

        // `cover_url` + `stories_count` chỉ có ở GET /api/categories (được nạp sẵn
        // trong controller). Danh sách thể loại nhúng trong StoryCard/Story giữ
        // nguyên shape gọn {id, name, slug}.
        $attributes = $this->resource->getAttributes();

        if (array_key_exists('cover_url', $attributes)) {
            $data['cover_url'] = $attributes['cover_url'];
        }

        if (array_key_exists('stories_count', $attributes)) {
            $data['stories_count'] = (int) $attributes['stories_count'];
        }

        return $data;
    }
}
