{{-- Вложенные пункты меню; файл подключает сам себя. --}}

@foreach ($items as $item)
    @if ($item['kind'] === 'divider')
        <hr class="my-1 border-slate-200">
    @elseif ($item['kind'] === 'heading')
        <p class="px-3 py-1.5 text-xs font-semibold tracking-wide text-slate-400 uppercase">{{ $item['name'] }}</p>
    @else
        <a href="{{ $item['url'] }}"
           @if ($item['target']) target="{{ $item['target'] }}" rel="noopener" @endif
           @class([
               'block rounded-lg px-3 py-1.5 text-sm transition',
               'font-medium text-brand-600' => $item['active'] || $item['open'],
               'text-slate-600 hover:bg-slate-50 hover:text-slate-900' => ! ($item['active'] || $item['open']),
               $item['class'] => $item['class'] ?? false,
           ])>
            {{ $item['name'] }}
        </a>
    @endif

    @if ($item['children'])
        <div class="ml-3 border-l border-slate-100 pl-2">
            @include('nexor::components.menu.partials.branch', ['items' => $item['children']])
        </div>
    @endif
@endforeach
