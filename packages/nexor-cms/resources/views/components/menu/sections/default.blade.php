{{--
    Шаблон компонента menu.sections: боковое дерево разделов.

    Приходит: $items (дерево разделов), $maxDepth, $block.
    Глубина уже обрезана компонентом — здесь только вёрстка.

    Свой шаблон: php artisan nexor:component menu.sections my_menu
--}}

@if ($items)
    @include('nexor::components.menu.sections.partials.branch', ['items' => $items, 'level' => 0])
@endif
