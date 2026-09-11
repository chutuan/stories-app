<?php

declare(strict_types=1);

namespace App\Support;

use App\Models\Category;
use App\Models\Chapter;
use App\Models\Story;
use Illuminate\Contracts\Pagination\LengthAwarePaginator;

/**
 * Dựng dữ liệu có cấu trúc schema.org.
 *
 * PHẢI nằm ở PHP thuần, TUYỆT ĐỐI không viết trong file .blade.php. Laravel 11 có
 * directive Blade tên `@context`, nên chuỗi '@context' trong mã nguồn template bị
 * trình biên dịch Blade nuốt và thay bằng mã PHP thô TRƯỚC khi PHP chạy tới. Kết
 * quả in ra là:
 *
 *   {"<?php $__contextArgs = []; if (context()->has(...
 *
 * Toàn bộ JSON-LD của site từng hỏng đúng như vậy trên 101/103 trang mà không có
 * dấu hiệu gì: trang vẫn dựng bình thường, chỉ Google là không đọc được. Đáng chú
 * ý là '@type' KHÔNG bị ảnh hưởng vì Blade không có directive tên đó — nên nhìn
 * lướt qua HTML vẫn thấy "@type" và tưởng mọi thứ ổn.
 *
 * Mỗi phương thức trả về CHUỖI JSON đã mã hoá, view chỉ việc in ra.
 */
class StructuredData
{
    private const CTX = 'https://schema.org';

    /** @param array<string, mixed> $data */
    private static function encode(array $data): string
    {
        return (string) json_encode(
            ['@context' => self::CTX] + array_filter($data, static fn ($v) => $v !== null && $v !== []),
            JSON_UNESCAPED_SLASHES | JSON_UNESCAPED_UNICODE
        );
    }

    public static function website(string $home, string $searchUrl): string
    {
        return self::encode([
            '@type' => 'WebSite',
            'name' => 'Stories',
            'url' => $home,
            'description' => 'Free short serialised fiction you can finish in one sitting.',
            'inLanguage' => 'en',
            'potentialAction' => [
                '@type' => 'SearchAction',
                'target' => ['@type' => 'EntryPoint', 'urlTemplate' => $searchUrl.'?q={search_term_string}'],
                'query-input' => 'required name=search_term_string',
            ],
        ]);
    }

    public static function organization(string $home, string $logo, ?string $email): string
    {
        return self::encode([
            '@type' => 'Organization',
            'name' => 'Stories',
            'url' => $home,
            'logo' => $logo,
            'email' => $email,
        ]);
    }

    /**
     * Trang truyện.
     *
     * `image` dùng ảnh chia sẻ 1200×630 chứ không phải ảnh bìa dọc 600×800:
     * Google khuyến nghị ảnh rộng tối thiểu 1.200px cho kết quả có hình.
     */
    public static function book(Story $story, string $url, string $image): string
    {
        return self::encode([
            '@type' => 'Book',
            'name' => $story->title,
            'url' => $url,
            // Tác giả khai là ORGANIZATION, không phải Person.
            //
            // Tên hiển thị (Vivian Pryce, Nora Calloway, Marin Halloway...) là BÚT
            // DANH của nhà xuất bản cho truyện soạn với hỗ trợ AI — không có con
            // người nào mang tên đó. Khẳng định @type: Person cho máy đọc là nói
            // với Google rằng họ có thật, và Publisher Policies cấm "khai gian về
            // danh tính". Bút danh tự nó hoàn toàn hợp lệ; cái sai là khai nhầm
            // LOẠI thực thể rồi không đính chính ở đâu cả.
            'author' => ['@type' => 'Organization', 'name' => 'Stories'],
            'publisher' => ['@type' => 'Organization', 'name' => 'Stories'],
            'description' => $story->description ?: null,
            'image' => $image,
            // KHÔNG dùng numberOfPages cho số chương: thuộc tính đó có nghĩa là số
            // TRANG giấy. Số chương diễn đạt đúng bằng hasPart.
            'numberOfPages' => null,
            'inLanguage' => 'en',
            'genre' => $story->categories->pluck('name')->all() ?: null,
            'hasPart' => $story->chapters->map(fn (Chapter $c) => [
                '@type' => 'Chapter',
                'name' => $c->title,
                'position' => $c->number,
            ])->all() ?: null,
        ]);
    }

    /** Trang đọc một chương. */
    public static function article(
        Story $story,
        Chapter $chapter,
        string $heading,
        string $url,
        string $storyUrl,
        string $image,
        ?string $excerpt,
    ): string {
        return self::encode([
            '@type' => 'Article',
            'headline' => $heading.' — '.$story->title,
            'url' => $url,
            'description' => $excerpt ?: null,
            'image' => $image,
            'inLanguage' => 'en',
            'datePublished' => $chapter->created_at?->toAtomString(),
            'dateModified' => $chapter->updated_at?->toAtomString(),
            // Cùng lý do với Book::author — xem chú thích ở trên.
            'author' => ['@type' => 'Organization', 'name' => 'Stories'],
            'publisher' => ['@type' => 'Organization', 'name' => 'Stories'],
            'isPartOf' => ['@type' => 'Book', 'name' => $story->title, 'url' => $storyUrl],
        ]);
    }

    /** @param list<array{name: string, url?: string}> $crumbs */
    public static function breadcrumbs(array $crumbs): string
    {
        return self::encode([
            '@type' => 'BreadcrumbList',
            'itemListElement' => array_values(array_map(
                static fn (int $i, array $c) => array_filter([
                    '@type' => 'ListItem',
                    'position' => $i + 1,
                    'name' => $c['name'],
                    'item' => $c['url'] ?? null,
                ], static fn ($v) => $v !== null),
                array_keys($crumbs),
                $crumbs,
            )),
        ]);
    }

    /** Trang duyệt / trang thể loại. */
    public static function collection(
        LengthAwarePaginator $stories,
        ?Category $category,
        string $url,
        string $home,
        callable $storyUrl,
    ): string {
        return self::encode([
            '@type' => 'CollectionPage',
            'name' => $category ? $category->name.' stories' : 'All stories',
            'url' => $url,
            'inLanguage' => 'en',
            'isPartOf' => ['@type' => 'WebSite', 'name' => 'Stories', 'url' => $home],
            'mainEntity' => [
                '@type' => 'ItemList',
                'numberOfItems' => $stories->total(),
                'itemListElement' => array_values(array_map(
                    static fn (int $i, Story $s) => [
                        '@type' => 'ListItem',
                        'position' => $stories->firstItem() + $i,
                        'url' => $storyUrl($s),
                        'name' => $s->title,
                    ],
                    array_keys($stories->items()),
                    $stories->items(),
                )),
            ],
        ]);
    }
}
