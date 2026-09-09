<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Models\Chapter;
use App\Services\ChapterAudioGenerator;
use App\Models\Story;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Storage;
use Illuminate\View\View;

class ChapterController extends Controller
{
    public function index(Story $story): View
    {
        $chapters = $story->chapters()->paginate(30);

        return view('admin.chapters.index', compact('story', 'chapters'));
    }

    public function create(Story $story): View
    {
        $nextNumber = ((int) $story->chapters()->max('number')) + 1;

        return view('admin.chapters.create', [
            'story' => $story,
            'chapter' => new Chapter(['number' => $nextNumber]),
        ]);
    }

    public function store(Request $request, Story $story): RedirectResponse
    {
        $data = $this->validateData($request, $story);

        $chapter = $story->chapters()->create($data);
        $this->storeAudio($request, $chapter);

        return redirect()->route('admin.stories.chapters.index', $story)
            ->with('status', 'Đã thêm chương.');
    }

    public function edit(Story $story, Chapter $chapter): RedirectResponse|View
    {
        abort_if($chapter->story_id !== $story->id, 404);

        return view('admin.chapters.edit', compact('story', 'chapter'));
    }

    public function update(Request $request, Story $story, Chapter $chapter): RedirectResponse
    {
        abort_if($chapter->story_id !== $story->id, 404);

        $data = $this->validateData($request, $story, $chapter->id);
        $chapter->update($data);
        $this->storeAudio($request, $chapter);

        return redirect()->route('admin.stories.chapters.index', $story)
            ->with('status', 'Đã cập nhật chương.');
    }

    public function destroy(Story $story, Chapter $chapter): RedirectResponse
    {
        abort_if($chapter->story_id !== $story->id, 404);

        $this->deleteAudioFile($chapter);
        $chapter->delete();

        return redirect()->route('admin.stories.chapters.index', $story)
            ->with('status', 'Đã xóa chương.');
    }

    /**
     * Xóa RIÊNG file audio của chương (giữ nguyên nội dung chương).
     */
    public function destroyAudio(Story $story, Chapter $chapter): RedirectResponse
    {
        abort_if($chapter->story_id !== $story->id, 404);

        $this->deleteAudioFile($chapter);
        $chapter->forceFill(['audio_path' => null])->save();

        return redirect()->route('admin.stories.chapters.edit', [$story, $chapter])
            ->with('status', 'Đã xóa audio của chương.');
    }

    protected function validateData(Request $request, Story $story, ?int $ignoreId = null): array
    {
        $validated = $request->validate([
            'number' => [
                'required', 'integer', 'min:1',
                'unique:chapters,number,'.($ignoreId ?? 'NULL').',id,story_id,'.$story->id,
            ],
            'title' => ['required', 'string', 'max:255'],
            'content' => ['required', 'string'],
            'audio' => ['nullable', 'file', 'mimes:mp3,mpga,m4a,aac,wav,ogg', 'max:102400'],
        ], [
            'number.unique' => 'Số chương này đã tồn tại trong truyện.',
            'audio.mimes' => 'File audio phải là MP3 (hoặc m4a/aac/wav/ogg).',
            'audio.max' => 'File audio tối đa 100MB.',
        ]);

        // `audio` là file, không phải cột trên bảng chapters.
        unset($validated['audio']);

        return $validated;
    }

    /**
     * Lưu file MP3 vào storage/app/public/audio và thay file cũ nếu có.
     */
    protected function storeAudio(Request $request, Chapter $chapter): void
    {
        if (! $request->hasFile('audio')) {
            return;
        }

        $this->deleteAudioFile($chapter);

        $path = $request->file('audio')->store('audio', 'public');
        $chapter->forceFill(['audio_path' => $path])->save();
    }

    protected function deleteAudioFile(Chapter $chapter): void
    {
        if ($chapter->audio_path) {
            Storage::disk('public')->delete($chapter->audio_path);
        }
    }

    /** Sinh giọng đọc AI cho chương bằng OpenAI TTS. */
    public function generateAudio(Story $story, Chapter $chapter, ChapterAudioGenerator $generator)
    {
        if ((string) config('services.openai.key') === '') {
            return back()->with('error', 'Chưa cấu hình OPENAI_API_KEY trong backend/.env');
        }

        try {
            $generator->generate($chapter, force: true);
            $voice = $generator->profileFor($chapter)['voice'];

            return back()->with('status', "Đã tạo giọng đọc cho chương {$chapter->number} (giọng {$voice}).");
        } catch (\Throwable $e) {
            return back()->with('error', 'Tạo audio thất bại: '.$e->getMessage());
        }
    }
}
