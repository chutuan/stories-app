<?php

declare(strict_types=1);

namespace App\Services;

use App\Models\Chapter;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Storage;
use RuntimeException;
use Throwable;

/**
 * Sinh giọng đọc cho từng chương bằng OpenAI TTS (gpt-4o-mini-tts).
 *
 * Vì sao generate ở SERVER chứ không đọc trên máy:
 *  - API key không được nằm trong app mobile.
 *  - Generate 1 lần rồi lưu MP3 -> nghe bao nhiêu lần cũng không tốn thêm tiền.
 *  - App chỉ tải `audio_url` về phát (expo-audio), không dùng TTS của hệ điều hành.
 *
 * Giọng và sắc thái được chọn theo THỂ LOẠI của truyện, nhờ tham số `instructions`
 * của gpt-4o-mini-tts (model này điều khiển được cách đọc bằng câu lệnh).
 *
 * LƯU Ý: nội dung truyện là TIẾNG ANH, nên mọi câu lệnh `instructions` phải viết
 * bằng tiếng Anh và yêu cầu model đọc bằng tiếng Anh.
 */
class ChapterAudioGenerator
{
    private const ENDPOINT = 'https://api.openai.com/v1/audio/speech';

    /**
     * Hồ sơ giọng đọc theo thể loại.
     * Khớp theo slug thể loại; truyện nhiều thể loại thì lấy hồ sơ khớp đầu tiên.
     *
     * @var array<string, array{voice: string, instructions: string}>
     */
    private const PROFILES = [
        'billionaire' => [
            'voice' => 'onyx',
            'instructions' => 'Narrate in English. You are the narrator of a modern hidden-billionaire drama. Use a confident, composed male voice with quiet authority and a steady, unhurried pace. Stay understated while the hero is dismissed as poor, then let controlled power fill the voice when his fortune surfaces. Never sound theatrical or rushed.',
        ],
        'ceo' => [
            'voice' => 'onyx',
            'instructions' => 'Narrate in English. You are the narrator of a corporate boardroom drama. Use a confident, level male voice with executive calm and a firm, measured rhythm. Keep boardroom dialogue crisp and controlled; drop the pitch and slow down on the lines where the chairman finally speaks. Authority, not aggression.',
        ],
        'secret-identity' => [
            'voice' => 'ballad',
            'instructions' => 'Narrate in English. You are the narrator of a hidden-identity story. Use a low, mysterious voice that holds something back. Read quietly and deliberately, leaving a beat of silence before the moment the true identity is revealed, then land that reveal with calm weight. Let the tension come from restraint, not volume.',
        ],
        'romance' => [
            'voice' => 'coral',
            'instructions' => 'Narrate in English. You are the narrator of a modern romance. Use a warm, tender female voice full of feeling. Read slowly and affectionately, softening on intimate dialogue and lingering on emotional beats. Never sound flat or robotic.',
        ],
        'revenge' => [
            'voice' => 'ash',
            'instructions' => 'Narrate in English. You are the narrator of a revenge story. Use a cold, sharp voice with tightly held-back anger. Keep the pace controlled and clipped as the humiliation builds, then let the payoff lines land hard and decisively. Cold precision, never shouting.',
        ],
        'family-drama' => [
            'voice' => 'sage',
            'instructions' => "Narrate in English. You are the narrator of a family drama. Use a warm, grounded storyteller's voice, as if telling the story of a household you know well. Keep the pace natural and conversational, give each family member's dialogue its own colour, and soften on moments of hurt or reconciliation.",
        ],
        'rags-to-riches' => [
            'voice' => 'ash',
            'instructions' => 'Narrate in English. You are the narrator of a rags-to-riches story. Use an inspiring voice that gathers momentum. Start plain and humble in the early hardship, then build energy and lift through the rise, ending scenes with confident, uplifting drive.',
        ],
        'second-chance' => [
            'voice' => 'sage',
            'instructions' => 'Narrate in English. You are the narrator of a second-chance story. Use a hopeful, gentle voice with quiet resilience. Read softly and reflectively when looking back on what was lost, and let the tone rise steadily with renewed determination as the character climbs back.',
        ],
    ];

