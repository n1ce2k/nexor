<?php

namespace Nexor\Cms\Services;

use Illuminate\Contracts\Pagination\LengthAwarePaginator;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Collection;
use Illuminate\Support\Collection as BaseCollection;
use Illuminate\Support\Facades\Schema;
use Nexor\Cms\Enums\PropertyType;
use Nexor\Cms\Models\Iblock;
use Nexor\Cms\Models\IblockElement;
use Nexor\Cms\Models\IblockElementValue;
use Nexor\Cms\Models\IblockProperty;
use Nexor\Cms\Models\IblockSection;
use RuntimeException;

/**
 * Единая точка чтения инфоблоков для публичной части сайта.
 *
 * Это ядро, на которое опираются компоненты вроде `news.list` или
 * `catalog.section`: они описывают только разметку, а всё, что касается выборки
 * — фильтры, сортировка, разделы, постраничность — живёт здесь.
 *
 * Значения свойств у нас хранятся не в JSON-колонке, а построчно в
 * `iblock_element_values`, каждое в колонке своего типа. Поэтому фильтр и
 * сортировка по свойству разворачиваются в подзапрос по этой таблице — снаружи
 * это по-прежнему просто `['PRICE' => ['>', 1000]]`.
 *
 * ```php
 * $service = app(InfoBlockService::class);
 *
 * $news = $service->getActiveElements('news', ['created_at' => 'desc'], perPage: 10);
 * $item = $service->getElementByCode('news', 'otkrytie-magazina');
 * $tree = $service->getSectionsTree('katalog');
 * ```
 */
class InfoBlockService
{
    /** Колонки самого элемента: по ним можно и фильтровать, и сортировать. */
    public const COLUMNS = [
        'id', 'code', 'name', 'is_active', 'sort', 'section_id',
        'views', 'created_at', 'updated_at', 'active_from', 'active_to',
    ];

    /** @var array<int, string> */
    public const OPERATORS = ['>', '<', '>=', '<=', '=', '!=', '<>', 'LIKE', 'NOT LIKE'];

    /**
     * Инфоблоки, уже найденные в этом запросе.
     *
     * Компонент на странице обычно спрашивает один и тот же код по нескольку
     * раз — списком, счётчиком, хлебными крошками.
     *
     * @var array<string, Iblock|null>
     */
    protected array $blocks = [];

    /**
     * Инфоблок по символьному коду. Неактивные не отдаются.
     */
    public function getInfoBlockByCode(string $code): ?Iblock
    {
        $this->checkInfoBlocksModule();

        return $this->blocks[$code] ??= Iblock::query()
            ->active()
            ->where('code', $code)
            ->with('properties.enums')
            ->first();
    }

    /**
     * Есть ли такой инфоблок на сайте.
     */
    public function infoBlockExists(string $code): bool
    {
        return $this->getInfoBlockByCode($code) !== null;
    }

    // ------------------------------------------------------------- элементы

    /**
     * Элементы инфоблока с фильтром, сортировкой и постраничностью.
     *
     * @param  array<string, mixed>  $filter  Колонка или код свойства => значение
     * @param  array<string, string>  $order  Поле => asc|desc
     * @param  int|null  $perPage  null — вернуть всё одной коллекцией
     * @param  int|null  $page  null — взять номер страницы из адреса (`?page=2`)
     * @param  int|null  $limit  Ограничение для выборки без страниц
     * @return Collection<int, IblockElement>|LengthAwarePaginator<int, IblockElement>
     */
    public function getElements(
        string $code,
        array $filter = [],
        array $order = ['sort' => 'asc'],
        ?int $perPage = null,
        ?int $page = null,
        ?int $limit = null,
    ): Collection|LengthAwarePaginator {
        $iblock = $this->requireInfoBlock($code);

        $query = $this->query($iblock, $filter, $order);

        if ($perPage !== null) {
            return $query->paginate($perPage, ['*'], 'page', $page)->withQueryString();
        }

        return $query->when($limit !== null, fn (Builder $q) => $q->limit($limit))->get();
    }

