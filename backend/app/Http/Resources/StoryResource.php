<?php

namespace App\Http\Resources;

use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

class StoryResource extends JsonResource
{
    public function toArray(Request $request): array
    {
        return [
            'id' => $this->id,
            'title' => $this->title,
            'slug' => $this->slug,
            'author' => $this->author,
            'thumbnail_url' => $this->thumbnail_url,
            'status' => $this->status,
            'status_label' => $this->status_label,
            'categories' => CategoryResource::collection($this->whenLoaded('categories')),
            'chapters_count' => (int) ($this->chapters_count ?? $this->chapters()->count()),
            'latest_chapter_number' => $this->resolveLatestChapterNumber(),
            'updated_at' => optional($this->updated_at)->toISOString(),
            'description' => $this->description,
            'free_chapters' => (int) $this->free_chapters,
            'views' => (int) $this->views,
            'is_featured' => (bool) $this->is_featured,
            'chapters' => $this->when(
                $this->relationLoaded('chapters'),
                fn () => $this->chapters->map(fn ($chapter) => [
                    'id' => $chapter->id,
                    'number' => (int) $chapter->number,
                    'title' => $chapter->title,
                    'is_free' => (int) $chapter->number <= (int) $this->free_chapters,
                    'has_audio' => $chapter->has_audio,
                ])->values()
            ),
        ];
    }

    protected function resolveLatestChapterNumber(): ?int
    {
        $value = $this->chapters_max_number
            ?? $this->latest_chapter_number
            ?? $this->chapters()->max('number');

        return $value !== null ? (int) $value : null;
    }
}
