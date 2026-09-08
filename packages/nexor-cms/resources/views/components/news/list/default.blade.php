{{--
    Шаблон компонента news.list: лента.

    Приходит: $elements, $current (раздел), $block, $cardView.

    Свой шаблон: php artisan nexor:component news.list my_template
--}}

<div id="nexor-items" class="divide-y divide-slate-100">
    @forelse ($elements as $element)
        @include($cardView, ['element' => $element])
    @empty
        <p class="py-6 text-slate-500">Новостей пока нет.</p>
    @endforelse
</div>

@if ($elements instanceof \Illuminate\Contracts\Pagination\LengthAwarePaginator)
    <x-nexor::pagination :paginator="$elements" :iblock="$block" />
@endif
