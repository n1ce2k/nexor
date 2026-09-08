{{--
    Шаблон компонента catalog.section: плитка пошире, четыре в ряд.
--}}

<div id="nexor-items" class="grid gap-4 sm:grid-cols-2 lg:grid-cols-4">
    @forelse ($elements as $element)
        @include($cardView, ['element' => $element])
    @empty
        <p class="text-slate-500 sm:col-span-2 lg:col-span-4">Ничего не найдено.</p>
    @endforelse
</div>

@if ($elements instanceof \Illuminate\Contracts\Pagination\LengthAwarePaginator)
    <x-nexor::pagination :paginator="$elements" :iblock="$block" />
@endif
