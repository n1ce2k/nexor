<?php

namespace App\Http\Controllers\Site;

use App\Http\Controllers\Controller;
use Illuminate\View\View;
use Nexor\Cms\Models\Iblock;
use Nexor\Cms\Models\IblockSection;
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
     * Everything below an infoblock, read like a folder path.
     *
     * Segments are matched against sections one level at a time; the first
     * segment that is not a section has to be an element. That keeps
     * /katalog/mebel a section and /katalog/mebel/stul the element inside it,
     * at any nesting depth, without the two ever colliding.
     */
    public function inside(string $code, string $path): View
    {
        $iblock = $this->pagedIblock($code);

        abort_if($iblock === null, 404);

        $segments = array_values(array_filter(explode('/', $path), 'strlen'));
        $section = null;

        foreach ($segments as $index => $segment) {
            $next = $this->section($iblock, $segment, $section);

            if ($next) {
                $section = $next;

                continue;
            }

            // Не раздел — значит элемент, и он обязан быть последним сегментом.
            abort_unless($index === count($segments) - 1, 404);

            return $this->element($iblock, $segment, $section);
        }

        return $this->sectionPage($iblock, $section);
    }

    /**
     * Детальная страница элемента.
     */
    protected function element(Iblock $iblock, string $code, ?IblockSection $section): View
    {
        $view = PageGenerator::view($iblock, 'detail');

        abort_unless(view()->exists($view), 404);

        $element = Site::element($iblock->code, $code);

        abort_if($element === null, 404);

        // Элемент, лежащий в другом разделе, по этому адресу не открывается:
        // иначе одна и та же страница была бы доступна по множеству адресов.
        abort_if($section && $element->section_id !== $section->id, 404);

        $element->increment('views');

        return view($view, ['element' => $element, 'iblock' => $iblock, 'section' => $section]);
    }

    /**
     * Страница раздела.
     */
    protected function sectionPage(Iblock $iblock, ?IblockSection $section): View
    {
        $view = PageGenerator::view($iblock, 'section');

        // Без своего шаблона раздел показывает список инфоблока.
        if (! view()->exists($view)) {
            $view = PageGenerator::view($iblock);
        }

        abort_unless(view()->exists($view), 404);

        return view($view, ['iblock' => $iblock, 'section' => $section]);
    }

    /**
     * Активный раздел с таким кодом внутри указанного родителя.
     */
    protected function section(Iblock $iblock, string $code, ?IblockSection $parent): ?IblockSection
    {
        if (! $iblock->has_sections) {
            return null;
        }

        return IblockSection::query()
            ->where('iblock_id', $iblock->id)
            ->where('parent_id', $parent?->id)
            ->where('code', $code)
            ->active()
            ->first();
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
