<?php

namespace App\Http\Resources;

use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

class ChapterResource extends JsonResource
{
    /**
     * Expects the resource to carry `prev` and `next` (chapter numbers or null)
     * as additional attributes, and the `story` relation to be loaded.
     */
    public function toArray(Request $request): array
    {
        $freeChapters = (int) ($this->story->free_chapters ?? 1);

        return [
            'id' => $this->id,
            'story_id' => (int) $this->story_id,
            'number' => (int) $this->number,
            'title' => $this->title,
            'content' => $this->content,
            'is_free' => (int) $this->number <= $freeChapters,
            // Link MP3 do server phục vụ (null nếu chương chưa có audio).
            'audio_url' => $this->audio_url,
            'prev' => $this->prev,
            'next' => $this->next,
            // Năm khuôn mặt cuối chương (app ≥ 1.0.4): số phiếu + điểm trung bình (null khi < 3
            // phiếu). Phiếu của chính máy này app tự nhớ, không hỏi server.
            'reaction' => $this->reaction_totals,
            'story' => [
                'id' => $this->story->id,
                'title' => $this->story->title,
                'free_chapters' => (int) $this->story->free_chapters,
            ],
        ];
    }
}
