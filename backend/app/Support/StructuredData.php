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

    /**
     * Người biên tập — Person có thật, đứng tên trên mọi truyện. url trỏ về /editor để
     * Google nối byline của từng trang với một trang hồ sơ (xem ReadController::editor).
     *
     * @return array<string, string>
     */
    private static function editor(): array
    {
        return [
            '@type' => 'Person',
            'name' => (string) config('app.editor_name'),
            'url' => route('public.editor'),
        ];
    }

    /**
     * Nhà xuất bản.
     *
     * `publishingPrinciples` trỏ tới trang chuẩn biên tập. Đây là trường schema.org
     * dành riêng cho "quy tắc biên tập công bố ở đâu"; Google dùng nó trong nhóm
     * tín hiệu về độ tin cậy của nhà xuất bản. Bỏ trống thì không in ra — khai một
     * URL không tồn tại còn tệ hơn là không khai.
     */
    public static function organization(
        string $home,
        string $logo,
        ?string $email,
        ?string $publishingPrinciples = null,
    ): string {
        return self::encode(array_filter([
            '@type' => 'Organization',
            'name' => 'Stories',
            'url' => $home,
            'logo' => $logo,
            'email' => $email,
            'publishingPrinciples' => $publishingPrinciples,
            'founder' => self::editor(),
        ]));
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
            'alternateName' => $story->hook ?: null,
            'url' => $url,
            // Tác giả là NGƯỜI BIÊN TẬP có thật (Person), không phải bút danh.
            //
            // Trước 01/11/2026 khai Organization "Stories", vì byline khi đó là bút
            // danh (Vivian Pryce, Marin Halloway...) — khai Person cho một cái tên
            // không có thật là khai gian danh tính. Nay byline là tên thật của người
            // đặt brief và duyệt đăng ("Edited by …"), trang /editor nói rõ truyện
            // soạn với công cụ AI. Không dùng lại cột `author`: cột đó vẫn còn bút danh.
            'author' => self::editor(),
            'publisher' => ['@type' => 'Organization', 'name' => 'Stories'],
            'description' => $story->description ?: null,
            'image' => $image,
            // Ngày lên web và ngày sửa: Google dùng để biết truyện còn mới (Discover chỉ đẩy
            // nội dung mới) và để hiện ngày cạnh kết quả.
            'datePublished' => $story->created_at?->toAtomString(),
            'dateModified' => $story->updated_at?->toAtomString(),
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
            'author' => self::editor(),
            'publisher' => ['@type' => 'Organization', 'name' => 'Stories'],
            'isPartOf' => ['@type' => 'Book', 'name' => $story->title, 'url' => $storyUrl],
        ]);
    }

    public static function aboutPage(string $url, string $home): string
    {
        return self::encode([
            '@type' => 'AboutPage',
            'name' => 'About Stories',
            'url' => $url,
            'inLanguage' => 'en',
            'isPartOf' => ['@type' => 'WebSite', 'name' => 'Stories', 'url' => $home],
            'about' => ['@type' => 'Organization', 'name' => 'Stories', 'url' => $home],
        ]);
    }

    /**
     * Trang liên hệ.
     *
     * ContactPage là loại riêng trong schema.org, không phải WebPage chung: nó nói
     * với Google "đây là trang liên hệ chính thức của tổ chức này", điều mà người
     * soát AdSense cũng đi tìm bằng mắt.
     */
    public static function contactPage(string $url, string $home, ?string $email): string
    {
        return self::encode([
            '@type' => 'ContactPage',
            'name' => 'Contact Stories',
            'url' => $url,
            'inLanguage' => 'en',
            'isPartOf' => ['@type' => 'WebSite', 'name' => 'Stories', 'url' => $home],
            'about' => array_filter([
                '@type' => 'Organization',
                'name' => 'Stories',
                'url' => $home,
                'email' => $email,
            ]),
        ]);
    }

    /**
     * Trang chuẩn biên tập.
     *
     * Khai bằng `publishingPrinciples` trên Organization (xem organization() bên
     * trên) chứ không chỉ là một trang rời: đó là trường schema.org dành đúng cho
     * "quy tắc biên tập của nhà xuất bản này nằm ở đâu".
     */
    public static function editorialPage(string $url, string $home): string
    {
        return self::encode([
            '@type' => 'WebPage',
            'name' => 'Editorial standards',
            'url' => $url,
            'inLanguage' => 'en',
            'isPartOf' => ['@type' => 'WebSite', 'name' => 'Stories', 'url' => $home],
            'about' => ['@type' => 'Organization', 'name' => 'Stories', 'url' => $home],
        ]);
    }

    /**
     * Trang hồ sơ người biên tập. ProfilePage + mainEntity Person là dạng Google
     * khuyến nghị cho trang giới thiệu một người.
     */
    public static function editorPage(string $url, string $home, string $name, ?string $email): string
    {
        return self::encode([
            '@type' => 'ProfilePage',
            'name' => $name.' — editor',
            'url' => $url,
            'inLanguage' => 'en',
            'isPartOf' => ['@type' => 'WebSite', 'name' => 'Stories', 'url' => $home],
            'mainEntity' => array_filter([
                '@type' => 'Person',
                'name' => $name,
                'url' => $url,
                'jobTitle' => 'Editor',
                'email' => $email,
                'worksFor' => ['@type' => 'Organization', 'name' => 'Stories', 'url' => $home],
            ]),
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
