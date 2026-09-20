<?php

namespace Nexor\Cms\View\Components\Catalog;

use Illuminate\Pagination\LengthAwarePaginator;
use Illuminate\Support\Collection;
use Illuminate\View\View;
use Nexor\Cms\Models\IblockProperty;
use Nexor\Cms\Models\IblockSection;
use Nexor\Cms\View\Components\Component;
use RuntimeException;

/**
 * Список элементов инфоблока — то же, чем в Битриксе заняты `catalog.section`
 * и `news.list`: разница между ними только в шаблоне и значениях по умолчанию.
 *
 * ```blade
 * <x-nexor::catalog.section iblock="katalog" template="tiles" />
 * <x-nexor::catalog.section iblock="news" :order="['created_at' => 'desc']" :per-page="5" />
 * <x-nexor::catalog.section iblock="katalog" :section_id="3" />
 * ```
 *
 * Раздел и фильтр компонент берёт из адресной строки, поэтому `catalog.filter`
 * и меню разделов ничего не знают про список — связь идёт через URL.
 * `section_id` закрепляет раздел жёстко: адрес страницы его не меняет, а
 * удалённый раздел даёт пустой список, а не весь инфоблок.
 */
class Section extends Component
{
    /**
     * @param  string  $iblock  Символьный код инфоблока
     * @param  string  $template  Имя шаблона вёрстки
     * @param  string|null  $section  Код раздела; по умолчанию берётся из адреса страницы
     * @param  int|string|null  $sectionId  Id раздела — важнее кода и адреса страницы (`:section_id="3"`)
     * @param  array<string, mixed>  $filter  Дополнительный фильтр поверх адресной строки
     * @param  array<string, string>  $order  Сортировка
     * @param  int|null  $perPage  Размер страницы; по умолчанию — настройка инфоблока
     * @param  string  $card  Вьюха карточки одного элемента
     * @param  bool  $recursive  Учитывать ли вложенные разделы
     * @param  bool  $paginate  Выключите, чтобы получить список без страниц
     * @param  int|null  $limit  Сколько вывести, когда страницы выключены
     */
    public function __construct(
        public string $iblock,
        public string $template = 'default',
        public ?string $section = null,
        public array $filter = [],
        public array $order = ['sort' => 'asc'],
        public ?int $perPage = null,
        public string $card = 'nexor::components.catalog.card.default',
        public bool $recursive = true,
        public bool $paginate = true,
        public ?int $limit = null,
        public int|string|null $sectionId = null,
    ) {}

    public function render(): View
    {
        $iblocks = $this->iblocks();
        $block = $this->requireIblock($this->iblock);

        $filter = array_merge(['is_active' => true], $this->fromRequest(), $this->filter);
        $perPage = $this->paginate ? ($this->perPage ?? $block->pageSize()) : null;

        $pinned = $this->sectionId !== null && $this->sectionId !== '';
        $section = $pinned ? $this->pinnedSection() : $this->currentSection($block, $this->section);

        $elements = match (true) {
            // Закреплённый раздел пропал — пустой список, а не весь инфоблок.
            $pinned && ! $section => $perPage === null
                ? collect()
                : new LengthAwarePaginator([], 0, $perPage, LengthAwarePaginator::resolveCurrentPage()),
            $section && $this->recursive => $iblocks->getElementsBySectionRecursive(
                $this->iblock, $section->id, $filter, $this->order, $perPage,
            ),
            (bool) $section => $iblocks->getElementsBySection(
                $this->iblock, $section->id, $filter, $this->order, $perPage,
            ),
            default => $iblocks->getElements(
                $this->iblock, $filter, $this->order, $perPage, limit: $this->limit,
            ),
        };

        // Без страниц ограничение применяется и к разделам тоже.
        if ($perPage === null && $this->limit !== null && $elements instanceof Collection) {
            $elements = $elements->take($this->limit);
        }

        // Публичные свойства компонента перекрывают данные вьюхи, поэтому имена
        // ключей отличаются от имён пропов: `$iblock` — это код, `$block` — модель.
        return $this->template($this->template, [
            'block' => $block,
            'current' => $section,
            'elements' => $elements,
            'cardView' => $this->card,
        ]);
    }

    protected function component(): string
    {
        return 'catalog.section';
    }

    /**
     * Раздел из `section_id` — только из этого инфоблока.
     */
    protected function pinnedSection(): ?IblockSection
    {
        if (! ctype_digit((string) $this->sectionId)) {
            throw new RuntimeException("Компоненту {$this->component()} передан section_id «{$this->sectionId}» — нужно число.");
        }

        return $this->iblocks()->getSectionById($this->iblock, (int) $this->sectionId);
    }

    /**
     * Фильтр, собранный из адресной строки.
     *
     * Компонент смотрит только на свойства с галочкой «участвует в фильтре»,
     * поэтому произвольный параметр в URL не может превратиться в условие
     * запроса.
     *
     * Понимает три формы: `?FINISH=Матовый`, `?FINISH[]=A&FINISH[]=B`
     * и границы `?FINISH_FROM=100`, `?FINISH_TO=900`. Цена торгового каталога
     * приходит отдельно: `?price_from=`, `?price_to=`.
     *
     * @return array<string, mixed>
     */
    protected function fromRequest(): array
    {
        $filter = [];

        foreach ($this->filterable() as $property) {
            $code = $property->code;
            $value = request()->query($code);

            if (is_array($value)) {
                $value = array_values(array_filter($value, fn ($item) => is_scalar($item) && $item !== ''));
            }

            if ($value !== null && $value !== '' && $value !== []) {
                $filter[$code] = $value;

                continue;
            }

            // Обе границы работают вместе: «от 100 до 900» — это одно условие.
            $range = $this->range($code.'_FROM', $code.'_TO');

            if ($range !== []) {
                $filter[$code] = $range;
            }
        }

        // Цена лежит в торговом каталоге, а не в свойствах, поэтому и параметр
        // у неё свой, всегда один и тот же.
        $price = $this->range('price_from', 'price_to');

        if ($price !== [] && $this->requireIblock($this->iblock)->is_catalog) {
            $filter['price'] = $price;
        }

        return $filter;
    }

    /**
     * Границы диапазона из адреса: пустые не попадают в фильтр.
     *
     * @return array<string, string>
     */
    protected function range(string $from, string $to): array
    {
        $range = [];

        foreach ([['from', $from], ['to', $to]] as [$key, $parameter]) {
            $bound = request()->query($parameter);

            if ($bound !== null && $bound !== '' && is_numeric($bound)) {
                $range[$key] = $bound;
            }
        }

        return $range;
    }

    /**
     * @return Collection<int, IblockProperty>
     */
    protected function filterable(): Collection
    {
        return $this->iblocks()->getProperties($this->iblock)->where('is_filterable', true);
    }
}
