<?php

declare(strict_types=1);

namespace App\Services;

use App\Models\Chapter;
use App\Models\FacebookPost;
use App\Models\Story;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Storage;
use RuntimeException;

/**
 * Đăng truyện lên Page Facebook TunaStory qua Graph API: một bài ẢNH (bìa dọc của truyện) kèm
 * câu mồi + đoạn mở đầu chương 1 + câu mời bình luận, rồi Page tự bình luận link đọc tiếp.
 *
 * LINK KHÔNG nằm trong thân bài mà ở bình luận đầu tiên (Tuấn chọn 02/11/2026): Facebook hạ tiếp
 * cận và có thể hạn chế Page đăng link ra ngoài dày đặc. Hệ quả: bài lên mà bình luận hỏng thì
 * người đọc không có đường đi tiếp — commentPending() gửi bù ở lượt sau.
 *
 * Chỉ truyện index được, có câu mồi và có bìa. Cùng nguồn với sitemap: Facebook dẫn người đọc về
 * trang Google được xếp hạng và có quảng cáo.
 */
class FacebookPublisher
{
    /** Độ dài đoạn mở đầu trích vào bài (số từ) — cùng ngưỡng với trang truyện trên web. */
    private const OPENING_MIN = 120;

    private const OPENING_MAX = 220;

    /**
     * Câu mời bình luận, xoay vòng theo số bài đã đăng. Không lặp nguyên văn một câu ở mọi bài:
     * 24 bài/ngày cùng một câu là dấu hiệu spam rõ nhất với bộ lọc của Facebook.
     */
    private const CTAS = [
        'Do you want to keep reading? Comment below 👇',
        'Want to know what happens next? Let us know in the comments 👇',
        'What would you have done in this situation? Tell us in the comments 👇',
        'Should we share how this ends? Comment below 👇',
        'Would you have stayed quiet? Tell us below 👇',
        'Has anything like this ever happened to you? Share in the comments 👇',
    ];

    public function enabled(): bool
    {
        return (bool) config('services.facebook.enabled')
            && filled(config('services.facebook.page_id'))
            && filled(config('services.facebook.page_token'));
    }

    /** Truyện kế tiếp chưa đăng: nhiều lượt xem trước, rồi mới hơn trước. */
    public function next(): ?Story
    {
        return Story::query()
            ->visibleOnWeb()
            ->where('noindex', false)
            ->whereNotNull('hook')->where('hook', '!=', '')
            ->whereNotNull('thumbnail')
            ->whereNotIn('id', array_map('intval', (array) config('services.facebook.exclude')))
            ->whereDoesntHave('facebookPost')
            ->whereHas('chapters')
            ->orderByDesc('views')
            ->orderByDesc('id')
            ->first();
    }

    /** Nội dung bài: câu mồi, đoạn mở đầu chương 1, câu mời bình luận. KHÔNG có link. */
    public function caption(Story $story, int $index): string
    {
        $first = $story->chapters()->orderBy('number')->first();
        $paragraphs = $first ? $this->paragraphs((string) $first->content) : [];

        $out = [];
        $words = 0;
        foreach ($paragraphs as $p) {
            $n = str_word_count($p);
            if ($out !== [] && $words + $n > self::OPENING_MAX) {
                break;
            }
            $out[] = $p;
            $words += $n;
            if ($words >= self::OPENING_MIN) {
                break;
            }
        }

        // Câu mồi nhiều truyện chính là câu mở đầu chương 1 — trùng thì không nhắc lại.
        $hook = trim((string) $story->hook);
        $norm = static fn (string $s): string => (string) preg_replace('/[^a-z0-9]/', '', strtolower($s));
        $lead = $hook !== '' && ! str_contains($norm(implode(' ', array_slice($paragraphs, 0, 2))), substr($norm($hook), 0, 40))
            ? [$hook]
            : [];

        $body = implode("\n\n", [...$lead, ...$out]);
        if (! preg_match('/[.!?…"”’]$/u', $body)) {
            $body .= '…';
        }

        return $body."\n\n".self::CTAS[$index % count(self::CTAS)]."\n📖 Full story in the comments";
    }

    public function comment(Story $story): string
    {
        return '📖 Read the full story free here: '.$this->storyUrl($story);
    }

    /** Đăng một truyện: bài ảnh rồi bình luận link. Trả về dòng facebook_posts đã ghi. */
    public function publish(Story $story): FacebookPost
    {
        $path = Storage::disk('public')->path((string) $story->thumbnail);
        if (! is_file($path)) {
            throw new RuntimeException("Không thấy file bìa: {$story->thumbnail}");
        }

        $index = FacebookPost::query()->count();
        $photo = Http::timeout(60)
            ->attach('source', (string) file_get_contents($path), basename($path))
            ->post($this->endpoint((string) config('services.facebook.page_id').'/photos'), [
                'message' => $this->caption($story, $index),
                'access_token' => config('services.facebook.page_token'),
            ])
            ->throw()
            ->json();

        // Bài ảnh trả về {id: <photo id>, post_id: <pageid_postid>} — bình luận phải gắn vào post_id.
        $record = FacebookPost::query()->create([
            'story_id' => $story->id,
            'post_id' => $photo['post_id'] ?? $photo['id'] ?? null,
            'posted_at' => now(),
        ]);

        $this->postComment($record);

        return $record;
    }

    /**
     * Gửi bù bình luận link cho những bài đã lên mà chưa có. Trả về số bình luận gửi được.
     */
    public function commentPending(): int
    {
        $done = 0;
        foreach (FacebookPost::query()->whereNotNull('post_id')->whereNull('comment_id')->with('story')->get() as $record) {
            if ($record->story && $this->postComment($record)) {
                $done++;
            }
        }

        return $done;
    }

    private function postComment(FacebookPost $record): bool
    {
        $response = Http::asForm()->timeout(30)->post($this->endpoint($record->post_id.'/comments'), [
            'message' => $this->comment($record->story),
            'access_token' => config('services.facebook.page_token'),
        ]);

        if ($response->failed()) {
            $record->update(['error' => 'comment: '.mb_substr($response->body(), 0, 1000)]);

            return false;
        }

        $record->update(['comment_id' => $response->json('id'), 'error' => null]);

        return true;
    }

    /**
     * Link trên tên miền bản web. KHÔNG dùng route() tuyệt đối: lệnh chạy từ console nên route()
     * lấy APP_URL = api.tunastory.com. Cùng lý do với IndexNow.
     */
    private function storyUrl(Story $story): string
    {
        return rtrim((string) config('services.indexnow.site'), '/').route('public.story', $story, false);
    }

    private function endpoint(string $path): string
    {
        return 'https://graph.facebook.com/'.config('services.facebook.graph_version').'/'.$path;
    }

    /** @return list<string> */
    private function paragraphs(string $content): array
    {
        $parts = preg_split('/\n\s*\n/', str_replace(["\r\n", "\r"], "\n", $content)) ?: [];

        return array_values(array_filter(array_map(
            static fn (string $p): string => trim(preg_replace('/\s*\n\s*/', ' ', $p) ?? ''),
            $parts,
        ), static fn (string $p): bool => $p !== ''));
    }
}