    private const FALLBACK_PROFILE = [
        'voice' => 'ash',
        'instructions' => 'Narrate in English. You are an audiobook narrator. Use a warm, expressive voice at a moderate pace. Pause naturally at punctuation and shade the delivery with the emotion of each passage. Never read flatly like a machine.',
    ];

    /**
     * Sinh MP3 cho 1 chương và lưu vào disk public.
     *
     * Cập nhật `chapters.audio_status` xuyên suốt (processing -> done | failed) để admin
     * theo dõi được khi chạy trong hàng đợi (App\Jobs\GenerateChapterAudio).
     *
     * @param  bool  $force  ghi đè nếu chương đã có audio
     * @return string đường dẫn tương đối đã lưu (vd `audio/3/2.mp3`)
     *
     * @throws Throwable lỗi được ghi vào audio_status/audio_error rồi ném tiếp ra ngoài
     */
    public function generate(Chapter $chapter, bool $force = false): string
    {
        $this->markStatus($chapter, 'processing');

        try {
            $path = $this->run($chapter, $force);
        } catch (Throwable $e) {
            $this->markStatus($chapter, 'failed', $e->getMessage());

            throw $e;
        }

        $this->markStatus($chapter, 'done');

        return $path;
    }

    /**
     * Ghi trạng thái sinh audio vào chương.
     * Dùng forceFill vì audio_status/audio_error không nằm trong $fillable (chỉ hệ thống ghi).
     */
    private function markStatus(Chapter $chapter, string $status, ?string $error = null): void
    {
        $chapter->forceFill([
            'audio_status' => $status,
            'audio_error' => $error === null ? null : mb_substr($error, 0, 1000),
        ])->save();
    }

    /** Phần việc thật sự: gọi OpenAI TTS rồi lưu file. */
    private function run(Chapter $chapter, bool $force): string
    {
        $apiKey = (string) config('services.openai.key');
        if ($apiKey === '') {
            throw new RuntimeException('Chưa cấu hình OPENAI_API_KEY trong backend/.env');
        }

        if (! $force && $chapter->audio_path && Storage::disk('public')->exists($chapter->audio_path)) {
            return $chapter->audio_path;
        }

        $text = trim((string) $chapter->content);
        if ($text === '') {
            throw new RuntimeException("Chương {$chapter->number} không có nội dung để đọc.");
        }

        $profile = $this->profileFor($chapter);
        $chunks = $this->chunk($text, (int) config('services.openai.tts_chunk_chars', 3500));

        $parts = [];
        try {
            foreach ($chunks as $i => $chunk) {
                $parts[] = $this->synthesize($apiKey, $chunk, $profile, $i + 1, count($chunks));
            }

            $mp3 = count($parts) === 1
                ? file_get_contents($parts[0])
                : $this->concat($parts);

            $path = "audio/{$chapter->story_id}/{$chapter->number}.mp3";
            Storage::disk('public')->put($path, $mp3);

            // Xoá file cũ nếu đổi đường dẫn
            if ($chapter->audio_path && $chapter->audio_path !== $path) {
                Storage::disk('public')->delete($chapter->audio_path);
            }

            $chapter->forceFill(['audio_path' => $path])->save();

            return $path;
        } finally {
            foreach ($parts as $tmp) {
                @unlink($tmp);
            }
        }
    }

    /**
     * Thứ tự ƯU TIÊN khi truyện thuộc nhiều thể loại.
     * Thể loại có chất giọng đặc trưng hơn được ưu tiên trước (revenge, romance, secret-identity...),
     * thể loại chung chung về địa vị/tài sản (ceo, billionaire) xếp sau.
     *
     * Duyệt theo danh sách CỐ ĐỊNH này thay vì theo thứ tự thể loại do DB trả về,
     * để cùng một truyện luôn cho ra cùng một giọng ở mọi lần chạy.
     *
     * @var list<string>
     */
    private const PROFILE_PRIORITY = [
        'revenge',
        'romance',
        'secret-identity',
        'family-drama',
        'second-chance',
        'rags-to-riches',
        'ceo',
        'billionaire',
    ];

