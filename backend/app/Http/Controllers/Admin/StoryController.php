<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Models\Category;
use App\Models\Story;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Str;
use Illuminate\View\View;

class StoryController extends Controller
{
    public function index(Request $request): View
    {
        $stories = Story::with('categories')
            ->withCount(['chapters', 'reactions'])
            ->withAvg('reactions', 'score')
            ->when($request->query('search'), function ($q, $search) {
                $q->where('title', 'like', "%{$search}%")
                    ->orWhere('author', 'like', "%{$search}%");
            })
            ->latest('updated_at')
            ->paginate(15)
            ->withQueryString();

        return view('admin.stories.index', compact('stories'));
    }

    public function create(): View
    {
        return view('admin.stories.create', [
            'story' => new Story(['status' => 'ongoing', 'free_chapters' => 1]),
            'categories' => Category::orderBy('name')->get(),
            'selectedCategories' => [],
        ]);
    }

    public function store(Request $request): RedirectResponse
    {
        $data = $this->validateData($request);

        $story = new Story;
        $this->fill($story, $data);
        $story->slug = $this->uniqueSlug($data['title']);

        if ($request->hasFile('thumbnail')) {
            $story->thumbnail = $request->file('thumbnail')->store('stories', 'public');
        }

        $story->save();
        $story->categories()->sync($data['categories'] ?? []);

        return redirect()->route('admin.stories.index')
            ->with('status', 'Đã thêm truyện.');
    }

    public function edit(Story $story): View
    {
        return view('admin.stories.edit', [
            'story' => $story,
            'categories' => Category::orderBy('name')->get(),
            'selectedCategories' => $story->categories()->pluck('categories.id')->all(),
        ]);
    }

    public function update(Request $request, Story $story): RedirectResponse
    {
        $data = $this->validateData($request);

        $this->fill($story, $data);
        $story->slug = $this->uniqueSlug($data['title'], $story->id);

        if ($request->hasFile('thumbnail')) {
            if ($story->thumbnail) {
                Storage::disk('public')->delete($story->thumbnail);
            }
            $story->thumbnail = $request->file('thumbnail')->store('stories', 'public');
        }

        $story->save();
        $story->categories()->sync($data['categories'] ?? []);

        return redirect()->route('admin.stories.index')
            ->with('status', 'Đã cập nhật truyện.');
    }

    public function destroy(Story $story): RedirectResponse
    {
        if ($story->thumbnail) {
            Storage::disk('public')->delete($story->thumbnail);
        }

        // DB cascade sẽ xóa chapters, nhưng file audio trên disk thì không -> dọn trước.
        $audioPaths = $story->chapters()->whereNotNull('audio_path')->pluck('audio_path')->all();
        if ($audioPaths) {
            Storage::disk('public')->delete($audioPaths);
        }

        $story->delete();

        return redirect()->route('admin.stories.index')
            ->with('status', 'Đã xóa truyện.');
    }

    protected function validateData(Request $request): array
    {
        return $request->validate([
            'title' => ['required', 'string', 'max:255'],
            'author' => ['nullable', 'string', 'max:255'],
            'description' => ['nullable', 'string'],
            'status' => ['required', 'in:ongoing,completed'],
            'free_chapters' => ['required', 'integer', 'min:0'],
            'is_featured' => ['nullable', 'boolean'],
            'categories' => ['nullable', 'array'],
            'categories.*' => ['integer', 'exists:categories,id'],
            'thumbnail' => ['nullable', 'image', 'max:4096'],
        ]);
    }

    protected function fill(Story $story, array $data): void
    {
        $story->title = $data['title'];
        $story->author = $data['author'] ?? null;
        $story->description = $data['description'] ?? null;
        $story->status = $data['status'];
        $story->free_chapters = $data['free_chapters'];
        $story->is_featured = (bool) ($data['is_featured'] ?? false);
    }

    protected function uniqueSlug(string $title, ?int $ignoreId = null): string
    {
        $base = Str::slug($title);
        if ($base === '') {
            $base = 'truyen';
        }
        $slug = $base;
        $i = 2;

        while (Story::where('slug', $slug)
            ->when($ignoreId, fn ($q) => $q->where('id', '!=', $ignoreId))
            ->exists()) {
            $slug = $base.'-'.$i++;
        }

        return $slug;
    }
}
