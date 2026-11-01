<?php

declare(strict_types=1);

namespace Tests\Feature;

use App\Models\Chapter;
use App\Models\PushDevice;
use App\Models\Story;
use App\Models\StoryView;
use App\Services\DailyStoryNotifier;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Http\Client\Request;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\Http;
use Tests\TestCase;

class DailyStoryPushTest extends TestCase
{
    use RefreshDatabase;

    private const TOKEN = 'ExponentPushToken[abcdefghijklmnop]';

    protected function tearDown(): void
    {
        Carbon::setTestNow();
        parent::tearDown();
    }

    private function story(string $slug, Carbon $createdAt, int $hits = 0, bool $withChapter = true): Story
    {
        $s = Story::create(['title' => "T $slug", 'slug' => $slug, 'status' => 'ongoing']);
        $s->forceFill(['created_at' => $createdAt])->save();
        if ($withChapter) {
            Chapter::create(['story_id' => $s->id, 'number' => 1, 'title' => 'One', 'content' => 'Lorem.']);
        }
        if ($hits > 0) {
            StoryView::create([
                'story_id' => $s->id, 'chapter_id' => 0, 'source' => 'web', 'device' => 'mobile',
                'visitor_hash' => str_repeat('a', 64), 'viewed_on' => $createdAt->toDateString(), 'hits' => $hits,
            ]);
        }

        return $s;
    }

    private function fakeExpo(array $tickets = []): void
    {
        Http::fake(['exp.host/*' => Http::response(['data' => $tickets ?: [['status' => 'ok', 'id' => 'x']]])]);
    }

    public function test_dang_ky_va_huy_token(): void
    {
        $this->postJson('/api/push-devices', ['token' => self::TOKEN, 'platform' => 'ios', 'timezone' => 'Asia/Ho_Chi_Minh'])
            ->assertOk();
        $this->assertSame('Asia/Ho_Chi_Minh', PushDevice::query()->sole()->timezone);

        // Đăng ký lại cùng token (mỗi lần mở app) chỉ cập nhật, không thêm dòng.
        $this->postJson('/api/push-devices', ['token' => self::TOKEN, 'platform' => 'ios', 'timezone' => 'Not/AZone'])
            ->assertOk();
        $this->assertSame('America/New_York', PushDevice::query()->sole()->timezone);

        $this->postJson('/api/push-devices', ['token' => 'rac', 'platform' => 'ios'])->assertStatus(422);

        $this->deleteJson('/api/push-devices', ['token' => self::TOKEN])->assertNoContent();
        $this->assertSame(0, PushDevice::query()->count());
    }

    public function test_chon_truyen_moi_nhieu_luot_xem_nhat_va_moi_ngay_mot_lan(): void
    {
        // 23:30 UTC = 19:30 ở New York (giờ mùa hè), 06:30 sáng ở Việt Nam.
        $now = Carbon::parse('2026-10-20 23:30:00', 'UTC');
        Carbon::setTestNow($now);

        $this->story('cu', $now->copy()->subDays(2), 999);
        $this->story('chua-co-chuong', $now->copy()->subHours(2), 500, false);
        $this->story('it-xem', $now->copy()->subHours(3), 5);
        $hot = $this->story('nhieu-xem', $now->copy()->subHours(20), 40);

        $ny = PushDevice::create(['token' => self::TOKEN, 'platform' => 'ios', 'timezone' => 'America/New_York']);
        PushDevice::create(['token' => 'ExponentPushToken[vietnamvietnam]', 'platform' => 'ios', 'timezone' => 'Asia/Ho_Chi_Minh']);

        $this->fakeExpo();
        $stats = app(DailyStoryNotifier::class)->run($now);

        $this->assertSame($hot->id, $stats['story']);
        $this->assertSame(1, $stats['sent'], 'chỉ máy ở New York đang tới giờ tối');
        Http::assertSent(fn (Request $r) => $r[0]['to'] === self::TOKEN
            && $r[0]['data']['storyId'] === $hot->id
            && count($r->data()) === 1);
        $this->assertSame('2026-10-20', $ny->fresh()->last_notified_on->toDateString());

        // Một giờ sau vẫn trong khung tối nhưng hôm nay đã gửi rồi.
        Carbon::setTestNow($now->copy()->addHour());
        $this->assertSame(0, app(DailyStoryNotifier::class)->run(Carbon::now())['due']);
    }

    public function test_khong_co_truyen_moi_thi_khong_gui(): void
    {
        $now = Carbon::parse('2026-10-20 23:30:00', 'UTC');
        Carbon::setTestNow($now);
        $this->story('cu', $now->copy()->subDays(3), 50);
        PushDevice::create(['token' => self::TOKEN, 'platform' => 'ios', 'timezone' => 'America/New_York']);

        Http::fake();
        $stats = app(DailyStoryNotifier::class)->run($now);

        $this->assertNull($stats['story']);
        Http::assertNothingSent();
    }

    public function test_token_chet_bi_xoa_va_loi_mang_thi_gio_sau_gui_bu(): void
    {
        $now = Carbon::parse('2026-10-20 23:05:00', 'UTC');
        Carbon::setTestNow($now);
        $this->story('moi', $now->copy()->subHours(1));
        PushDevice::create(['token' => self::TOKEN, 'platform' => 'ios', 'timezone' => 'America/New_York']);
        $dead = PushDevice::create(['token' => 'ExponentPushToken[deaddeaddead]', 'platform' => 'ios', 'timezone' => 'America/New_York']);

        // Lần đầu Expo sập, lần sau trả vé: một máy ok, một máy đã gỡ app.
        Http::fake(['exp.host/*' => Http::sequence()
            ->push('down', 503)
            ->push(['data' => [
                ['status' => 'ok', 'id' => 'a'],
                ['status' => 'error', 'message' => 'gone', 'details' => ['error' => 'DeviceNotRegistered']],
            ]]),
        ]);
        $this->assertSame(2, app(DailyStoryNotifier::class)->run($now)['failed']);
        $this->assertSame(2, PushDevice::query()->whereNull('last_notified_on')->count());

        Carbon::setTestNow($now->copy()->addHour());
        $stats = app(DailyStoryNotifier::class)->run(Carbon::now());

        $this->assertSame(1, $stats['sent']);
        $this->assertSame(1, $stats['removed']);
        $this->assertNull(PushDevice::query()->find($dead->id));
    }
}
