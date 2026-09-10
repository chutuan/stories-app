<?php

declare(strict_types=1);

namespace App\Services;

use App\Models\Story;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Storage;
use RuntimeException;
use Throwable;

/**
 * Sinh ẢNH BÌA như ảnh chụp thật cho truyện bằng OpenAI Images (gpt-image-1).
 *
 * Thay cho bộ vẽ bằng GD ở database/seeders/covers/generate-covers.php: ảnh đó là
 * hình khối trừu tượng, không có người, nên nhìn rất "app tự chế".
 *
 * Vì sao generate ở SERVER (giống hệt lý do của giọng đọc, xem ChapterAudioGenerator):
 *  - API key không được nằm trong app mobile.
 *  - Sinh 1 lần rồi lưu JPEG -> mọi lượt xem sau không tốn thêm tiền.
 *  - Tên file giữ nguyên `stories/<slug>.jpg`, PublicFileUrl tự gắn `?v=<mtime>`
 *    nên client thấy ảnh mới ngay, không kẹt cache.
 *
 * HAI BƯỚC, và bước 1 mới là điểm mấu chốt:
 *  1. ĐỌC TRUYỆN rồi viết chỉ đạo hình ảnh: gửi tiêu đề + mô tả + trích đoạn chương 1
 *     cho một model văn bản, bắt nó chọn ra MỘT khoảnh khắc có thật trong truyện và
 *     tả lại thành brief chụp ảnh. Không làm bước này thì ảnh chỉ "đúng thể loại"
 *     chứ không đúng bối cảnh của chính câu chuyện.
 *  2. Dựng prompt cuối = brief + tông hình ảnh theo thể loại + ràng buộc kỹ thuật,
 *     rồi gọi model ảnh.
 *
 * LƯU Ý: truyện viết bằng TIẾNG ANH nên toàn bộ prompt cũng phải bằng tiếng Anh.
 */
class StoryCoverGenerator
{
    private const IMAGE_ENDPOINT = 'https://api.openai.com/v1/images/generations';

    private const CHAT_ENDPOINT = 'https://api.openai.com/v1/chat/completions';

    /** Số ký tự chương 1 gửi kèm để model nắm được bối cảnh mở đầu. */
    private const EXCERPT_CHARS = 1800;

    /** Kích thước ảnh bìa cuối cùng lưu vào disk (khớp bộ ảnh cũ, tỉ lệ 3:4). */
    private const OUT_WIDTH = 600;

    private const OUT_HEIGHT = 800;

    /**
     * Tông hình ảnh theo thể loại — vai trò giống hệt bảng giọng đọc bên audio:
     * quyết định ánh sáng, bảng màu, cảm xúc khung hình. NỘI DUNG cảnh thì lấy từ
     * chính truyện (bước 1), bảng này chỉ quyết định "chụp kiểu gì".
     *
     * @var array<string, string>
     */
    private const PROFILES = [
        'billionaire' => 'Night-time wealth with real colour: deep navy offset by warm gold spilling from chandeliers, headlights or a lit skyline behind them. Hard rim light along jaw and shoulders so the figures cut away from the dark, and enough spill on the faces to read every expression. Cold blue shadow against warm gold highlight — never a flat grey night and never a blacked-out frame.',
        'ceo' => 'Hard daylight raking through floor-to-ceiling glass, cutting sharp geometric shadows across the room and the faces. Steel grey and white, broken by ONE saturated accent — a deep red tie, amber whisky, a green sign light. Crisp, controlled, faintly intimidating.',
        'secret-identity' => 'Split lighting: the protagonist\'s face clearly and brightly lit from one side, the other side falling to shadow but never to black. Teal shadow against warm amber key. The mystery comes from what is BEHIND them being expensive while they are not — never from hiding anyone in murk.',
        'romance' => 'Golden-hour or lamplight with visible glow and gentle lens bloom around the highlights. Saturated amber, blush and cream, warm and rich rather than pale. Faces near each other, shallow focus softening the room without erasing it.',
        'revenge' => 'High contrast hard side light, saturated cold blue ground with ONE hot accent — brake lights, a fire, a lit doorway. Rain, wet glass or drifting smoke to catch the light. Keep every face lit enough to read. Absolute stillness in the posture that reads as threat.',
        'family-drama' => 'Warm practical light from a kitchen bulb, a table lamp or a window, rich saturated domestic colour — patterned wallpaper, worn wood, a bright rug, children\'s things underfoot. Honest and lived-in, never drab or grey. Several people in one room, caught mid-feeling.',
        'rags-to-riches' => 'Low golden sun down a working street, long shadows, dust and haze catching the light. Saturated ochre, faded denim blue and warm concrete, strong contrast. Camera slightly low so the frame lifts.',
        'second-chance' => 'Blue hour: deep teal sky and street, with a warm amber window or open doorway burning as the single accent against it. Calm, spacious, cinematic, faces caught in that warm spill. Cool ground, warm highlight, no muddy middle.',
    ];

