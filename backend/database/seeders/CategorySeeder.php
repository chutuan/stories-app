<?php

namespace Database\Seeders;

use App\Models\Category;
use Illuminate\Database\Seeder;
use Illuminate\Support\Str;

class CategorySeeder extends Seeder
{
    public function run(): void
    {
        $names = [
            'Tiên Hiệp',
            'Huyền Huyễn',
            'Ngôn Tình',
            'Đô Thị',
            'Kiếm Hiệp',
            'Trọng Sinh',
            'Đam Mỹ',
            'Linh Dị',
        ];

        foreach ($names as $name) {
            Category::updateOrCreate(
                ['name' => $name],
                ['slug' => Str::slug($name)]
            );
        }
    }
}
