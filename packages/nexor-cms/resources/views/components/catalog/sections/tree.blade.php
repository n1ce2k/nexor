{{--
    Шаблон компонента catalog.sections: дерево целиком, для боковой колонки.

    Вложенность рисуется рекурсивным подключением этого же файла.
--}}

@php
    // Файл подключает сам себя, поэтому уровень приходит извне или считается нулевым.
    $level = $level ?? 0;
@endphp

@if ($tree)
    <ul @class(['space-y-1', 'mt-1 ml-4 border-l border-slate-200 pl-3' => $level > 0])>
        @foreach ($tree as $node)
            <li>
                <a href="{{ $baseUrl }}?section={{ $node['code'] }}"
                   class="block rounded-lg px-2 py-1.5 text-sm transition {{ $current === $node['code'] ? 'bg-brand-50 font-medium text-brand-700' : 'text-slate-600 hover:bg-slate-50 hover:text-slate-900' }}">
                    {{ $node['name'] }}
                </a>

                @if ($node['children'])
                    @include('nexor::components.catalog.sections.tree', [
                        'tree' => $node['children'],
                        'level' => $level + 1,
                    ])
                @endif
            </li>
        @endforeach
    </ul>
@endif
