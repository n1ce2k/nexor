<?php

namespace Nexor\Cms\View\Components;

use Illuminate\View\View;
use Nexor\Cms\Models\IblockElement;
use Nexor\Cms\Models\IblockSection;
use Nexor\Cms\Support\CurrentPage;

/**
 * Хлебные крошки: Главная — инфоблок — разделы — элемент.
 *
 * Один раз в макете сайта, перед содержимым страницы:
 *
 * ```blade
 * <x-nexor::breadcrumbs />
 * <x-nexor::breadcrumbs exclude="/, cart, checkout, akcii/*" />
 * ```
 *
 * Путь берётся из открытой страницы: какой инфоблок, раздел и элемент, знает
 * контроллер страниц. На главной и там, где пути нет, ничего не выводится.
 *
 * Страница не из инфоблока (свой маршрут, `Route::view`) называется так, как
 * назвала себя в `@section('title', 'О компании')` — отдельно крошку ей
 * задавать не нужно. Свои шаги задаёт шаблон: `@breadcrumb('Акции', '/sale')`.
 *
 * Прежний вызов с параметрами работает как раньше:
 *
 * ```blade
 * <x-nexor::breadcrumbs iblock="katalog" :element="$element" />
 * ```
 *
 * Если страница вывела крошки сама, компонент в макете молчит — дублей нет.
 */
class Breadcrumbs extends Component
{
    /** @var array<int, array<string, mixed>>|null */
    protected ?array $crumbs = null;

    /**
     * @param  string|null  $iblock  Символьный код инфоблока; без него путь берётся из открытой страницы
     * @param  IblockElement|null  $element  Элемент, если это детальная страница
     * @param  IblockSection|null  $section  Раздел; по умолчанию берётся из адреса
     * @param  string  $template  Имя шаблона вёрстки
     * @param  array<int, string>|string  $exclude  Где крошки не нужны: адреса или имена маршрутов, можно со звёздочкой
     * @param  bool  $pageTitle  Называть страницу не из инфоблока по её `@section('title')`
     */
    public function __construct(
        public ?string $iblock = null,
        public ?IblockElement $element = null,
        public ?IblockSection $section = null,
        public string $template = 'default',
        public array|string $exclude = [],
        public bool $pageTitle = true,
    ) {}

    public function shouldRender(): bool
    {
        if ($this->excluded()) {
            return false;
        }

        // Вызов с параметрами — осознанный: его выводим всегда.
        if ($this->iblock !== null) {
            return true;
        }

        return ! CurrentPage::get()->breadcrumbsShown() && count($this->crumbs()) > 1;
    }

    public function render(): View
    {
        CurrentPage::get()->markBreadcrumbsShown();

        return $this->template($this->template, ['crumbs' => $this->crumbs()]);
    }

    protected function component(): string
    {
        return 'breadcrumbs';
    }

    /**
     * Путь до страницы; собирается один раз.
     *
     * @return array<int, array<string, mixed>>
     */
    protected function crumbs(): array
    {
        if ($this->crumbs !== null) {
            return $this->crumbs;
        }

        $page = CurrentPage::get();
        $own = $page->crumbs();

        // Страница не из инфоблока и без своих крошек: берём её заголовок.
        if ($own === [] && $this->iblock === null && $page->iblock() === null && ($title = $this->titleOfPage()) !== null) {
            $own = [['name' => $title, 'url' => null]];
        }

        return $this->crumbs = [...$this->trail(), ...$own];
    }

    /**
     * Заголовок страницы из её `@section('title')`.
     *
     * Blade выполняет шаблон страницы раньше макета, поэтому к этому моменту
     * секция уже задана. На главной заголовок — название сайта, а не шаг пути.
     */
    protected function titleOfPage(): ?string
    {
        if (! $this->pageTitle || request()->is('/')) {
            return null;
        }

        $view = app('view');

        if (! $view->hasSection('title')) {
            return null;
        }

        // Секция хранится уже экранированной, а шаблон крошек экранирует сам.
        $title = trim(html_entity_decode(strip_tags((string) $view->yieldContent('title')), ENT_QUOTES | ENT_HTML5, 'UTF-8'));

        return $title !== '' ? $title : null;
    }

    /**
     * @return array<int, array<string, mixed>>
     */
    protected function trail(): array
    {
        if ($this->iblock !== null) {
            return $this->iblocks()->getBreadcrumbs($this->iblock, $this->element, $this->section ?? $this->fromRequest());
        }

        $page = CurrentPage::get();

        // Страница не из инфоблока: только «Главная» — дальше её собственные крошки.
        return $page->iblock()
            ? $this->iblocks()->getBreadcrumbs($page->iblock()->code, $page->element(), $page->section())
            : [['name' => 'Главная', 'url' => url('/')]];
    }

    /**
     * Стоит ли текущая страница в списке исключений.
     *
     * Пишутся адреса (`cart`, `/`, `akcii/*`) или имена маршрутов
     * (`shop.cart`) — через запятую или массивом.
     */
    protected function excluded(): bool
    {
        $patterns = is_array($this->exclude)
            ? $this->exclude
            : (preg_split('/[\s,;]+/', $this->exclude, -1, PREG_SPLIT_NO_EMPTY) ?: []);

        foreach ($patterns as $pattern) {
            $pattern = trim((string) $pattern);

            if ($pattern === '') {
                continue;
            }

            $path = $pattern === '/' ? '/' : trim($pattern, '/');

            if (request()->is($path) || request()->routeIs($pattern)) {
                return true;
            }
        }

        return false;
    }

    protected function fromRequest(): ?IblockSection
    {
        // У элемента раздел свой — подставлять ещё один из адреса не надо.
        return $this->element ? null : $this->currentSection($this->requireIblock($this->iblock));
    }
}
