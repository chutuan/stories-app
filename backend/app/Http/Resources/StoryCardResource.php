<?php

namespace App\Http\Resources;

use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

class StoryCardResource extends JsonResource
{
    public function toArray(Request $request): array
    {
        return [
            'id' => $this->id,
            'title' => $this->title,
            'slug' => $this->slug,
            'author' => $this->author,
            'thumbnail_url' => $this->thumbnail_url,
            // Truyện luôn được nộp đủ chương (status = completed), nhưng chừng nào
            // còn chương hẹn giờ thì với người đọc nó vẫn đang ra dần.
            'status' => $this->publicStatus(),
            'status_label' => $this->publicStatusLabel(),
            'categories' => CategoryResource::collection($this->whenLoaded('categories')),
            'views' => (int) $this->views,
            'chapters_count' => (int) ($this->chapters_count ?? $this->publishedChapters()->count()),
            'latest_chapter_number' => $this->resolveLatestChapterNumber(),
            'updated_at' => optional($this->updated_at)->toISOString(),
        ];
    }

    protected function resolveLatestChapterNumber(): ?int
    {
        $value = $this->chapters_max_number
            ?? $this->latest_chapter_number
            ?? $this->publishedChapters()->max('number');

        return $value !== null ? (int) $value : null;
    }
}