    private const FALLBACK_PROFILE = 'Natural cinematic lighting from real lamps and windows, a believable lived-in everyday setting, warm restrained colour grade, every face lit enough to read.';

    /**
     * Thứ tự ưu tiên khi truyện có NHIỀU thể loại — giữ đúng cách làm của audio để
     * một truyện luôn ra cùng một tông, không phụ thuộc thứ tự bản ghi trong DB.
     *
     * @var list<string>
     */
    private const PROFILE_PRIORITY = [
        'secret-identity',
        'revenge',
        'romance',
        'family-drama',
        'second-chance',
        'rags-to-riches',
        'ceo',
        'billionaire',
    ];

    /**
     * Ràng buộc kỹ thuật gắn vào MỌI prompt ảnh.
     * Quan trọng nhất là dòng cấm chữ: model ảnh rất hay tự bịa tiêu đề lên bìa,
     * mà bìa trong app đã có chữ đè lên rồi.
     */
    private const CONSTRAINTS = <<<'TXT'
    Style and technical requirements:
    - ABSOLUTELY NO TEXT ANYWHERE. No letters, words, numbers, logos, brand names, signage, posters, newspapers, name plates, screens with writing or watermarks — including on buildings, clothing and props. If a surface would normally carry a sign, leave it blank.
    - A CINEMATIC FILM STILL, not a portrait and not a symbol. Medium-wide framing that keeps the whole room and everyone in it readable, the way a frame grab from a prestige television drama looks. The protagonist stands nearest the camera; the other people are further back but still large enough to read their posture and what they are doing.
    - THUMBNAIL TEST: this image is seen about 150 pixels wide on a phone, next to other covers. At that size a stranger must still be able to tell who is upset, who they are upset with, and roughly where this is happening. Achieve that with clear separation between figures, distinct blocks of colour, and the protagonist's face lit brighter than anything else — NOT by cropping in to a headshot.
    - EXPOSE FOR A LIT ROOM, not for a mood. Overall the frame should read bright and richly coloured, like a daytime television drama — practical lamps on, daylight through windows, walls and floors clearly visible in their real colours. Deep shadows are allowed in corners, never across the people.
    - A CAUGHT MOMENT, not a posed line-up. NOBODY LOOKS INTO THE LENS — not one person. People are turned towards each other, mid-gesture, mid-step, mid-sentence.
    - CONNECT THE TWO SIDES WITH EYE-LINES. The people in the frame must be looking at, pointing at, reaching for or turning away from EACH OTHER, so a stranger can trace who is doing something to whom. Three people staring in three different directions is a group portrait, not a scene — it fails even when every other rule is met.
    - NEVER a silhouette, never a lone figure against an empty background, never a mostly dark frame. Every face that matters must be lit well enough to read the expression. If the scene is at night, the light comes from lamps, windows and open doorways inside the frame.
    - Rich saturated colour and real contrast between deep shadow and bright highlight. Avoid flat grey, muddy shadow, and a washed-out low-contrast look.
    - A photorealistic photograph, as if shot on a full-frame camera with a 35mm lens at f/2.8: enough depth of field that the room and everyone in it stays readable, true-to-life skin texture with visible pores and imperfections, believable wear on clothing. Never an illustration, painting, 3D render, CGI or AI-art look. (An 85mm portrait lens is wrong for this — it collapses the scene into a headshot.)
    - Real, ordinary-looking people with expressive faces. Not fashion models, not airbrushed, not symmetrical beauty.
    - Fill the setting with the specific, ordinary objects the scene calls for — the toys, the paint tins, the luggage, the paperwork, the food left out. An empty, tidy, generic room reads as stock photography and kills the drama.
    - Vertical 3:4 portrait. Keep the top-left corner and the bottom-right corner free of important detail — small badges are drawn over those two corners.
    - Do not depict any real, famous or identifiable person.
    TXT;

