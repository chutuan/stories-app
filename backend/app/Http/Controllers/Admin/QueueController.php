<?php

declare(strict_types=1);

namespace App\Http\Controllers\Admin;

use App\Console\Commands\ReleaseQueuedStories;
use App\Http\Controllers\Controller;
use App\Models\QueuedStory;
use Illuminate\Http\RedirectResponse;
use Illuminate\Support\Facades\Storage;
use Illuminate\View\View;

/** Kho truyện chờ đăng: xem hàng chờ, bỏ bớt truyện không muốn đăng. */
class QueueController extends Controller
{
    public function index(): View
    {
        $pending = QueuedStory::query()->where('status', 'pending')->count();
        // Không kéo cột payload (cả nội dung truyện) chỉ để hiện danh sách.
        $columns = ['id', 'title', 'position', 'noindex', 'source', 'status', 'story_id', 'released_at', 'error', 'updated_at'];

        return view('admin.queue.index', [
            'pendingCount' => $pending,
            'daily' => ReleaseQueuedStories::DAILY,
            'next' => QueuedStory::query()->pending()->limit(50)->get($columns),
            'released' => QueuedStory::query()->with('story:id,title,slug')->where('status', 'released')
                ->latest('released_at')->limit(20)->get($columns),
            'failed' => QueuedStory::query()->where('status', 'failed')->latest('updated_at')->get($columns),
        ]);
    }

    /** Bỏ một truyện khỏi kho: không bao giờ được đăng, kể cả khi xếp hàng lại cả lô. */
    public function destroy(QueuedStory $item): RedirectResponse
    {
        abort_unless(in_array($item->status, ['pending', 'failed'], true), 422);

        if ($item->cover_path) {
            Storage::disk('local')->delete($item->cover_path);
        }
        $item->forceFill(['status' => 'skipped', 'cover_path' => null])->save();

        return redirect()->route('admin.queue.index')->with('status', "Đã bỏ “{$item->title}” khỏi kho.");
    }
}
