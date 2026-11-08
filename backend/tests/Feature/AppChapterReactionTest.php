<?php

declare(strict_types=1);

namespace Tests\Feature;

use App\Models\Chapter;
use App\Models\ChapterReaction;
use App\Models\Story;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

/** Năm khuôn mặt dưới mỗi chương trong app: cùng bảng phiếu với web. */
class AppChapterReactionTest extends TestCase
{
    use RefreshDatabase;

    private const READER = 'r_abcdefghijklmnop1234';

    private function chapter(): Chapter
    {
        $story = Story::create(['title' => 'Test', 'slug' => 'test', 'description' => 'x', 'status' => 'completed']);

        return Chapter::create(['story_id' => $story->id, 'number' => 1, 'title' => 'One', 'content' => 'Hello.']);
    }

    private function vote(Chapter $chapter, int $score, string $reader = self::READER)
    {
        return $this->postJson("/api/stories/{$chapter->story_id}/chapters/{$chapter->number}/reaction", [
            'score' => $score, 'reader_id' => $reader,
        ]);
    }

    public function test_bam_lan_dau_ghi_phieu_bam_lai_thi_doi_phieu_khong_nhan_doi(): void
    {
        $chapter = $this->chapter();

        $this->vote($chapter, 4)->assertOk()->assertExactJson(['count' => 1, 'average' => null, 'mine' => 4]);
        $this->vote($chapter, 5)->assertOk()->assertJson(['count' => 1, 'mine' => 5]);

        $this->assertSame(1, ChapterReaction::count());
        $this->assertSame(5, ChapterReaction::first()->score);
        // Không lưu mã gốc của máy.
        $this->assertNotSame(self::READER, ChapterReaction::first()->visitor_hash);
    }

    public function test_trung_binh_chi_hien_tu_ba_phieu_va_nam_trong_api_chuong(): void
    {
        $chapter = $this->chapter();
        $this->vote($chapter, 5, 'reader_one_0000000000');
        $this->vote($chapter, 4, 'reader_two_0000000000');

        $this->getJson("/api/stories/{$chapter->story_id}/chapters/1")->assertOk()
            ->assertJsonPath('reaction', ['count' => 2, 'average' => null]);

        $this->vote($chapter, 3, 'reader_three_00000000')->assertJson(['count' => 3, 'average' => 4]);
        $this->getJson("/api/stories/{$chapter->story_id}/chapters/1")
            ->assertJsonPath('reaction.average', 4);
    }

    public function test_tu_choi_diem_ngoai_thang_ma_may_sai_va_chuong_khong_ton_tai(): void
    {
        $chapter = $this->chapter();

        $this->vote($chapter, 6)->assertStatus(422);
        $this->vote($chapter, 0)->assertStatus(422);
        $this->vote($chapter, 3, 'short')->assertStatus(422);
        $this->vote($chapter, 3, 'has spaces and !!! symbols')->assertStatus(422);
        $this->postJson("/api/stories/{$chapter->story_id}/chapters/9/reaction", ['score' => 3, 'reader_id' => self::READER])
            ->assertNotFound();

        $this->assertSame(0, ChapterReaction::count());
    }
}
