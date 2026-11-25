<?php

declare(strict_types=1);

namespace App\Support;

use App\Models\Story;
use Illuminate\Support\Facades\Cache;

/**
 * Tìm truyện trong app theo tên truyện mà trang web KHÁC đang hiện, cho script nhúng
 * /embed/app-prompt.js: khớp được thì hộp mời hiện bìa + tên và mời "đọc tiếp trong app";
 * không khớp thì hộp mời nói chung chung, không hứa có truyện đó.
 *
 * Chỉ khớp CHÍNH XÁC sau khi chuẩn hoá (chữ thường, bỏ dấu câu, bỏ "Chapter 3"/"Part 2",
 * thử từng đoạn quanh " | " và " - " vì trang thường ghép tên site vào tựa). Không khớp gần
 * đúng: hứa sai truyện còn tệ hơn không hứa gì.
 *
 * So với cả `title` lẫn `hook` (tựa kiểu Facebook): các blog đăng truyện hay dùng câu mở
 * đầu dài làm tựa bài, và đó thường chính là hook bên Stories. Truyện ẩn trên web
 * (hidden_on_web) vẫn khớp vì app vẫn hiện chúng.
 */
final class EmbedStoryMatcher
{
    /** Ngắn hơn thì dễ trùng nhầm ("Revenge", "The Wedding"). */
    private const MIN_KEY_LENGTH = 10;

    private const CACHE_KEY = 'embed:story-keys:v1';

    public static function normalize(string $text): string
    {
        $text = mb_strtolower(html_entity_decode($text, ENT_QUOTES | ENT_HTML5, 'UTF-8'));
        // Dấu nháy cong và nháy thẳng coi như nhau, rồi bỏ hẳn: "Mom's" = "Moms".
        $text = (string) preg_replace("/['\u{2018}\u{2019}`]/u", '', $text);
        $text = (string) preg_replace('/\b(chapter|chap|ch|part|pt|episode|ep)\.?\s*\d+\b/u', ' ', $text);
        $text = (string) preg_replace('/[^\p{L}\p{N}]+/u', ' ', $text);

        return trim((string) preg_replace('/\s+/u', ' ', $text));
    }

    /**
     * Các khoá cần thử cho một chuỗi tựa: cả chuỗi, rồi từng đoạn quanh dấu ngăn có
     * khoảng trắng hai bên (" | ", " - ", " – ", " — ", " :: "), đoạn dài trước.
     *
     * @return list<string>
     */
    public static function candidateKeys(string $title): array
    {
        $parts = preg_split('/\s+(?:\||-|–|—|::|»)\s+/u', $title) ?: [];
        usort($parts, static fn ($a, $b) => mb_strlen($b) <=> mb_strlen($a));

        $keys = [];
        foreach ([$title, ...$parts] as $part) {
            $key = self::normalize((string) $part);
            if (mb_strlen($key) >= self::MIN_KEY_LENGTH && ! in_array($key, $keys, true)) {
                $keys[] = $key;
            }
        }

        return $keys;
    }

    /** @param list<string> $titles */
    public static function match(array $titles): ?Story
    {
        $index = self::index();

        foreach ($titles as $title) {
            foreach (self::candidateKeys($title) as $key) {
                if (isset($index[$key])) {
                    return Story::find($index[$key]);
                }
            }
        }

        return null;
    }

    /**
     * khoá chuẩn hoá => id truyện. Tựa thắng hook khi trùng khoá; tựa trùng nhau thì
     * truyện cũ hơn (id nhỏ) thắng. Lưu 10 phút: truyện mới đăng chậm tối đa chừng đó.
     *
     * @return array<string, int>
     */
    private static function index(): array
    {
        return Cache::remember(self::CACHE_KEY, 600, static function (): array {
            $byTitle = [];
            $byHook = [];

            Story::query()->orderBy('id')->select(['id', 'title', 'hook'])
                ->each(static function (Story $story) use (&$byTitle, &$byHook): void {
                    $title = self::normalize((string) $story->title);
                    if (mb_strlen($title) >= self::MIN_KEY_LENGTH) {
                        $byTitle[$title] ??= $story->id;
                    }
                    $hook = self::normalize((string) $story->hook);
                    if (mb_strlen($hook) >= self::MIN_KEY_LENGTH) {
                        $byHook[$hook] ??= $story->id;
                    }
                });

            return $byTitle + $byHook;
        });
    }
}
