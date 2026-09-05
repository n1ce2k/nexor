<?php

namespace App\Http\Controllers\Site;

use App\Http\Controllers\Controller;
use Illuminate\View\View;
use Nexor\Cms\Models\Iblock;
use Nexor\Cms\Support\Site;

class HomeController extends Controller
{
    public function index(): View
    {
        return view('site.home', [
            'pages' => Site::elements(Site::PAGES, 6),
            'iblocks' => Iblock::query()->active()->withCount('elements')->ordered()->get(),
        ]);
    }
}
