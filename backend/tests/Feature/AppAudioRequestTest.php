<?php

declare(strict_types=1);

namespace Tests\Feature;

use App\Jobs\GenerateChapterAudio;
use App\Models\Chapter;
use App\Models\ChapterAudioRequest;
use App\Models\Story;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Queue;
use Tests\TestCase;

/** Lượt bấm nghe audio trong app + trang admin/audio (Tuấn yêu cầu 10/11/2026). */
class AppAudioRequestTest extends TestCase
{
    use RefreshDatabase;

    private const READER = 'r_abcdefghijklmnop1234';

    private function story(): Story
    {
        $story = Story::create(['title' => 'Audio Story', 'slug' => 'audio-story', 'description' => 'x', 'status' => 'ongoing']);
        Chapter::create(['story_id' => $story->id, 'number' => 1, 'title' => 'One', 'content' => 'Hello.']);
        Chapter::create(['story_id' => $story->id, 'number' => 2, 'title' => 'Two', 'content' => 'Hello again.']);

        return $story;
    }

    private function send(Story $story, int $number, string $event, string $reader = self::READER, ?string $platform = 'ios')
    {
        return $this->postJson("/api/stories/{$story->id}/chapters/{$number}/audio-request", array_filter([
            'event' => $event, 'reader_id' => $reader, 'platform' => $platform,
        ]));
    }

    public function test_moi_may_mot_dong_moi_chuong_va_cong_don_luot_bam(): void
    {
        $story = $this->story();

        $this->send($story, 1, 'click')->assertNoContent();
        $this->send($story, 1, 'click')->assertNoContent();
        $this->send($story, 1, 'unlock')->assertNoContent();
        $this->send($story, 1, 'click', 'reader_two_0000000000', 'android')->assertNoContent();

        $this->assertSame(2, ChapterAudioRequest::count());
        $mine = ChapterAudioRequest::query()->where('platform', 'ios')->firstOrFail();
        $this->assertSame([2, 1], [$mine->clicks, $mine->unlocks]);
        // Không lưu mã gốc của máy.
        $this->assertNotSame(self::READER, $mine->reader_hash);
        $this->assertSame(64, strlen($mine->reader_hash));
    }

    public function test_tu_choi_du_lieu_sai_va_chuong_khong_ton_tai(): void
    {
        $story = $this->story();

        $this->send($story, 1, 'play')->assertUnprocessable();
        $this->send($story, 1, 'click', 'short')->assertUnprocessable();
        $this->send($story, 1, 'click', self::READER, 'windows')->assertUnprocessable();
        $this->send($story, 9, 'click')->assertNotFound();

        $this->assertSame(0, ChapterAudioRequest::count());
    }

    public function test_trang_admin_xep_theo_so_nguoi_va_co_nut_tao_audio(): void
    {
        $story = $this->story();
        // Chương 2: ba người; chương 1: một người bấm năm lần.
        foreach (['reader_aaaa_000000000', 'reader_bbbb_000000000', 'reader_cccc_000000000'] as $r) {
            $this->send($story, 2, 'click', $r);
        }
        foreach (range(1, 5) as $_) {
            $this->send($story, 1, 'click');
        }
        $story->chapters()->where('number', 1)->update(['audio_path' => 'audio/1/1.mp3']);

        $res = $this->actingAs(User::factory()->create())->get('/admin/audio');

        $res->assertOk();
        $res->assertSeeInOrder(['Chương 2', 'Chương 1']);
        $res->assertSee('Tạo audio');
        $res->assertSee('Có audio');

        // Lọc "chưa có audio" chỉ còn chương 2.
        $this->get('/admin/audio?audio=missing')->assertOk()
            ->assertSee('Chương 2')->assertDontSee('Chương 1 ');
    }

    public function test_nut_tao_audio_dua_dung_chuong_vao_hang_doi(): void
    {
        Queue::fake();
        config(['services.openai.key' => 'test-key']);
        $story = $this->story();
        $chapter = $story->chapters()->where('number', 2)->firstOrFail();
        $this->send($story, 2, 'click');

        $this->actingAs(User::factory()->create())
            ->from('/admin/audio')
            ->post("/admin/stories/{$story->id}/chapters/{$chapter->id}/audio/generate")
            ->assertRedirect('/admin/audio');

        Queue::assertPushed(GenerateChapterAudio::class, 1);
        $this->assertSame('queued', $chapter->fresh()->audio_status);
        $this->get('/admin/audio')->assertSee('Đang chờ');
    }
}
