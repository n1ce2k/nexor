<?php

namespace Nexor\Cms\View\Components\Catalog;

use Illuminate\View\View;
use Nexor\Cms\Models\IblockSection;
use Nexor\Cms\View\Components\Component;

/**
 * Плитка подразделов — аналог `catalog.section.list`.
 *
 * ```blade
 * <x-nexor::catalog.section-list iblock="katalog" />              разделы верхнего уровня
 * <x-nexor::catalog.section-list iblock="katalog" root="mebel" /> подразделы «Мебели»
 * ```
 *
 * От меню отличается назначением: меню — это навигация, а здесь карточки с
 * картинкой, описанием и счётчиком, которыми открывают раздел каталога.
 *
 * Имя тега через дефис, а не `catalog.section.list`: Blade превратил бы точки в
 * класс `Catalog\Section\List`, а `list` в PHP — зарезервированное слово.
 */
class SectionList extends Component
{
    /**
     * @param  string  $iblock  Символьный код инфоблока
     * @param  string  $template  Имя шаблона вёрстки
     * @param  string|null  $root  Код родительского раздела; по умолчанию из `?section=`
     * @param  bool  $count  Показывать число элементов
     * @param  bool  $recursive  Считать элементы вложенных разделов тоже
     */
    public function __construct(
        public string $iblock,
        public string $template = 'default',
        public ?string $root = null,
        public bool $count = true,
        public bool $recursive = true,
    ) {}

    public function render(): View
    {
        $block = $this->requireIblock($this->iblock);
        $root = $this->root();

        return $this->template($this->template, [
            'block' => $block,
            'parent' => $root,
            'sections' => $this->children($root),
            'showCount' => $this->count,
        ]);
    }

    protected function component(): string
    {
        return 'catalog.section-list';
    }

    protected function root(): ?IblockSection
    {
        $code = $this->root ?? request()->query('section');

        if (! is_string($code) || $code === '' || $code === 'none') {
            return null;
        }

        return $this->iblocks()->getSectionByCode($this->iblock, $code);
    }

    /**
     * Прямые потомки раздела — или разделы верхнего уровня, если корня нет.
     *
     * @return array<int, array<string, mixed>>
     */
    protected function children(?IblockSection $root): array
    {
        $depth = $root ? $root->depth + 1 : 0;

        return $this->iblocks()
            ->getSections($this->iblock)
            ->filter(fn (IblockSection $section) => $section->depth === $depth
                && $section->parent_id === $root?->id)
            ->map(fn (IblockSection $section) => [
                'section' => $section,
                'name' => $section->name,
                'code' => $section->code,
                'picture' => $section->picture_url,
                'description' => $section->description,
                'url' => url('/'.$this->iblock.'?section='.$section->code),
                'count' => $this->count ? $this->countIn($section) : null,
            ])
            ->values()
            ->all();
    }

    protected function countIn(IblockSection $section): int
    {
        $ids = $this->recursive
            ? [$section->id, ...$this->iblocks()->getDescendantSectionIds($section)]
            : [$section->id];

        return $this->iblocks()->countElements($this->iblock, [
            'is_active' => true,
            'section_id' => $ids,
        ]);
    }
}
