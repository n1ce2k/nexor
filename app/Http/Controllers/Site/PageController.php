<?php

namespace App\Http\Controllers\Site;

use App\Http\Controllers\Controller;
use Illuminate\View\View;
use Nexor\Cms\Models\Iblock;
use Nexor\Cms\Support\PageGenerator;
use Nexor\Cms\Support\Site;

class PageController extends Controller
{
    /**
     * Serves either an infoblock's own Blade page or an element of the
     * "Страницы" infoblock, whichever the code matches.
     *
     * An infoblock created with the "создать страницу" switch owns a folder
     * under resources/views, and that folder wins — the way it works in Bitrix.
     */
    public function show(string $code): View
    {
        if ($view = $this->iblockPage($code)) {
            return view($view);
        }

        $page = Site::element(Site::PAGES, $code);

        abort_if($page === null, 404);

        $page->increment('views');

        return view('site.page', compact('page'));
    }

    /**
     * Dotted view name of an active page-backed infoblock, when its file exists.
     */
    protected function iblockPage(string $code): ?string
    {
        $iblock = Iblock::query()
            ->active()
            ->where('has_page', true)
            ->where('code', $code)
            ->first();

        if (! $iblock || ! PageGenerator::exists($iblock)) {
            return null;
        }

        return str_replace('/', '.', PageGenerator::directory($iblock)).'.index';
    }
}
