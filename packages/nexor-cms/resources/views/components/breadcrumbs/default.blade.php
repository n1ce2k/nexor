{{-- Шаблон компонента breadcrumbs: Главная / Каталог / Раздел / Элемент. --}}

@if (count($crumbs) > 1)
    <nav class="mb-6 flex flex-wrap items-center gap-1.5 text-xs text-slate-500" aria-label="Хлебные крошки">
        @foreach ($crumbs as $crumb)
            @if ($loop->last)
                <span class="text-slate-700">{{ $crumb['name'] }}</span>
            @else
                <a href="{{ $crumb['url'] }}" class="transition hover:text-brand-600">{{ $crumb['name'] }}</a>
                <span aria-hidden="true">/</span>
            @endif
        @endforeach
    </nav>
@endif
