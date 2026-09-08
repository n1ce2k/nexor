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
     * @param  string|null  $action  Адрес формы; по умолчанию — страница инфоблока
     */
    public function __construct(
        public string $iblock,
        public string $template = 'default',
        public array $only = [],
        public ?string $action = null,
    ) {}

    public function render(): View
    {
        $block = $this->requireIblock($this->iblock);

        return $this->template($this->template, [
            'block' => $block,
            'properties' => $this->properties(),
            'options' => $this->iblocks()->getEnumOptions($this->iblock),
            'values' => request()->query(),
            'formAction' => $this->action ?? url('/'.$block->code),
            // Раздел выбирают не в фильтре, но терять его при отправке нельзя.
            'currentSection' => (string) request()->query('section', ''),
        ]);
    }

    protected function component(): string
    {
        return 'catalog.filter';
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
