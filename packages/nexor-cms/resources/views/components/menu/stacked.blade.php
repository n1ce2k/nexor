{{--
    Шаблон компонента menu: вертикальный список.

    Для мобильного меню, подвала и боковой колонки — везде, где пункты идут
    столбиком, а вложенность рисуется отступом.
--}}

@if ($items)
    <nav class="flex flex-col gap-0.5" aria-label="Меню">
        @include('nexor::components.menu.partials.stack', ['items' => $items, 'level' => 0])
    </nav>
@endif
