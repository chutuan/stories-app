<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Models\Category;
use App\Models\Chapter;
use App\Models\Story;
use Illuminate\View\View;

class DashboardController extends Controller
{
    public function index(): View
    {
        return view('admin.dashboard', [
            'storiesCount' => Story::count(),
            'chaptersCount' => Chapter::count(),
            'categoriesCount' => Category::count(),
            'recentStories' => Story::withCount('chapters')->latest('updated_at')->limit(8)->get(),
        ]);
    }
}
