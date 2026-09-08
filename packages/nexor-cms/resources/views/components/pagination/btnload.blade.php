{{--
    Шаблон компонента pagination: только кнопка «Показать ещё».

    Номеров страниц нет — следующая порция подгружается кнопкой.
--}}

@if ($paginator?->hasMorePages())
    @include('nexor::components.pagination.partials.load-more', ['paginator' => $paginator])
@endif
