<?php

namespace App\Support;

use Illuminate\Support\Facades\Storage;

/**
 * Dựng URL tuyệt đối cho file nằm trong disk `public`, kèm `?v=<mtime>` để
 * chống cache khi file đổi mà tên giữ nguyên (dùng chung cho ảnh bìa + audio).
 */
class PublicFileUrl
{
    /**
     * @param  string|null  $path  Đường dẫn tương đối trong disk public (vd `audio/x.mp3`).
     * @param  bool  $requireExists  true -> file không tồn tại thì trả null.
     */
    public static function make(?string $path, bool $requireExists = true): ?string
    {
        if (! $path) {
            return null;
        }

        $disk = Storage::disk('public');

        if (! $disk->exists($path)) {
            return $requireExists ? null : $disk->url($path);
        }

        return $disk->url($path).'?v='.$disk->lastModified($path);
    }
}