    /**
     * Sinh ảnh bìa cho 1 truyện và lưu vào disk public.
     *
     * Cập nhật `stories.cover_status` xuyên suốt (processing -> done | failed) để theo dõi
     * được khi chạy trong hàng đợi (App\Jobs\GenerateStoryCover).
     *
     * @param  bool  $force  vẽ lại kể cả khi đã có ảnh bìa
     * @return string đường dẫn tương đối đã lưu (vd `stories/the-janitor-owns-the-company.jpg`)
     *
     * @throws Throwable lỗi được ghi vào cover_status/cover_error rồi ném tiếp ra ngoài
     */
    public function generate(Story $story, bool $force = false): string
    {
        $this->markStatus($story, 'processing');

        try {
            $path = $this->run($story, $force);
        } catch (Throwable $e) {
            $this->markStatus($story, 'failed', $e->getMessage());

            throw $e;
        }

        $this->markStatus($story, 'done');

        return $path;
    }

    /**
     * Ghi trạng thái sinh ảnh vào truyện.
     * Dùng forceFill vì cover_status/cover_error không nằm trong $fillable (chỉ hệ thống ghi).
     */
    private function markStatus(Story $story, string $status, ?string $error = null): void
    {
        $story->forceFill([
            'cover_status' => $status,
            'cover_error' => $error ? mb_substr($error, 0, 1000) : null,
        ])->save();
    }

    private function run(Story $story, bool $force): string
    {
        $path = 'stories/'.$story->slug.'.jpg';

        if (! $force && $story->thumbnail && Storage::disk('public')->exists($story->thumbnail)) {
            return $story->thumbnail;
        }

        $apiKey = (string) config('services.openai.key');
        if ($apiKey === '') {
            throw new RuntimeException('Chưa cấu hình OPENAI_API_KEY.');
        }

        $jpeg = $this->renderCover($apiKey, $this->promptFor($story, $apiKey));

        Storage::disk('public')->put($path, $jpeg);

        // Ảnh cũ có thể nằm ở tên khác (đổi slug) -> dọn để không bỏ rác lại.
        if ($story->thumbnail && $story->thumbnail !== $path) {
            Storage::disk('public')->delete($story->thumbnail);
        }

        $story->forceFill(['thumbnail' => $path])->save();

        return $path;
    }

    /* ------------------------------------------------------------------ */
    /* PROMPT */
    /* ------------------------------------------------------------------ */

    /** Tông hình ảnh theo thể loại của truyện (tất định khi truyện có nhiều thể loại). */
    public function profileFor(Story $story): string
    {
        $slugs = $story->categories->pluck('slug')->all();

        foreach (self::PROFILE_PRIORITY as $slug) {
            if (in_array($slug, $slugs, true) && isset(self::PROFILES[$slug])) {
                return self::PROFILES[$slug];
            }
        }

        return self::FALLBACK_PROFILE;
    }

    /**
     * Prompt hoàn chỉnh gửi cho model ảnh: chỉ đạo cảnh lấy từ chính truyện,
     * cộng tông theo thể loại, cộng ràng buộc kỹ thuật.
     *
     * Tách riêng public để lệnh `stories:cover --dry` in ra xem trước mà không tốn tiền ảnh.
     */
    public function promptFor(Story $story, ?string $apiKey = null): string
    {
        $brief = $this->briefFor($story, $apiKey ?? (string) config('services.openai.key'));

        return implode("\n\n", [
            'Photograph the following scene from a contemporary drama, as the cover image of that story.',
            'SCENE (this is the story being illustrated — follow it faithfully):'."\n".$brief,
            'MOOD AND LIGHT:'."\n".$this->profileFor($story),
            self::CONSTRAINTS,
        ]);
    }

