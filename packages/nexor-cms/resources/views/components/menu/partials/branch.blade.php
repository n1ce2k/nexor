{{-- Вложенные пункты меню; файл подключает сам себя. --}}

@foreach ($items as $item)
    <a href="{{ $item['url'] }}"
       class="block rounded-lg px-3 py-1.5 text-sm transition {{ $item['active'] ? 'font-medium text-brand-600' : 'text-slate-600 hover:bg-slate-50 hover:text-slate-900' }}">
        {{ $item['name'] }}
    </a>

    @if ($item['children'])
        <div class="ml-3 border-l border-slate-100 pl-2">
            @include('nexor::components.menu.partials.branch', ['items' => $item['children']])
        </div>
    @endif
@endforeach
