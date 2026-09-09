{{--
    Шаблон компонента menu: горизонтальное меню с выпадающими подпунктами.

    Приходит: $items (дерево), $maxDepth, $block (только в режиме iblock).
    У пункта: name, url, active, open, children, level, kind, target, class.

    Свой шаблон: php artisan nexor:component menu my_menu
--}}

@if ($items)
    <nav class="flex flex-wrap items-center gap-1" aria-label="Меню">
        @foreach ($items as $item)
            @if ($item['kind'] === 'divider')
                <span class="mx-1 h-4 w-px bg-slate-200"></span>
            @elseif ($item['kind'] === 'heading')
                <span class="px-3 py-2 text-sm font-semibold text-slate-400">{{ $item['name'] }}</span>
            @else
                <div class="group relative">
                    <a href="{{ $item['url'] }}"
                       @if ($item['target']) target="{{ $item['target'] }}" rel="noopener" @endif
                       @class([
                           'block rounded-lg px-3 py-2 text-sm transition',
                           'font-medium text-brand-600' => $item['active'] || $item['open'],
                           'text-slate-600 hover:text-slate-900' => ! ($item['active'] || $item['open']),
                           $item['class'] => $item['class'] ?? false,
                       ])>
                        {{ $item['name'] }}
                    </a>

                    @if ($item['children'])
                        <div class="absolute top-full left-0 z-20 hidden min-w-48 rounded-xl border border-slate-200 bg-white p-1.5 shadow-lg group-hover:block">
                            @include('nexor::components.menu.partials.branch', ['items' => $item['children']])
                        </div>
                    @endif
                </div>
            @endif
        @endforeach
    </nav>
@endif