    /**
     * Bước 1: bắt model văn bản ĐỌC truyện rồi viết chỉ đạo chụp ảnh.
     *
     * Gửi kèm trích đoạn chương 1 chứ không chỉ mô tả, vì mô tả thường là lời quảng cáo
     * ("chàng lao công hoá ra là chủ tịch") còn chương 1 mới cho biết nhân vật bao nhiêu
     * tuổi, mặc gì, đứng ở đâu — đúng những thứ cần cho một bức ảnh.
     */
    private function briefFor(Story $story, string $apiKey): string
    {
        $chapter = $story->chapters()->orderBy('number')->first();
        $excerpt = $chapter ? mb_substr(trim(strip_tags((string) $chapter->content)), 0, self::EXCERPT_CHARS) : '';
        $genres = $story->categories->pluck('name')->implode(', ');

        $material = implode("\n", array_filter([
            'TITLE: '.$story->title,
            $story->author ? 'AUTHOR: '.$story->author : null,
            $genres !== '' ? 'GENRES: '.$genres : null,
            $story->description ? "SYNOPSIS:\n".trim((string) $story->description) : null,
            $excerpt !== '' ? "OPENING OF CHAPTER 1:\n".$excerpt : null,
        ]));

        $system = <<<'TXT'
        You are a photo editor picking the cover shot for a story that has to win a tap on a
        crowded phone screen. You will be given a story's title, genres, synopsis and the opening
        of its first chapter.

        Pick the single most CHARGED moment in that material — the instant of humiliation,
        recognition, defiance or reveal. Never a calm establishing shot, never a generic stock
        idea. Then describe it as one photograph.

        STAGE THE CONFRONTATION, DO NOT SHOOT A PORTRAIT. The frame must show the two sides of
        the conflict standing in the same room at the same moment — the wife in the doorway with the
        suitcase while the husband builds a crib for the other woman; the mother holding her child
        at the lost-children desk while the father poses for family photographs behind her. A single
        person alone against a background is a headshot, not a cover, and it tells the reader
        nothing about the story.

        The frame must carry three things at once:
        1. TWO TO FOUR people, all in one shot. The protagonist is nearest the camera and is the
           emotional centre; the others are the people the story is about — the spouse, the rival,
           the child. Every person must be doing something that belongs to the conflict.
        2. A REAL PLACE, described specifically, filled with the objects that carry the story: the
           half-painted nursery, the plastic tubs of baby clothes, the abandoned bench, the packed
           suitcase, the crutches. The room itself should tell a stranger what went wrong.
        3. One strong light source and one strong colour.

        Answer with 90-140 words of plain prose, no headings, no bullet points, no quotation marks.
        Cover, in this order: every person in the frame (approximate age, build, hair, ethnicity if
        the text indicates it), exactly what each wears and how worn it is, what each is doing and
        the expression on the protagonist's face, the location and the specific objects lying around
        it, the light source and its colour, and the camera framing (for example a medium-wide shot
        from the doorway at chest height).

        Rules: two to four people, with the protagonist clearly dominant in the frame. Describe only
        what a camera could see — no thoughts, no plot, no character names. NEVER describe any
        text, sign, logo, brand, poster, name plate or screen with writing; if the setting would
        normally have one, describe that surface as blank. Keep clothing and setting true to the
        character's situation at that moment: if the text says he is taken for a poor man, he must
        look genuinely poor.
        TXT;

        $response = Http::withToken($apiKey)
            ->timeout(120)
            ->retry(2, 2000, throw: false)
            ->post(self::CHAT_ENDPOINT, [
                'model' => (string) config('services.openai.cover_prompt_model'),
                'messages' => [
                    ['role' => 'system', 'content' => $system],
                    ['role' => 'user', 'content' => $material],
                ],
            ]);

        if ($response->failed()) {
            throw new RuntimeException('OpenAI chat lỗi '.$response->status().': '.mb_substr($response->body(), 0, 400));
        }

        $brief = trim((string) ($response->json('choices.0.message.content') ?? ''));

        if ($brief === '') {
            throw new RuntimeException('OpenAI không trả về chỉ đạo hình ảnh cho truyện '.$story->slug.'.');
        }

        return $brief;
    }