    /**
     * То же самое, но только опубликованные элементы.
     *
     * «Активен» здесь — не только галочка: элемент со сроком показа виден лишь
     * внутри него.
     *
     * @param  array<string, string>  $order
     * @return Collection<int, IblockElement>|LengthAwarePaginator<int, IblockElement>
     */
    public function getActiveElements(
        string $code,
        array $order = ['sort' => 'asc'],
        ?int $perPage = null,
        ?int $page = null,
    ): Collection|LengthAwarePaginator {
        return $this->getElements($code, ['is_active' => true], $order, $perPage, $page);
    }

    public function getElementById(string $code, int $id): ?IblockElement
    {
        return $this->query($this->requireInfoBlock($code))->find($id);
    }

    public function getElementByCode(string $infoBlockCode, string $elementCode): ?IblockElement
    {
        return $this->query($this->requireInfoBlock($infoBlockCode))
            ->where('code', $elementCode)
            ->first();
    }

    /**
     * Первый элемент по фильтру — например, «последняя акция».
     *
     * @param  array<string, mixed>  $filter
     * @param  array<string, string>  $order
     */
    public function getFirstElement(
        string $code,
        array $filter = [],
        array $order = ['sort' => 'asc'],
    ): ?IblockElement {
        return $this->query($this->requireInfoBlock($code), $filter, $order)->first();
    }

    /**
     * Случайные элементы — для блоков «а ещё посмотрите».
     *
     * @param  array<string, mixed>  $filter
     * @return Collection<int, IblockElement>
     */
    public function getRandomElements(string $code, int $count = 1, array $filter = []): Collection
    {
        return $this->query($this->requireInfoBlock($code), $filter, [])
            ->inRandomOrder()
            ->limit($count)
            ->get();
    }

    /**
     * Свежие элементы — по дате создания.
     *
     * @param  array<string, mixed>  $filter
     * @return Collection<int, IblockElement>
     */
    public function getLatestElements(string $code, int $count = 10, array $filter = []): Collection
    {
        return $this->query($this->requireInfoBlock($code), $filter, ['created_at' => 'desc'])
            ->limit($count)
            ->get();
    }

    /**
     * Популярные элементы.
     *
     * По умолчанию считаем по счётчику просмотров, который ведёт сама CMS; но
     * метрикой может быть и любое числовое свойство.
     *
     * @param  array<string, mixed>  $filter
     * @return Collection<int, IblockElement>
     */
    public function getPopularElements(
        string $code,
        string $metric = 'views',
        int $count = 10,
        array $filter = [],
    ): Collection {
        return $this->query($this->requireInfoBlock($code), $filter, [$metric => 'desc'])
            ->limit($count)
            ->get();
    }

    /**
     * Поиск по названию, символьному коду и текстовым свойствам.
     *
     * Свойства берутся те, у которых в админке стоит галочка «участвует в поиске».
     *
     * Регистр зависит от сопоставления базы: в MySQL с `utf8mb4_*_ci` поиск
     * регистронезависимый, в SQLite — только для латиницы.
     *
     * @param  array<string, mixed>  $filter
     * @param  array<string, string>  $order
     * @return Collection<int, IblockElement>|LengthAwarePaginator<int, IblockElement>
     */
    public function searchElements(
        string $code,
        string $search,
        array $filter = [],
        ?int $perPage = null,
        ?int $page = null,
        array $order = ['sort' => 'asc'],
        ?int $limit = null,
    ): Collection|LengthAwarePaginator {
        $term = '%'.trim($search).'%';

        $iblock = $this->requireInfoBlock($code);
        $searchable = $this->searchableProperties($iblock);

        $query = $this->query($iblock, $filter, $order)
            ->where(function (Builder $q) use ($term, $searchable): void {
                $q->where('name', 'like', $term)->orWhere('code', 'like', $term);

                foreach ($searchable as $property) {
                    $column = $property->type->column();

                    $q->orWhereHas('values', fn (Builder $values) => $values
                        ->where('property_id', $property->id)
                        ->where($column, 'like', $term));
                }
            });

        if ($perPage !== null) {
            return $query->paginate($perPage, ['*'], 'page', $page)->withQueryString();
        }

        return $query->when($limit !== null, fn (Builder $q) => $q->limit($limit))->get();
    }

