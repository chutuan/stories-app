<?php

namespace Database\Seeders;

use App\Models\Category;
use App\Models\Story;
use Illuminate\Database\Seeder;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Str;

class StorySeeder extends Seeder
{
    public function run(): void
    {
        $categories = Category::pluck('id', 'name');

        $paragraphs = [
            'Trời vừa hửng sáng, sương mù còn giăng kín cả sườn núi. Thiếu niên áo vải đứng lặng bên vách đá, ánh mắt dõi về phía chân trời xa xăm nơi từng có lời hẹn ước năm nào.',
            'Linh khí trong thân thể cuồn cuộn tuôn trào, tựa như trăm sông đổ về biển lớn. Hắn hít một hơi thật sâu, chậm rãi vận chuyển công pháp, cảm nhận từng đường kinh mạch dần được khai thông.',
            'Giang hồ hiểm ác, lòng người khó lường. Một bước sa chân có thể vạn kiếp bất phục, nhưng nếu không dám bước đi thì mãi mãi chỉ là kẻ tầm thường dưới chân người khác.',
            'Nàng khẽ mỉm cười, ánh mắt long lanh như chứa cả trời sao. "Cho dù thiên hạ này có quay lưng với chàng, ta vẫn sẽ đứng về phía chàng." Lời nói nhẹ nhàng mà nặng tựa ngàn cân.',
            'Đêm ấy mưa rơi tầm tã, sấm chớp xé toạc bầu trời. Trong căn phòng nhỏ leo lét ánh đèn, một bóng người ngồi bất động, tay nắm chặt thanh kiếm cũ đã theo mình suốt bao năm phong trần.',
            'Con đường tu tiên vốn dĩ nghịch thiên mà hành, mỗi cảnh giới đều là một lần lột xác đau đớn. Nhưng chỉ cần trong lòng còn niềm tin, thì dẫu vạn dặm chông gai cũng chẳng thể ngăn bước chân người quyết chí.',
            'Thành phố về đêm rực rỡ ánh đèn, nhưng phía sau sự phồn hoa ấy là biết bao câu chuyện chẳng ai hay. Anh bước đi giữa dòng người vội vã, lòng chợt thấy cô đơn đến lạ.',
        ];

        $stories = [
            ['title' => 'Cửu Chuyển Kiếm Đế', 'author' => 'Thiên Tằm Thổ Đậu', 'status' => 'ongoing', 'featured' => true, 'free' => 1, 'cats' => ['Tiên Hiệp', 'Huyền Huyễn']],
            ['title' => 'Cực Phẩm Thần Y', 'author' => 'Đường Gia Tam Thiếu', 'status' => 'ongoing', 'featured' => false, 'free' => 2, 'cats' => ['Đô Thị', 'Trọng Sinh']],
            ['title' => 'Phàm Nhân Tu Tiên Lộ', 'author' => 'Vong Ngữ', 'status' => 'completed', 'featured' => false, 'free' => 1, 'all_free' => true, 'cats' => ['Tiên Hiệp']],
            ['title' => 'Đế Bá Thương Khung', 'author' => 'Yếm Bút Tiêu Sinh', 'status' => 'ongoing', 'featured' => true, 'free' => 1, 'cats' => ['Huyền Huyễn', 'Kiếm Hiệp']],
            ['title' => 'Trọng Sinh Chi Đô Thị Cuồng Long', 'author' => 'Mạc Mặc', 'status' => 'ongoing', 'featured' => false, 'free' => 3, 'cats' => ['Đô Thị', 'Trọng Sinh']],
            ['title' => 'Thịnh Thế Ngôn Tình', 'author' => 'Cố Mạn', 'status' => 'completed', 'featured' => false, 'free' => 1, 'all_free' => true, 'cats' => ['Ngôn Tình']],
            ['title' => 'Kiếm Lai', 'author' => 'Phong Hỏa Hí Chư Hầu', 'status' => 'ongoing', 'featured' => false, 'free' => 2, 'cats' => ['Kiếm Hiệp', 'Tiên Hiệp']],
            ['title' => 'U Minh Quỷ Sự', 'author' => 'Nam Phái Tam Thúc', 'status' => 'ongoing', 'featured' => false, 'free' => 1, 'cats' => ['Linh Dị', 'Huyền Huyễn']],
            ['title' => 'Hoa Nở Bên Kia Sông', 'author' => 'Tân Di Ổ', 'status' => 'completed', 'featured' => false, 'free' => 1, 'cats' => ['Ngôn Tình', 'Đô Thị']],
            ['title' => 'Trường Sinh Bất Tử Kinh', 'author' => 'Cổ Chân Nhân', 'status' => 'ongoing', 'featured' => false, 'free' => 2, 'cats' => ['Tiên Hiệp', 'Đam Mỹ']],
        ];

        foreach ($stories as $i => $s) {
            $slug = Str::slug($s['title']);

            $story = Story::create([
                'title' => $s['title'],
                'slug' => $slug,
                'author' => $s['author'],
                'description' => 'Câu chuyện kể về hành trình của nhân vật chính trên con đường đầy chông gai để khẳng định bản thân. '
                    .$paragraphs[$i % count($paragraphs)],
                'thumbnail' => $this->seedCover($slug),
                'status' => $s['status'],
                'free_chapters' => $s['free'],
                'views' => random_int(1000, 500000),
                'is_featured' => $s['featured'],
            ]);

            $catIds = collect($s['cats'])
                ->map(fn ($name) => $categories[$name] ?? null)
                ->filter()
                ->all();
            $story->categories()->sync($catIds);

            $chapterCount = random_int(4, 6);
            for ($n = 1; $n <= $chapterCount; $n++) {
                $body = collect(range(1, random_int(3, 4)))
                    ->map(fn () => $paragraphs[array_rand($paragraphs)])
                    ->implode("\n\n");

                $story->chapters()->create([
                    'number' => $n,
                    'title' => "Chương {$n}: ".$this->chapterTitle($n),
                    'content' => $body,
                ]);
            }

            // Vài truyện đọc miễn phí TOÀN BỘ -> phục vụ tab "Miễn phí" (?free=1).
            if (! empty($s['all_free'])) {
                $story->free_chapters = $chapterCount;
                $story->save();
            }
        }
    }

    /**
     * Copy ảnh bìa mẫu vào storage/app/public/stories/ và trả về đường dẫn lưu DB.
     * Ảnh nguồn sinh bằng database/seeders/covers/generate-covers.php.
     * Trả null nếu thiếu file -> app tự hiện placeholder.
     */
    protected function seedCover(string $slug): ?string
    {
        $source = __DIR__.'/covers/'.$slug.'.jpg';

        if (! is_file($source)) {
            return null;
        }

        $target = 'stories/'.$slug.'.jpg';
        Storage::disk('public')->put($target, file_get_contents($source));

        return $target;
    }

    protected function chapterTitle(int $n): string
    {
        $titles = [
            'Khởi đầu gian nan',
            'Cơ duyên bất ngờ',
            'Cường địch xuất hiện',
            'Đột phá cảnh giới',
            'Ân oán giang hồ',
            'Sinh tử quan đầu',
        ];

        return $titles[($n - 1) % count($titles)];
    }
}
