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
        if ($iblock = $this->pagedIblock($code)) {
            return view(PageGenerator::view($iblock));
        }

        $page = Site::element(Site::PAGES, $code);

        abort_if($page === null, 404);

        $page->increment('views');

        return view('site.page', compact('page'));
    }

    /**
     * Detail page of one element, at /<код инфоблока>/<код элемента>.
     */
    public function element(string $code, string $element): View
    {
        $iblock = $this->pagedIblock($code);

        abort_if($iblock === null, 404);

        $view = PageGenerator::view($iblock, 'detail');

        abort_unless(view()->exists($view), 404);

        $model = Site::element($code, $element);

        abort_if($model === null, 404);

        $model->increment('views');

        return view($view, ['element' => $model, 'iblock' => $iblock]);
    }

    /**
     * An active page-backed infoblock whose listing file exists on disk.
     */
    protected function pagedIblock(string $code): ?Iblock
    {
        $iblock = Iblock::query()
            ->active()
            ->where('has_page', true)
            ->where('code', $code)
            ->first();

        return $iblock && PageGenerator::exists($iblock) ? $iblock : null;
    }
}