    /**
     * Свойства, по которым имеет смысл искать текстом.
     *
     * @return Collection<int, IblockProperty>
     */
    protected function searchableProperties(Iblock $iblock): Collection
    {
        return $iblock->properties
            ->where('is_active', true)
            ->where('is_searchable', true)
            ->filter(fn (IblockProperty $property) => in_array(
                $property->type->column(), ['value_string', 'value_text'], true,
            ))
            ->values();
    }

    /**
     * Элементы с определённым значением свойства.
     *
     * @param  array<string, string>  $order
     * @return Collection<int, IblockElement>|LengthAwarePaginator<int, IblockElement>
     */
    public function getElementsByProperty(
        string $code,
        string $propertyCode,
        mixed $value,
        array $order = ['sort' => 'asc'],
        ?int $perPage = null,
        ?int $page = null,
    ): Collection|LengthAwarePaginator {
        return $this->getElements($code, [$propertyCode => $value], $order, $perPage, $page);
    }

    /**
     * Элементы, разложенные по значению свойства.
     *
     * Множественное свойство кладёт элемент в каждую свою группу.
     *
     * @param  array<string, mixed>  $filter
     * @return array<string, array<int, IblockElement>>
     */
    public function getElementsGroupedByProperty(string $code, string $propertyCode, array $filter = []): array
    {
        $grouped = [];

        foreach ($this->getElements($code, $filter) as $element) {
            $value = $element->property($propertyCode);
            $keys = $value instanceof BaseCollection ? $value->all() : [$value];

            foreach ($keys as $key) {
                $grouped[$this->groupKey($key)][] = $element;
            }
        }

        return $grouped;
    }

    /**
     * Сколько элементов подходит под фильтр.
     *
     * @param  array<string, mixed>  $filter
     */
    public function countElements(string $code, array $filter = []): int
    {
        return $this->query($this->requireInfoBlock($code), $filter, [])->count();
    }

    public function elementExists(string $code, int $id): bool
    {
        return $this->getElementById($code, $id) !== null;
    }

    public function elementExistsByCode(string $infoBlockCode, string $elementCode): bool
    {
        return $this->getElementByCode($infoBlockCode, $elementCode) !== null;
    }

    /**
     * Элемент вместе с описанием его свойств.
     *
     * Шаблону детальной страницы обычно нужно и значение, и подпись свойства,
     * и его тип — чтобы решить, как это рисовать.
     *
     * @return array{element: IblockElement, properties: array<string, array{property: IblockProperty, value: mixed}>}|null
     */
    public function getElementWithFields(string $code, int $id): ?array
    {
        $element = $this->getElementById($code, $id);

        if (! $element) {
            return null;
        }

        $values = $element->propertyValues();

        $properties = $this->getProperties($code)
            ->mapWithKeys(fn (IblockProperty $property) => [$property->code => [
                'property' => $property,
                'value' => $values->get($property->code),
            ]])
            ->all();

        return ['element' => $element, 'properties' => $properties];
    }

    // ------------------------------------------------------------- свойства

    /**
     * Активные свойства инфоблока.
     *
     * @return Collection<int, IblockProperty>
     */
    public function getProperties(string $code): Collection
    {
        return $this->requireInfoBlock($code)->properties->where('is_active', true)->values();
    }

    public function getProperty(string $code, string $propertyCode): ?IblockProperty
    {
        return $this->getProperties($code)->firstWhere('code', $propertyCode);
    }

    /**
     * Варианты всех свойств-списков инфоблока.
     *
     * Готово для фильтров на витрине: код свойства => список вариантов.
     *
     * @return array<string, array<int, array{id: int, code: string|null, value: string}>>
     */
    public function getEnumOptions(string $code): array
    {
        $options = [];

        foreach ($this->getProperties($code) as $property) {
            if (! $property->type->usesEnums()) {
                continue;
            }

            $options[$property->code] = $property->enums
                ->sortBy('sort')
                ->map(fn ($enum) => ['id' => $enum->id, 'code' => $enum->code, 'value' => $enum->value])
                ->values()
                ->all();
        }

        return $options;
    }

    // -------------------------------------------------------------- разделы

    /**
     * Плоский список разделов, уже в порядке дерева.
     *
     * @return Collection<int, IblockSection>
     */
    public function getSections(string $code, bool $activeOnly = true): Collection
    {
        $iblock = $this->requireSectionedInfoBlock($code);

        return $iblock->sections()
            ->when($activeOnly, fn (Builder $q) => $q->active())
            ->ordered()
            ->get();
    }

