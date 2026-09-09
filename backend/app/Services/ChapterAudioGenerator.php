<?php

declare(strict_types=1);

namespace App\Services;

use App\Models\Chapter;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Storage;
use RuntimeException;

/**
 * Sinh giọng đọc cho từng chương bằng OpenAI TTS (gpt-4o-mini-tts).
 *
 * Vì sao generate ở SERVER chứ không đọc trên máy:
 *  - API key không được nằm trong app mobile.
 *  - Generate 1 lần rồi lưu MP3 -> nghe bao nhiêu lần cũng không tốn thêm tiền.
 *  - App chỉ tải `audio_url` về phát (expo-audio), không dùng TTS của hệ điều hành.
 *
 * Giọng và sắc thái được chọn theo THỂ LOẠI của truyện, nhờ tham số `instructions`
 * của gpt-4o-mini-tts (model này điều khiển được cách đọc bằng câu lệnh tiếng Việt).
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
        'tien-hiep' => [
            'voice' => 'onyx',
            'instructions' => 'Bạn là người kể chuyện truyện tiên hiệp Việt Nam. Giọng nam trầm, khí phách và có chiều sâu. Kể chậm rãi, trang trọng; nhấn mạnh ở các đoạn đột phá cảnh giới và giao chiến, hạ giọng ở đoạn tả cảnh. Ngắt nghỉ rõ giữa các đoạn để tạo không khí sử thi.',
        ],
        'kiem-hiep' => [
            'voice' => 'onyx',
            'instructions' => 'Bạn là người kể chuyện kiếm hiệp Việt Nam. Giọng nam trầm, dứt khoát, hào sảng. Đọc nhanh và gấp gáp ở đoạn tỉ thí, chậm và lắng lại ở đoạn tâm tình. Tạo kịch tính bằng khoảng lặng ngắn trước các câu quyết định.',
        ],
        'huyen-huyen' => [
            'voice' => 'ash',
            'instructions' => 'Bạn là người kể chuyện huyền huyễn Việt Nam. Giọng nam vang, huyền bí, nhiều biến hoá. Nhấn nhá ở các chi tiết pháp thuật và bí cảnh, giữ nhịp dồn dập ở cao trào.',
        ],
        'ngon-tinh' => [
            'voice' => 'coral',
            'instructions' => 'Bạn là người kể chuyện ngôn tình Việt Nam. Giọng nữ ấm áp, dịu dàng và giàu cảm xúc. Đọc chậm, tình cảm; mềm giọng ở lời thoại nhân vật nữ, lắng xuống ở đoạn tâm trạng. Tránh đọc đều đều như máy.',
        ],
        'dam-my' => [
            'voice' => 'sage',
            'instructions' => 'Bạn là người kể chuyện đam mỹ Việt Nam. Giọng nhẹ nhàng, tinh tế, nhiều cảm xúc tiết chế. Đọc êm, chú ý ngắt nghỉ tự nhiên ở lời thoại.',
        ],
        'do-thi' => [
            'voice' => 'ash',
            'instructions' => 'Bạn là người kể chuyện đô thị hiện đại Việt Nam. Giọng nam trẻ trung, tiết tấu nhanh, tự nhiên như đang kể cho bạn bè nghe. Sinh động ở lời thoại, gọn gàng ở đoạn tường thuật.',
        ],
        'trong-sinh' => [
            'voice' => 'ash',
            'instructions' => 'Bạn là người kể chuyện trọng sinh Việt Nam. Giọng nam chắc chắn, có chút bồi hồi khi nhắc chuyện kiếp trước, quyết đoán khi nhân vật hành động ở kiếp này.',
        ],
        'linh-di' => [
            'voice' => 'ballad',
            'instructions' => 'Bạn là người kể chuyện linh dị Việt Nam. Giọng trầm, chậm, thì thầm và rợn người. Kéo dài khoảng lặng trước các chi tiết đáng sợ, hạ giọng gần như thì thào ở đoạn cao trào.',
        ],
    ];

    private const FALLBACK_PROFILE = [
        'voice' => 'ash',
        'instructions' => 'Bạn là người kể chuyện tiếng Việt. Giọng ấm, truyền cảm, tiết tấu vừa phải. Ngắt nghỉ tự nhiên theo dấu câu, nhấn nhá theo cảm xúc của đoạn văn. Tránh đọc đều đều như máy.',
    ];

    /**
     * Sinh MP3 cho 1 chương và lưu vào disk public.
     *
     * @param  bool  $force  ghi đè nếu chương đã có audio
     * @return string đường dẫn tương đối đã lưu (vd `audio/3/2.mp3`)
     */
    public function generate(Chapter $chapter, bool $force = false): string
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
     * Thể loại có chất giọng đặc trưng hơn được ưu tiên trước (linh dị, ngôn tình...),
     * thể loại chung chung (huyền huyễn) xếp sau.
     *
     * Duyệt theo danh sách CỐ ĐỊNH này thay vì theo thứ tự thể loại do DB trả về,
     * để cùng một truyện luôn cho ra cùng một giọng ở mọi lần chạy.
     *
     * @var list<string>
     */
    private const PROFILE_PRIORITY = [
        'linh-di',
        'ngon-tinh',
        'dam-my',
        'kiem-hiep',
        'tien-hiep',
        'trong-sinh',
        'do-thi',
        'huyen-huyen',
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
            $instructions .= " Đây là phần {$index}/{$total} của một chương, hãy giữ giọng điệu nhất quán với các phần khác.";
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
