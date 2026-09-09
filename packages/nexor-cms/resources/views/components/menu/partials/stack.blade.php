{{-- Ветка вертикального меню; файл подключает сам себя. --}}

@foreach ($items as $item)
    @if ($item['kind'] === 'divider')
        <hr class="my-2 border-slate-200">
    @elseif ($item['kind'] === 'heading')
        <p @class(['mt-3 px-3 text-xs font-semibold tracking-wide text-slate-400 uppercase', 'ml-'.($level * 3) => $level > 0])>
            {{ $item['name'] }}
        </p>
    @else
        <a href="{{ $item['url'] }}" @if ($item['target']) target="{{ $item['target'] }}" rel="noopener" @endif
           @class([
               'rounded-lg px-3 py-2 text-sm transition',
               'font-medium text-brand-600' => $item['active'],
               'text-slate-700 hover:bg-slate-50' => ! $item['active'],
               $item['class'] => $item['class'],
           ])
           @style(['margin-left: '.($level * 0.75).'rem' => $level > 0])>
            {{ $item['name'] }}
        </a>
    @endif

    @if ($item['children'])
        @include('nexor::components.menu.partials.stack', ['items' => $item['children'], 'level' => $level + 1])
    @endif
@endforeach