    /**
     * Дерево разделов.
     *
     * Собирается из одного плоского запроса: материализованный путь уже задаёт
     * порядок, а вложенность восстанавливается по `parent_id`.
     *
     * @return array<int, array<string, mixed>>
     */
    public function getSectionsTree(string $code, bool $activeOnly = true): array
    {
        $nodes = [];
        $roots = [];

        foreach ($this->getSections($code, $activeOnly) as $section) {
            $nodes[$section->id] = [
                'id' => $section->id,
                'parent_id' => $section->parent_id,
                'code' => $section->code,
                'name' => $section->name,
                'depth' => $section->depth,
                'picture' => $section->picture_url,
                'description' => $section->description,
                'is_active' => $section->is_active,
                'section' => $section,
                'children' => [],
            ];
        }

        foreach ($nodes as $id => &$node) {
            if ($node['parent_id'] && isset($nodes[$node['parent_id']])) {
                $nodes[$node['parent_id']]['children'][] = &$node;
            } else {
                $roots[] = &$node;
            }
        }

        unset($node);

        return $roots;
    }

    public function getSectionById(string $code, int $sectionId): ?IblockSection
    {
        return $this->requireSectionedInfoBlock($code)->sections()->whereKey($sectionId)->first();
    }

    public function getSectionByCode(string $infoBlockCode, string $sectionCode): ?IblockSection
    {
        return $this->requireSectionedInfoBlock($infoBlockCode)
            ->sections()
            ->where('code', $sectionCode)
            ->first();
    }

    /**
     * Элементы одного раздела.
     *
     * @param  array<string, mixed>  $filter
     * @param  array<string, string>  $order
     * @return Collection<int, IblockElement>|LengthAwarePaginator<int, IblockElement>
     */
    public function getElementsBySection(
        string $code,
        int $sectionId,
        array $filter = [],
        array $order = ['sort' => 'asc'],
        ?int $perPage = null,
        ?int $page = null,
        bool $activeOnly = true,
    ): Collection|LengthAwarePaginator {
        $this->requireSectionedInfoBlock($code);

        $filter['section_id'] = $sectionId;

        if ($activeOnly) {
            $filter['is_active'] = true;
        }

        return $this->getElements($code, $filter, $order, $perPage, $page);
    }

    /**
     * Элементы раздела вместе со всеми вложенными.
     *
     * Потомков даёт материализованный путь — одним запросом, без обхода дерева.
     *
     * @param  array<string, mixed>  $filter
     * @param  array<string, string>  $order
     * @return Collection<int, IblockElement>|LengthAwarePaginator<int, IblockElement>
     */
    public function getElementsBySectionRecursive(
        string $code,
        int $sectionId,
        array $filter = [],
        array $order = ['sort' => 'asc'],
        ?int $perPage = null,
        ?int $page = null,
        bool $activeOnly = true,
    ): Collection|LengthAwarePaginator {
        $section = $this->getSectionById($code, $sectionId);

        if (! $section) {
            throw new RuntimeException("Раздел #{$sectionId} не найден в инфоблоке «{$code}».");
        }

        if ($activeOnly) {
            $filter['is_active'] = true;
        }

        $filter['section_id'] = $this->sectionWithDescendants($section);

        return $this->getElements($code, $filter, $order, $perPage, $page);
    }

    /**
     * Все элементы инфоблока с разделами, независимо от раздела.
     *
     * @param  array<string, mixed>  $filter
     * @param  array<string, string>  $order
     * @return Collection<int, IblockElement>|LengthAwarePaginator<int, IblockElement>
     */
    public function getAllCatalogElements(
        string $code,
        array $filter = [],
        array $order = ['sort' => 'asc'],
        ?int $perPage = null,
        ?int $page = null,
        bool $activeOnly = true,
    ): Collection|LengthAwarePaginator {
        $this->requireSectionedInfoBlock($code);

        if ($activeOnly) {
            $filter['is_active'] = true;
        }

        return $this->getElements($code, $filter, $order, $perPage, $page);
    }

