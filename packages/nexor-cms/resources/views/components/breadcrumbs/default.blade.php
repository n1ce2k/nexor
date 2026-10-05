{{--
    Шаблон компонента breadcrumbs: Главная / Каталог / Раздел / Элемент.

    Приходит: $crumbs — у каждого шага name и url. Адреса может не быть: у
    крошки, заданной страницей через @breadcrumb('Название'). Классы,
    переданные компоненту, ложатся на <nav>: так в макете ему задают ширину и
    отступы, не оборачивая в лишний блок.
--}}

@if (count($crumbs) > 1)
    <nav {{ $attributes->class('mb-6 flex flex-wrap items-center gap-1.5 text-xs text-slate-500') }} aria-label="Хлебные крошки">
        @foreach ($crumbs as $crumb)
            @if ($loop->last || empty($crumb['url']))
                <span @class(['text-slate-700' => $loop->last])>{{ $crumb['name'] }}</span>
            @else
                <a href="{{ $crumb['url'] }}" class="transition hover:text-brand-600">{{ $crumb['name'] }}</a>
            @endif

            @unless ($loop->last)
                <span aria-hidden="true">/</span>
            @endunless
        @endforeach
    </nav>
@endif