    /** Chọn hồ sơ giọng theo thể loại đặc trưng nhất của truyện (tất định). */
    public function profileFor(Chapter $chapter): array
    {
        $story = $chapter->story()->with('categories')->first();
        $slugs = collect($story?->categories ?? [])->pluck('slug')->all();

        foreach (self::PROFILE_PRIORITY as $slug) {
            if (in_array($slug, $slugs, true) && isset(self::PROFILES[$slug])) {
                return self::PROFILES[$slug];
            }
        }

        return self::FALLBACK_PROFILE;
    }

    /**
     * Cắt nội dung thành các đoạn <= $limit ký tự, CẮT THEO RANH GIỚI CÂU
     * để giọng đọc không bị đứt giữa chừng.
     *
     * @return list<string>
     */
    public function chunk(string $text, int $limit): array
    {
        if (mb_strlen($text) <= $limit) {
            return [$text];
        }

        // Tách theo câu (giữ lại dấu câu), rồi gom lại cho tới sát giới hạn.
        $sentences = preg_split('/(?<=[.!?…:;])\s+|\n{2,}/u', $text, -1, PREG_SPLIT_NO_EMPTY) ?: [$text];

        $chunks = [];
        $current = '';
        foreach ($sentences as $sentence) {
            $sentence = trim($sentence);
            if ($sentence === '') {
                continue;
            }

            // Câu đơn lẻ dài hơn giới hạn -> buộc phải cắt cứng theo ký tự.
            if (mb_strlen($sentence) > $limit) {
                if ($current !== '') {
                    $chunks[] = $current;
                    $current = '';
                }
                foreach (mb_str_split($sentence, $limit) as $piece) {
                    $chunks[] = $piece;
                }

                continue;
            }

            $candidate = $current === '' ? $sentence : $current.' '.$sentence;
            if (mb_strlen($candidate) > $limit) {
                $chunks[] = $current;
                $current = $sentence;
            } else {
                $current = $candidate;
            }
        }

        if ($current !== '') {
            $chunks[] = $current;
        }

        return $chunks;
    }

    /** Gọi OpenAI TTS cho 1 đoạn, trả về đường dẫn file tạm. */
    private function synthesize(string $apiKey, string $text, array $profile, int $index, int $total): string
    {
        $instructions = $profile['instructions'];
        if ($total > 1) {
            $instructions .= " This is part {$index} of {$total} of one chapter; keep the tone and pacing consistent with the other parts.";
        }

        $response = Http::withToken($apiKey)
            ->timeout(180)
            ->retry(2, 2000, throw: false)
            ->post(self::ENDPOINT, [
                'model' => config('services.openai.tts_model', 'gpt-4o-mini-tts'),
                'input' => $text,
                'voice' => $profile['voice'],
                'instructions' => $instructions,
                'response_format' => 'mp3',
            ]);

        if (! $response->successful()) {
            throw new RuntimeException(
                "OpenAI TTS lỗi (HTTP {$response->status()}): ".mb_substr($response->body(), 0, 300)
            );
        }

        $tmp = tempnam(sys_get_temp_dir(), 'tts_').'.mp3';
        file_put_contents($tmp, $response->body());

        return $tmp;
    }

    /** Nối nhiều MP3 thành một. Dùng ffmpeg nếu có (sạch hơn), không thì nối nhị phân. */
    private function concat(array $files): string
    {
        $ffmpeg = trim((string) shell_exec('command -v ffmpeg 2>/dev/null'));

        if ($ffmpeg !== '') {
            $listFile = tempnam(sys_get_temp_dir(), 'ttslist_');
            $out = tempnam(sys_get_temp_dir(), 'ttsout_').'.mp3';
            file_put_contents(
                $listFile,
                implode("\n", array_map(fn ($f) => "file '".str_replace("'", "'\\''", $f)."'", $files))
            );

            $cmd = escapeshellarg($ffmpeg).' -y -f concat -safe 0 -i '.escapeshellarg($listFile)
                .' -c copy '.escapeshellarg($out).' 2>/dev/null';
            shell_exec($cmd);
            @unlink($listFile);

            if (is_file($out) && filesize($out) > 0) {
                $data = file_get_contents($out);
                @unlink($out);

                return $data;
            }
            @unlink($out);
        }

        // Dự phòng: nối nhị phân — trình phát MP3 vẫn đọc được chuỗi khung liên tiếp.
        return implode('', array_map(fn ($f) => file_get_contents($f), $files));
    }
}
