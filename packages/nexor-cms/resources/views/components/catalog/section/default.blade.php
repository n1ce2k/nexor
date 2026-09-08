{{--
    Шаблон компонента catalog.section: сетка карточек и пагинация.

    Приходит: $elements (страница элементов), $current (раздел), $block
    (инфоблок), $cardView (вьюха карточки).

    Свой шаблон: php artisan nexor:component catalog.section
--}}

{{-- id обязателен: в этот контейнер кнопка «Показать ещё» дописывает порцию. --}}
<div id="nexor-items" class="grid gap-6 sm:grid-cols-2 lg:grid-cols-3">
    @forelse ($elements as $element)
        @include($cardView, ['element' => $element])
    @empty
        <p class="text-slate-500 sm:col-span-2 lg:col-span-3">Ничего не найдено.</p>
    @endforelse
</div>

@if ($elements instanceof \Illuminate\Contracts\Pagination\LengthAwarePaginator)
    <x-nexor::pagination :paginator="$elements" :iblock="$block" />
@endif
