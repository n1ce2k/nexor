<?php

namespace Nexor\Cms\View\Components;

use Illuminate\View\View;
use Nexor\Cms\Models\Iblock;
use Nexor\Cms\Models\IblockElement;
use Nexor\Cms\Models\IblockSection;
use Nexor\Cms\Support\MenuResolver;
use RuntimeException;

/**
 * Навигация по инфоблоку — аналог `bitrix:menu`.
 *
 * ```blade
 * <x-nexor::menu code="main" />                           меню из админки
 * <x-nexor::menu iblock="katalog" :depth="3" />           разделы на три уровня
 * <x-nexor::menu iblock="katalog" root="mebel" />         только ветка раздела
 * ```
 *
 * Два источника. `code` — меню, собранное в админке: там рядом живут рукописные
 * ссылки и динамические ветки разделов. `iblock` — быстрый вариант без всякой
 * админки, когда надо просто показать разделы каталога.
 *
 * Дерево строится из разделов, а элементы становятся листьями. По умолчанию
 * инфоблок с разделами даёт меню разделов, а без разделов — меню элементов:
 * выводить все товары каталога пунктами меню почти никогда не нужно.
 *
 * Глубина считается от корня меню, а не от корня инфоблока: `depth=1` — только
 * верхний уровень, `depth=2` — он и его дети.
 */
class Menu extends Component
{
    /**
     * @param  string|null  $code  Код меню из админки
     * @param  string|null  $iblock  Символьный код инфоблока, если меню строится из него
     * @param  string  $template  Имя шаблона вёрстки
     * @param  int  $depth  Максимальная глубина вложенности, начиная с единицы
     * @param  string|null  $root  Код раздела, от которого строится меню
     * @param  bool|null  $sections  Включать разделы; по умолчанию — если они есть
     * @param  bool|null  $elements  Включать элементы; по умолчанию — если разделов нет
     * @param  bool  $activeOnly  Прятать неопубликованное
     */
    public function __construct(
        public ?string $code = null,
        public ?string $iblock = null,
        public string $template = 'default',
        public int $depth = 2,
        public ?string $root = null,
        public ?bool $sections = null,
        public ?bool $elements = null,
        public bool $activeOnly = true,
    ) {}

    public function render(): View
    {
        // Меню из админки уже собрано резолвером — здесь его только рисуют.
        if ($this->code !== null) {
            return $this->template($this->template, [
                'block' => null,
                'items' => app(MenuResolver::class)->tree($this->code),
                'maxDepth' => max(1, $this->depth),
                'currentSection' => '',
            ]);
        }

        if ($this->iblock === null) {
            throw new RuntimeException('Компоненту menu нужен либо code меню, либо iblock.');
        }

        $block = $this->requireIblock($this->iblock);

        return $this->template($this->template, [
            'block' => $block,
            'items' => $this->items($block),
            'maxDepth' => max(1, $this->depth),
            'currentSection' => $this->currentSection($block)?->code ?? '',
        ]);
    }

    protected function component(): string
    {
        return 'menu';
    }

    /**
     * Пункты меню в виде дерева.
     *
     * @return array<int, array<string, mixed>>
     */
    protected function items(Iblock $block): array
    {
        $withSections = $this->sections ?? $block->has_sections;
        $withElements = $this->elements ?? ! $block->has_sections;

        $root = $withSections ? $this->rootSection($block) : null;

        $tree = $withSections ? $this->sectionItems($block, $root) : [];

        if ($withElements) {
            $tree = $this->attachElements($block, $tree, $root);
        }

        return $tree;
    }

    protected function rootSection(Iblock $block): ?IblockSection
    {
        if (! $this->root) {
            return null;
        }

        return $this->iblocks()->getSectionByCode($this->iblock, $this->root);
    }

    /**
     * Разделы, обрезанные по глубине.
     *
     * @return array<int, array<string, mixed>>
     */
    protected function sectionItems(Iblock $block, ?IblockSection $root): array
    {
        $base = $root?->depth ?? -1;
        $current = $this->currentSection($block)?->id;
        $nodes = [];
        $tree = [];

        foreach ($this->iblocks()->getSections($this->iblock, $this->activeOnly) as $section) {
            // Ветка чужого корня в меню не попадает.
            if ($root && ! $this->inside($section, $root)) {
                continue;
            }

            $level = $section->depth - $base;

            if ($level > max(1, $this->depth)) {
                continue;
            }

            $nodes[$section->id] = [
                'id' => $section->id,
                'parent_id' => $section->parent_id,
                'name' => $section->name,
                'code' => $section->code,
                'url' => $section->url(),
                'level' => $level,
                'kind' => 'section',
                // Форма пункта одна на оба источника, чтобы шаблон был один.
                'target' => null,
                'class' => null,
                'active' => $current === $section->id,
                'open' => false,
                'children' => [],
            ];
        }

        foreach ($nodes as &$node) {
            $parent = $node['parent_id'];

            if ($parent && isset($nodes[$parent])) {
                $nodes[$parent]['children'][] = &$node;
            } else {
                $tree[] = &$node;
            }
        }

        unset($node);

        return $tree;
    }

    protected function inside(IblockSection $section, IblockSection $root): bool
    {
        return $section->id === $root->id || str_starts_with($section->path, $root->path.$root->id.'/');
    }

    /**
     * Элементы прикрепляются к своим разделам, а бесхозные — к корню меню.
     *
     * @param  array<int, array<string, mixed>>  $tree
     * @return array<int, array<string, mixed>>
     */
    protected function attachElements(Iblock $block, array $tree, ?IblockSection $root): array
    {
        $filter = $this->activeOnly ? ['is_active' => true] : [];

        if ($root) {
            $filter['section_id'] = [$root->id, ...$this->iblocks()->getDescendantSectionIds($root)];
        }

        $elements = $this->iblocks()->getElements($this->iblock, $filter);

        foreach ($elements as $element) {
            $item = [
                'id' => $element->id,
                'name' => $element->name,
                'code' => $element->code,
                'url' => $element->url(),
                'kind' => 'element',
                'target' => null,
                'class' => null,
                'active' => url()->current() === $element->url(),
                'open' => false,
                'children' => [],
            ];

            $this->place($tree, $item, $element);
        }

        return $tree;
    }

    /**
     * @param  array<int, array<string, mixed>>  $tree
     */
    protected function place(array &$tree, array $item, IblockElement $element): void
    {
        if ($element->section_id) {
            foreach ($tree as &$node) {
                if ($this->pushInto($node, $item, $element->section_id)) {
                    return;
                }
            }

            unset($node);
        }

        $item['level'] = 1;
        $tree[] = $item;
    }

    /**
     * @param  array<string, mixed>  $node
     * @param  array<string, mixed>  $item
     */
    protected function pushInto(array &$node, array $item, int $sectionId): bool
    {
        if (($node['kind'] ?? '') === 'section' && $node['id'] === $sectionId) {
            if ($node['level'] >= max(1, $this->depth)) {
                return true; // Раздел на последнем уровне — глубже не идём.
            }

            $item['level'] = $node['level'] + 1;
            $node['children'][] = $item;

            return true;
        }

        foreach ($node['children'] as &$child) {
            if ($this->pushInto($child, $item, $sectionId)) {
                return true;
            }
        }

        unset($child);

        return false;
    }
}
