{{--
    Шаблон компонента catalog.sections: разделы верхнего уровня чипсами.

    Приходит: $tree (дерево), $current (код текущего), $baseUrl, $block.
--}}

@if ($tree)
    <div class="mb-8 flex flex-wrap gap-2">
        <a href="{{ $baseUrl }}"
           class="rounded-full border px-4 py-1.5 text-sm transition {{ $current === '' ? 'border-brand-600 bg-brand-50 text-brand-700' : 'border-slate-200 text-slate-600 hover:border-brand-300' }}">
            Все
        </a>

        @foreach ($tree as $node)
            <a href="{{ $baseUrl }}?section={{ $node['code'] }}"
               class="rounded-full border px-4 py-1.5 text-sm transition {{ $current === $node['code'] ? 'border-brand-600 bg-brand-50 text-brand-700' : 'border-slate-200 text-slate-600 hover:border-brand-300' }}">
                {{ $node['name'] }}
            </a>
        @endforeach
    </div>
@endif