    /**
     * Идентификаторы раздела и всего, что под ним.
     *
     * @return array<int, int>
     */
    public function getDescendantSectionIds(IblockSection $section): array
    {
        return IblockSection::query()
            ->where('iblock_id', $section->iblock_id)
            ->where('path', 'like', $section->path.$section->id.'/%')
            ->pluck('id')
            ->all();
    }

    // -------------------------------------------------------- хлебные крошки

    /**
     * Хлебные крошки: Главная — инфоблок — [разделы] — элемент.
     *
     * @return array<int, array{name: string, url: string|null, code?: string|null, id?: int}>
     */
    public function getBreadcrumbs(string $infoBlockCode, ?IblockElement $element = null, ?IblockSection $section = null): array
    {
        $iblock = $this->getInfoBlockByCode($infoBlockCode);

        $crumbs = [['name' => 'Главная', 'url' => url('/')]];

        if (! $iblock) {
            return $crumbs;
        }

        $crumbs[] = [
            'id' => $iblock->id,
            'code' => $iblock->code,
            'name' => $iblock->name,
            'url' => url('/'.$iblock->code),
        ];

        // Раздел элемента подставляется сам, если его не передали явно.
        $section ??= $element?->section;

        if ($section) {
            foreach ($section->ancestors()->push($section) as $step) {
                $crumbs[] = [
                    'id' => $step->id,
                    'code' => $step->code,
                    'name' => $step->name,
                    'url' => url('/'.$iblock->code.'/'.($step->code ?: $step->id)),
                ];
            }
        }

        if ($element) {
            $crumbs[] = [
                'id' => $element->id,
                'code' => $element->code,
                'name' => $element->name,
                'url' => $element->url(),
            ];
        }

        return $crumbs;
    }

    // ------------------------------------------------------------ внутренняя

    /**
     * Готовый запрос по элементам инфоблока.
     *
     * @param  array<string, mixed>  $filter
     * @param  array<string, string>  $order
     * @return Builder<IblockElement>
     */
    protected function query(Iblock $iblock, array $filter = [], array $order = ['sort' => 'asc']): Builder
    {
        // Именно Builder, а не отношение: дальше запрос уходит в фильтры и
        // сортировку, которым нужен полноценный конструктор.
        $query = IblockElement::query()
            ->where('iblock_id', $iblock->id)
            ->with(['section', 'values.property', 'values.enum']);

        $this->applyFilters($query, $filter, $iblock);
        $this->applyOrdering($query, $order, $iblock);

        return $query;
    }

    /**
     * Фильтры.
     *
     * Ключ — либо колонка элемента, либо код свойства. Значение:
     *
     * - `'кухня'` — точное совпадение (для `name` — подстрока);
     * - `[1, 2, 3]` — любое из перечисленного;
     * - `['>', 1000]` — сравнение, оператор из {@see self::OPERATORS};
     * - `true` для `is_active` — ещё и срок показа.
     *
     * @param  Builder<IblockElement>  $query
     * @param  array<string, mixed>  $filter
     */
    protected function applyFilters(Builder $query, array $filter, Iblock $iblock): void
    {
        foreach ($filter as $key => $value) {
            if ($key === 'is_active' && $value === true) {
                // Активность элемента — это ещё и `active_from`/`active_to`.
                $query->active();

                continue;
            }

            if (in_array($key, self::COLUMNS, true)) {
                $this->applyColumnFilter($query, $key, $value);

                continue;
            }

            $property = $iblock->properties->firstWhere('code', $key);

            if (! $property) {
                throw new RuntimeException("У инфоблока «{$iblock->code}» нет свойства «{$key}».");
            }

            $this->applyPropertyFilter($query, $property, $value);
        }
    }

    /**
     * @param  Builder<IblockElement>  $query
     */
    protected function applyColumnFilter(Builder $query, string $column, mixed $value): void
    {
        if ($operator = $this->operatorIn($value)) {
            $query->where($column, $operator, $value[1]);

            return;
        }

        if (is_array($value)) {
            $query->whereIn($column, $value);

            return;
        }

        if ($value === null) {
            $query->whereNull($column);

            return;
        }

        // Название ищут по вхождению — точное совпадение почти никогда не нужно.
        $column === 'name' && is_string($value)
            ? $query->where('name', 'like', '%'.$value.'%')
            : $query->where($column, $value);
    }

