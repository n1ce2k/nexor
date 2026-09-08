{{--
    Шаблон компонента menu: горизонтальное меню с выпадающими подпунктами.

    Приходит: $items (дерево), $maxDepth, $block.
    У пункта: name, url, active, children, level, kind (section|element).

    Свой шаблон: php artisan nexor:component menu my_menu
--}}

@if ($items)
    <nav class="flex flex-wrap items-center gap-1" aria-label="Меню">
        @foreach ($items as $item)
            <div class="group relative">
                <a href="{{ $item['url'] }}"
                   class="block rounded-lg px-3 py-2 text-sm transition {{ $item['active'] ? 'font-medium text-brand-600' : 'text-slate-600 hover:text-slate-900' }}">
                    {{ $item['name'] }}
                </a>

                @if ($item['children'])
                    <div class="absolute top-full left-0 z-20 hidden min-w-48 rounded-xl border border-slate-200 bg-white p-1.5 shadow-lg group-hover:block">
                        @include('nexor::components.menu.partials.branch', ['items' => $item['children']])
                    </div>
                @endif
            </div>
        @endforeach
    </nav>
@endif
