<?php

declare(strict_types=1);

namespace App\Services;

use App\Models\PushDevice;
use App\Models\Story;
use App\Models\StoryView;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Str;

/**
 * Thông báo "truyện mới hôm nay" cho app di động — mỗi máy tối đa MỘT lần mỗi ngày.
 *
 * Chạy mỗi giờ (routes/console.php). Mỗi lượt chỉ gửi cho những máy mà giờ địa phương
 * đang trong khung tối [daily_hour, daily_hour + WINDOW_HOURS) và hôm nay chưa nhận gì.
 * Khung vài giờ thay vì đúng một giờ: Expo lỗi lúc 19:00 thì 20:00 gửi bù, và máy bật
 * thông báo lúc 19:30 vẫn nhận ngay tối đó.
 *
 * Truyện được chọn: truyện lên kệ trong 24 giờ qua (có ít nhất một chương), nhiều lượt
 * xem nhất (bảng story_views — gồm cả app lẫn web, đã lọc bot). Không có truyện mới thì
 * không gửi gì: thông báo kiểu "hôm nay không có gì" chỉ dạy người ta tắt thông báo.
 */
class DailyStoryNotifier
{
    public const WINDOW_HOURS = 3;

    private const EXPO_URL = 'https://exp.host/--/api/v2/push/send';

    /** Expo nhận tối đa 100 thông báo mỗi request. */
    private const BATCH = 100;

    public function pickStory(Carbon $now): ?Story
    {
        $hits = StoryView::query()
            ->selectRaw('coalesce(sum(hits), 0)')
            ->whereColumn('story_views.story_id', 'stories.id');

        return Story::query()
            ->select('stories.*')
            ->selectSub($hits, 'recent_hits')
            ->where('created_at', '>=', $now->copy()->subDay())
            ->where('created_at', '<=', $now)
            ->whereHas('chapters')
            ->orderByDesc('recent_hits')
            ->orderByDesc('views')
            ->orderByDesc('created_at')
            ->first();
    }

    /**
     * @return array{story: ?int, due: int, sent: int, removed: int, failed: int}
     */
    public function run(Carbon $now, bool $dryRun = false): array
    {
        $stats = ['story' => null, 'due' => 0, 'sent' => 0, 'removed' => 0, 'failed' => 0];

        $due = $this->dueDevices($now);
        $stats['due'] = count($due);
        if ($due === []) {
            return $stats;
        }

        $story = $this->pickStory($now);
        if (! $story) {
            return $stats;
        }
        $stats['story'] = $story->id;

        // Máy đã nhận đúng truyện này (vd. vừa đổi múi giờ) thì thôi, không báo hai lần.
        $due = array_values(array_filter($due, fn (array $d) => $d['device']->last_story_id !== $story->id));

        if ($dryRun) {
            return $stats;
        }

        foreach (array_chunk($due, self::BATCH) as $batch) {
            $tickets = $this->send($batch, $story);
            if ($tickets === null) {
                $stats['failed'] += count($batch);

                continue;
            }

            foreach ($batch as $i => ['device' => $device, 'localDate' => $localDate]) {
                $ticket = $tickets[$i] ?? null;
                if (($ticket['status'] ?? null) === 'ok') {
                    $device->update(['last_notified_on' => $localDate, 'last_story_id' => $story->id]);
                    $stats['sent']++;
                } elseif (($ticket['details']['error'] ?? null) === 'DeviceNotRegistered') {
                    // Gỡ app hoặc tắt thông báo trong Cài đặt của máy: token chết hẳn.
                    $device->delete();
                    $stats['removed']++;
                } else {
                    $stats['failed']++;
                }
            }
        }

        return $stats;
    }

    /**
     * Máy đang trong khung giờ tối và hôm nay (theo giờ của máy) chưa nhận thông báo.
     *
     * @return list<array{device: PushDevice, localDate: string}>
     */
    private function dueDevices(Carbon $now): array
    {
        $start = (int) config('services.expo.daily_hour', 19);
        $due = [];

        foreach (PushDevice::query()->distinct()->pluck('timezone') as $timezone) {
            $local = $now->copy()->setTimezone($timezone);
            if ($local->hour < $start || $local->hour >= $start + self::WINDOW_HOURS) {
                continue;
            }
            $localDate = $local->toDateString();

            $devices = PushDevice::query()
                ->where('timezone', $timezone)
                ->where(fn ($q) => $q->whereNull('last_notified_on')->orWhere('last_notified_on', '<', $localDate))
                ->get();

            foreach ($devices as $device) {
                $due[] = ['device' => $device, 'localDate' => $localDate];
            }
        }

        return $due;
    }

    /**
     * @param  list<array{device: PushDevice, localDate: string}>  $batch
     * @return list<array<string, mixed>>|null  vé của Expo theo đúng thứ tự gửi; null nếu request hỏng
     */
    private function send(array $batch, Story $story): ?array
    {
        $messages = array_map(fn (array $d) => [
            'to' => $d['device']->token,
            'title' => 'New story today',
            'body' => Str::limit($story->title, 140),
            'sound' => 'default',
            // App đọc storyId để mở thẳng trang truyện (mobile/src/store/notifications.tsx).
            'data' => ['storyId' => $story->id],
        ], $batch);

        $request = Http::acceptJson()->timeout(20);
        if ($token = config('services.expo.access_token')) {
            $request = $request->withToken($token);
        }

        try {
            $response = $request->post(self::EXPO_URL, $messages);
        } catch (\Throwable $e) {
            Log::warning('Expo push: request lỗi', ['error' => $e->getMessage()]);

            return null;
        }

        $tickets = $response->json('data');
        if (! $response->successful() || ! is_array($tickets)) {
            Log::warning('Expo push: phản hồi lỗi', ['status' => $response->status(), 'body' => Str::limit($response->body(), 500)]);

            return null;
        }

        return array_values($tickets);
    }
}
