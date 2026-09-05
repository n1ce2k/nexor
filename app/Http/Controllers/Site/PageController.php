<?php

namespace App\Http\Controllers\Site;

use App\Http\Controllers\Controller;
use Illuminate\View\View;
use Nexor\Cms\Support\Site;

class PageController extends Controller
{
    public function show(string $code): View
    {
        $page = Site::element(Site::PAGES, $code);

        abort_if($page === null, 404);

        $page->increment('views');

        return view('site.page', compact('page'));
    }
}
