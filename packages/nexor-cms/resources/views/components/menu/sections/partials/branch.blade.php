{{-- Ветка дерева разделов; файл подключает сам себя. --}}

<ul @class(['space-y-1', 'mt-1 ml-3 border-l border-slate-200 pl-3' => $level > 0])>
    @foreach ($items as $item)
        <li>
            <a href="{{ $item['url'] }}"
               class="block rounded-lg px-2 py-1.5 text-sm transition {{ $item['active'] ? 'bg-brand-50 font-medium text-brand-700' : 'text-slate-600 hover:bg-slate-50 hover:text-slate-900' }}">
                {{ $item['name'] }}
            </a>

            @if ($item['children'])
                @include('nexor::components.menu.sections.partials.branch', [
                    'items' => $item['children'],
                    'level' => $level + 1,
                ])
            @endif
        </li>
    @endforeach
</ul>