    /**
     * Vẽ và TRẢ THẲNG dữ liệu JPEG, không đụng disk cũng không đụng cơ sở dữ liệu.
     *
     * Dùng cho `stories:cover --out=<file>`: thử prompt mới trên truyện thật mà không
     * ghi đè tấm bìa đang phục vụ người dùng, nên so sánh A/B xong vẫn lui lại được.
     */
    public function renderBytes(Story $story): string
    {
        $apiKey = (string) config('services.openai.key');
        if ($apiKey === '') {
            throw new RuntimeException('Chưa cấu hình OPENAI_API_KEY.');
        }

        return $this->renderCover($apiKey, $this->promptFor($story, $apiKey));
    }

    /* ------------------------------------------------------------------ */
    /* ẢNH */
    /* ------------------------------------------------------------------ */

    /** Gọi model ảnh rồi cắt/thu về đúng 600x800 JPEG. */
    private function renderCover(string $apiKey, string $prompt): string
    {
        $response = Http::withToken($apiKey)
            ->timeout(300)
            ->retry(2, 5000, throw: false)
            ->post(self::IMAGE_ENDPOINT, [
                'model' => (string) config('services.openai.image_model'),
                'prompt' => $prompt,
                'n' => 1,
                // 1024x1536 là khổ dọc của gpt-image-1; cắt xuống 3:4 ở bước sau.
                'size' => (string) config('services.openai.image_size'),
                'quality' => (string) config('services.openai.image_quality'),
                'output_format' => 'jpeg',
            ]);

        if ($response->failed()) {
            throw new RuntimeException('OpenAI images lỗi '.$response->status().': '.mb_substr($response->body(), 0, 400));
        }

        $b64 = (string) ($response->json('data.0.b64_json') ?? '');
        if ($b64 === '') {
            throw new RuntimeException('OpenAI images không trả về dữ liệu ảnh.');
        }

        $binary = base64_decode($b64, true);
        if ($binary === false || $binary === '') {
            throw new RuntimeException('Dữ liệu ảnh base64 hỏng.');
        }

        return $this->toCover($binary);
    }

    /**
     * Cắt giữa (lệch lên trên) rồi thu về 600x800.
     *
     * Ảnh model trả về là 2:3, bìa cần 3:4 -> phải bỏ bớt CHIỀU CAO. Cắt lệch lên trên
     * (chỉ bỏ 30% phần thừa ở mép trên, 70% ở mép dưới) để giữ đầu và khoảng trống phía
     * trên — chỗ app đè tiêu đề lên.
     */
    private function toCover(string $binary): string
    {
        $src = @imagecreatefromstring($binary);
        if ($src === false) {
            throw new RuntimeException('Không đọc được ảnh trả về từ OpenAI.');
        }

        try {
            $srcW = imagesx($src);
            $srcH = imagesy($src);
            $ratio = self::OUT_WIDTH / self::OUT_HEIGHT;

            if ($srcW / $srcH > $ratio) {
                // quá rộng -> cắt bớt chiều ngang, giữ giữa
                $cropW = (int) round($srcH * $ratio);
                $cropH = $srcH;
                $x = (int) round(($srcW - $cropW) / 2);
                $y = 0;
            } else {
                // quá cao -> cắt bớt chiều cao, lệch lên trên
                $cropW = $srcW;
                $cropH = (int) round($srcW / $ratio);
                $x = 0;
                $y = (int) round(($srcH - $cropH) * 0.3);
            }

            $cropped = imagecrop($src, ['x' => $x, 'y' => $y, 'width' => $cropW, 'height' => $cropH]);
            if ($cropped === false) {
                throw new RuntimeException('Cắt ảnh thất bại.');
            }

            try {
                $out = imagescale($cropped, self::OUT_WIDTH, self::OUT_HEIGHT, IMG_BICUBIC_FIXED);
                if ($out === false) {
                    throw new RuntimeException('Thu nhỏ ảnh thất bại.');
                }

                try {
                    ob_start();
                    imagejpeg($out, null, 88);

                    return (string) ob_get_clean();
                } finally {
                    imagedestroy($out);
                }
            } finally {
                imagedestroy($cropped);
            }
        } finally {
            imagedestroy($src);
        }
    }
}
