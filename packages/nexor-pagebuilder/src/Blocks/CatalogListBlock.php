<?php

namespace Nexor\PageBuilder\Blocks;

use Closure;
use Illuminate\Support\Collection;
use Nexor\Cms\Models\Iblock;
use Nexor\Cms\Models\IblockElement;
use Nexor\Cms\Models\IblockSection;
use Nexor\Cms\Services\InfoBlockService;
use Nexor\PageBuilder\Support\CardTemplates;
use Nexor\PageBuilder\Support\HtmlSanitizer;

/**
 * Каталог: элементы инфоблока каруселью.
 *
 * Элементы берутся из раздела (с подразделами), из всего инфоблока или
 * выбираются вручную. Карточка — шаблон компонента: `cardTemplate`
 * (`catalog.card.mini`), пусто — стандартная `catalog.card.default` с ценой и
 * кнопкой «В корзину», если стоит модуль магазина. Карусель — Swiper сайта.
 */
class CatalogListBlock extends Block
{
    public const MODES = ['section', 'all', 'manual'];

    public const SORTS = [
        'sort' => ['sort' => 'asc', 'name' => 'asc'],
        'active_from' => ['active_from' => 'desc', 'created_at' => 'desc'],
        'name' => ['name' => 'asc'],
    ];

    public const MAX_ITEMS = 50;

    public function type(): string
    {
        return 'catalog_list';
    }

    public function label(): string
    {
        return 'Каталог';
    }

    public function icon(): string
    {
        return 'cart';
    }

    public function rules(): array
    {
        return [
            'title' => ['nullable', 'string', 'max:255'],
            'description' => ['nullable', 'string', 'max:20000'],
            'cardTemplate' => ['nullable', 'string', 'max:150', function (string $attribute, mixed $value, Closure $fail): void {
                $name = CardTemplates::normalize($value);

                if ($name !== '' && ! CardTemplates::exists($name)) {
                    $fail("шаблон карточки {$name} не найден — создайте его командой php artisan nexor:component или проверьте имя.");
                }
            }],
            'source' => ['nullable', 'array'],
            'source.iblockId' => ['nullable', 'integer'],
            'source.selectionMode' => ['nullable', 'in:'.implode(',', self::MODES)],
            'source.sectionId' => ['nullable', 'integer'],
            'source.manualItems' => ['nullable', 'array', 'max:'.self::MAX_ITEMS],
            'source.manualItems.*.id' => ['required', 'integer'],
            'source.manualItems.*.name' => ['nullable', 'string', 'max:255'],
            'source.sortBy' => ['nullable', 'in:'.implode(',', array_keys(self::SORTS))],
            'source.limit' => ['nullable', 'integer', 'min:1', 'max:'.self::MAX_ITEMS],
            'view' => ['nullable', 'array'],
            'view.slidesPerView' => ['nullable', 'integer', 'min:1', 'max:6'],
            'view.gap' => ['nullable', 'integer', 'min:0', 'max:100'],
            'view.arrows' => ['nullable', 'boolean'],
            'view.dots' => ['nullable', 'boolean'],
        ];
    }

    public function defaults(): array
    {
        return [
            'title' => '',
            'description' => '',
            'cardTemplate' => '',
            'source' => [
                'iblockId' => null,
                'selectionMode' => 'section',
                'sectionId' => null,
                'manualItems' => [],
                'sortBy' => 'sort',
                'limit' => 12,
            ],
            'view' => ['slidesPerView' => 4, 'gap' => 20, 'arrows' => true, 'dots' => true],
        ];
    }

    public function prepare(array $data): array
    {
        $defaults = $this->defaults();
        $data = parent::prepare($data);

        // Вложенные группы тоже дополняются умолчаниями, а не заменяются целиком.
        $data['source'] = array_replace($defaults['source'], (array) ($data['source'] ?? []));
        $data['view'] = array_replace($defaults['view'], (array) ($data['view'] ?? []));

        return $data;
    }

