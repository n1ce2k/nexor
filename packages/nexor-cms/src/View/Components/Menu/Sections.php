<?php

namespace Nexor\Cms\View\Components\Menu;

use Nexor\Cms\View\Components\Menu;

/**
 * Меню разделов — то же дерево, что и у `menu`, но без элементов.
 *
 * ```blade
 * <x-nexor::menu.sections iblock="katalog" />                 два уровня
 * <x-nexor::menu.sections iblock="katalog" :depth="1" />      только верхний
 * <x-nexor::menu.sections iblock="katalog" root="mebel" />    ветка раздела
 * <x-nexor::menu.sections iblock="katalog" template="chips" />
 * ```
 *
 * Отдельный компонент, потому что у меню разделов своя вёрстка: боковое дерево,
 * чипсы, выпадающий список — а логика ровно та же.
 */
class Sections extends Menu
{
    public function __construct(
        string $iblock,
        string $template = 'default',
        int $depth = 2,
        ?string $root = null,
        bool $activeOnly = true,
    ) {
        parent::__construct(
            iblock: $iblock,
            template: $template,
            depth: $depth,
            root: $root,
            sections: true,
            elements: false,
            activeOnly: $activeOnly,
        );
    }

    protected function component(): string
    {
        return 'menu.sections';
    }
}
