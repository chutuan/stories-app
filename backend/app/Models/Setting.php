<?php

declare(strict_types=1);

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

/**
 * Một dòng cấu hình. Đừng đọc/ghi trực tiếp — đi qua App\Support\AppSettings để
 * có kiểu dữ liệu, giá trị mặc định và cache.
 */
class Setting extends Model
{
    protected $primaryKey = 'key';

    protected $keyType = 'string';

    public $incrementing = false;

    protected $fillable = ['key', 'value'];
}
