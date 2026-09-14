<?php

namespace Nexor\Cms\Http\Controllers\Site;

use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Routing\Controller;
use Illuminate\View\View;
use Nexor\Cms\Models\Iblock;
use Nexor\Cms\Models\IblockElement;
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
     * at any nesting depth, without the two ever colliding. One more segment
     * after the element is its trade offer: /katalog/mebel/stul/krasnyy.
     */
    public function inside(Request $request, string $code, string $path): View|RedirectResponse
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

            // Не раздел — значит элемент. После него может стоять только код предложения.
            $rest = array_slice($segments, $index + 1);

            abort_if(count($rest) > 1, 404);

            return $this->element($request, $iblock, $segment, $section, $rest[0] ?? null);
        }

        return $this->sectionPage($iblock, $section);
    }

    /**
     * Детальная страница элемента или его торгового предложения.
     */
    protected function element(Request $request, Iblock $iblock, string $code, ?IblockSection $section, ?string $offerCode): View|RedirectResponse
    {
        $view = PageGenerator::view($iblock, 'detail');

        abort_unless(view()->exists($view), 404);

        $element = Site::element($iblock->code, $code);

        abort_if($element === null, 404);

        $offer = $offerCode !== null ? $element->findOffer($offerCode) : null;

        abort_if($offerCode !== null && $offer === null, 404);

        // У страницы один адрес — канонический, заданный в инфоблоке. Любой другой
        // путь к тому же элементу (не тот раздел, лишний или пропущенный раздел,
        // id вместо кода) уводит на него, а не плодит дубли.
        if ($redirect = $this->toCanonical($request, $offer ?? $element)) {
            return $redirect;
        }

        $element->increment('views');

        return view($view, [
            'element' => $element,
            'offer' => $offer,
            'iblock' => $iblock,
            'section' => $section ?? $element->section,
        ]);
    }

    protected function toCanonical(Request $request, IblockElement $target): ?RedirectResponse
    {
        $canonical = $target->url();

        if (trim(parse_url($canonical, PHP_URL_PATH) ?? '', '/') === trim($request->path(), '/')) {
            return null;
        }

        $query = $request->getQueryString();

        return redirect()->to($canonical.($query ? '?'.$query : ''), 301);
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
