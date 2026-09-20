<?php

namespace Nexor\Cms\View\Components\Catalog;

use Illuminate\Support\Collection;
use Illuminate\View\View;
use Nexor\Cms\Models\IblockProperty;
use Nexor\Cms\View\Components\Component;

/**
 * Фильтр по свойствам инфоблока — аналог `catalog.filter`.
 *
 * ```blade
 * <x-nexor::catalog.filter iblock="katalog" />
 * <x-nexor::catalog.filter iblock="katalog" :only="['PRICE', 'FINISH']" />
 * ```
 *
 * Набор полей компонент выясняет сам: берёт свойства, у которых в админке
 * стоит галочка «участвует в фильтре». Отправляется обычной формой методом
 * GET, поэтому со списком он связан только адресной строкой — как через
 * `FILTER_NAME` в Битриксе, только выбор виден в URL и работает «назад».
 */
class Filter extends Component
{
    /**
     * @param  string  $iblock  Символьный код инфоблока
     * @param  string  $template  Имя шаблона вёрстки
     * @param  array<int, string>  $only  Ограничить набор свойств этими кодами
     * @param  string|null  $action  Адрес формы; по умолчанию — текущая страница
     * @param  bool  $price  Показывать ли блок цены у торгового каталога
     */
    public function __construct(
        public string $iblock,
        public string $template = 'default',
        public array $only = [],
        public ?string $action = null,
        public bool $price = true,
    ) {}

    public function render(): View
    {
        $block = $this->requireIblock($this->iblock);

        return $this->template($this->template, [
            'block' => $block,
            'properties' => $this->properties(),
            'options' => $this->iblocks()->getEnumOptions($this->iblock),
            'values' => request()->query(),
            // Отправляем туда же, где стоим: со страницы раздела фильтр не должен
            // выкидывать в корень каталога.
            'formAction' => $this->action ?? url()->current(),
            // Раздел выбирают не в фильтре, но терять его при отправке нельзя.
            'currentSection' => (string) request()->query('section', ''),
            // Цена торгового каталога: границы для ползунка и выбор покупателя.
            // Ключ не `price`: так называется проп-выключатель, а публичное
            // свойство компонента перекрыло бы данные вьюхи.
            'priceRange' => $this->priceRange($block->code),
        ]);
    }

    protected function component(): string
    {
        return 'catalog.filter';
    }

    /**
     * Границы цены и то, что покупатель уже выбрал.
     *
     * Считается по активным товарам инфоблока, а на странице раздела — по его
     * товарам: ползунок не должен обещать цены, которых в этом разделе нет.
     *
     * @return array{min: float, max: float, from: string, to: string}|null
     */
    protected function priceRange(string $code): ?array
    {
        if (! $this->price) {
            return null;
        }

        $section = (string) request()->query('section', '');
        $filter = [];

        if ($section !== '') {
            $found = $this->iblocks()->getSectionByCode($code, $section);
            $filter = $found ? ['section_id' => $found->id] : [];
        }

        $range = $this->iblocks()->getPriceRange($code, $filter);

        if ($range === null || $range['min'] >= $range['max']) {
            return null;
        }

        return [
            'min' => floor($range['min']),
            'max' => ceil($range['max']),
            'from' => (string) request()->query('price_from', ''),
            'to' => (string) request()->query('price_to', ''),
        ];
    }

    /**
     * @return Collection<int, IblockProperty>
     */
    protected function properties(): Collection
    {
        return $this->iblocks()
            ->getProperties($this->iblock)
            ->where('is_filterable', true)
            ->when($this->only !== [], fn (Collection $all) => $all->whereIn('code', $this->only))
            ->values();
    }
}