    public function clean(array $data): array
    {
        $source = $data['source'] ?? [];
        $view = $data['view'] ?? [];
        $iblockId = isset($source['iblockId']) ? (int) $source['iblockId'] : null;
        $mode = $source['selectionMode'] ?? 'section';

        // Раздел и элементы — только из выбранного инфоблока.
        $sectionId = $mode === 'section' && ! empty($source['sectionId']) && $iblockId
            && IblockSection::query()->whereKey($source['sectionId'])->where('iblock_id', $iblockId)->exists()
                ? (int) $source['sectionId']
                : null;

        $manualIds = $mode === 'manual' && $iblockId
            ? IblockElement::query()->where('iblock_id', $iblockId)
                ->whereIn('id', array_map('intval', array_column($source['manualItems'] ?? [], 'id')))
                ->pluck('name', 'id')
            : collect();

        return [
            'title' => trim((string) ($data['title'] ?? '')),
            'description' => HtmlSanitizer::clean($data['description'] ?? ''),
            'cardTemplate' => CardTemplates::normalize($data['cardTemplate'] ?? ''),
            'source' => [
                'iblockId' => $iblockId && Iblock::query()->whereKey($iblockId)->exists() ? $iblockId : null,
                'selectionMode' => $mode,
                'sectionId' => $sectionId,
                // Порядок — как расставил редактор.
                'manualItems' => array_values(array_filter(array_map(
                    fn (array $item) => isset($manualIds[(int) $item['id']])
                        ? ['id' => (int) $item['id'], 'name' => $manualIds[(int) $item['id']]]
                        : null,
                    $source['manualItems'] ?? [],
                ))),
                'sortBy' => $source['sortBy'] ?? 'sort',
                'limit' => (int) ($source['limit'] ?? 12),
            ],
            'view' => [
                'slidesPerView' => (int) ($view['slidesPerView'] ?? 4),
                'gap' => (int) ($view['gap'] ?? 20),
                'arrows' => $this->bool($view['arrows'] ?? null, true),
                'dots' => $this->bool($view['dots'] ?? null, true),
            ],
        ];
    }

    public function isEmpty(array $data): bool
    {
        return $data['source']['iblockId'] === null
            || ($data['source']['selectionMode'] === 'manual' && $data['source']['manualItems'] === []);
    }

    public function text(array $data): string
    {
        return trim($data['title'].' '.HtmlSanitizer::text($data['description']));
    }

    /**
     * Шаблон получает сами элементы и вьюху карточки.
     */
    public function viewData(array $data): array
    {
        return $data + [
            'items' => $this->elements($data['source']),
            'cardView' => CardTemplates::view($data['cardTemplate'] ?? ''),
        ];
    }

    /**
     * Опубликованные элементы для карусели.
     *
     * @param  array<string, mixed>  $source
     * @return Collection<int, IblockElement>
     */
    public function elements(array $source): Collection
    {
        $iblock = empty($source['iblockId']) ? null : Iblock::query()->active()->find($source['iblockId']);

        if (! $iblock) {
            return collect();
        }

        $query = IblockElement::query()
            ->where('iblock_id', $iblock->id)
            ->active()
            ->with(['section', 'catalog'])
            ->limit((int) ($source['limit'] ?: 12));

        if ($source['selectionMode'] === 'manual') {
            $ids = array_map('intval', array_column($source['manualItems'] ?? [], 'id'));

            $elements = $query->whereIn('id', $ids)->get()->keyBy('id');

            return collect($ids)->map(fn (int $id) => $elements->get($id))->filter()->values()
                ->each(fn (IblockElement $element) => $element->setRelation('iblock', $iblock));
        }

        if ($source['selectionMode'] === 'section' && ! empty($source['sectionId'])) {
            $section = IblockSection::query()->where('iblock_id', $iblock->id)->find($source['sectionId']);

            if (! $section) {
                return collect();
            }

            $query->whereIn('section_id', [$section->id, ...app(InfoBlockService::class)->getDescendantSectionIds($section)]);
        }

        foreach (self::SORTS[$source['sortBy']] ?? self::SORTS['sort'] as $column => $direction) {
            $query->orderBy($column, $direction);
        }

        return $query->get()->each(fn (IblockElement $element) => $element->setRelation('iblock', $iblock));
    }
}