    /**
     * Фильтр по значению свойства.
     *
     * @param  Builder<IblockElement>  $query
     */
    protected function applyPropertyFilter(Builder $query, IblockProperty $property, mixed $value): void
    {
        $column = $property->type->column();

        $query->whereHas('values', function (Builder $values) use ($property, $column, $value): void {
            $values->where('property_id', $property->id);

            if ($property->type === PropertyType::Select) {
                $this->applyEnumFilter($values, $property, $value);

                return;
            }

            if ($operator = $this->operatorIn($value)) {
                $values->where($column, $operator, $value[1]);

                return;
            }

            is_array($value)
                ? $values->whereIn($column, $value)
                : $values->where($column, $value);
        });
    }

    /**
     * Список принимают и по id варианта, и по его коду, и по подписи.
     *
     * @param  Builder<IblockElementValue>  $values
     */
    protected function applyEnumFilter(Builder $values, IblockProperty $property, mixed $value): void
    {
        $wanted = collect(is_array($value) ? $value : [$value])
            ->map(function (mixed $one) use ($property): ?int {
                if (is_int($one) || ctype_digit((string) $one)) {
                    return (int) $one;
                }

                return $property->enums
                    ->first(fn ($enum) => $enum->code === $one || $enum->value === $one)?->id;
            })
            ->filter()
            ->values()
            ->all();

        $values->whereIn('value_enum_id', $wanted ?: [0]);
    }

    /**
     * Сортировка по колонкам элемента и по значениям свойств.
     *
     * @param  Builder<IblockElement>  $query
     * @param  array<string, string>  $order
     */
    protected function applyOrdering(Builder $query, array $order, Iblock $iblock): void
    {
        foreach ($order as $field => $direction) {
            $direction = strtolower((string) $direction) === 'desc' ? 'desc' : 'asc';

            if (in_array($field, self::COLUMNS, true)) {
                $query->orderBy($field, $direction);

                continue;
            }

            $property = $iblock->properties->firstWhere('code', $field);

            if (! $property) {
                throw new RuntimeException("По свойству «{$field}» инфоблока «{$iblock->code}» сортировать нельзя: его нет.");
            }

            // Значение лежит в отдельной строке, поэтому подтягиваем его подзапросом.
            $query->orderBy(
                IblockElementValue::query()
                    ->select($property->type->column())
                    ->whereColumn('element_id', 'iblock_elements.id')
                    ->where('property_id', $property->id)
                    ->orderBy('sort')
                    ->limit(1),
                $direction,
            );
        }
    }

    /**
     * @return array<int, int>
     */
    protected function sectionWithDescendants(IblockSection $section): array
    {
        return [$section->id, ...$this->getDescendantSectionIds($section)];
    }

    /**
     * Оператор сравнения, если значение записано как `['>', 100]`.
     */
    protected function operatorIn(mixed $value): ?string
    {
        if (! is_array($value) || count($value) !== 2 || ! is_string($value[0] ?? null)) {
            return null;
        }

        $operator = strtoupper($value[0]);

        return in_array($operator, self::OPERATORS, true) ? $operator : null;
    }

    protected function groupKey(mixed $value): string
    {
        return match (true) {
            $value === null, $value === '' => 'uncategorized',
            is_bool($value) => $value ? '1' : '0',
            is_object($value) && property_exists($value, 'name') => (string) $value->name,
            is_object($value) && method_exists($value, '__toString') => (string) $value,
            default => (string) $value,
        };
    }

    protected function requireInfoBlock(string $code): Iblock
    {
        return $this->getInfoBlockByCode($code)
            ?? throw new RuntimeException("Инфоблок «{$code}» не найден или отключён.");
    }

    /**
     * Инфоблок, у которого включены разделы.
     */
    protected function requireSectionedInfoBlock(string $code): Iblock
    {
        $iblock = $this->requireInfoBlock($code);

        if (! $iblock->has_sections) {
            throw new RuntimeException("У инфоблока «{$code}» выключены разделы.");
        }

        return $iblock;
    }

    /**
     * CMS может стоять в приложении, где миграции ещё не прогоняли.
     */
    protected function checkInfoBlocksModule(): void
    {
        if (! Schema::hasTable('iblocks')) {
            throw new RuntimeException('Инфоблоки не установлены: выполните php artisan migrate.');
        }
    }
}
