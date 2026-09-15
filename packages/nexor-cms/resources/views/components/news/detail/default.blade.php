{{--
    Шаблон компонента news.detail.

    Приходит: $element, $block, $values, $showProperties.

    Свой шаблон: php artisan nexor:component news.detail my_template
--}}

<article>
    <header class="mb-6">
        <time datetime="{{ $element->created_at?->toDateString() }}" class="text-sm text-slate-400">
            {{ $element->created_at?->format('d.m.Y') }}
        </time>

        <h1 class="mt-2 text-3xl font-semibold tracking-tight text-slate-900 sm:text-4xl">
            {{ $element->name }}
        </h1>
    </header>

    @if ($element->detail_picture_url)
        <img src="{{ $element->detail_picture_url }}" alt="{{ $element->name }}"
             class="mb-8 w-full rounded-2xl object-cover">
    @endif

    @if ($element->preview_text)
        <p class="mb-6 text-lg text-slate-600">{{ $element->preview_text }}</p>
    @endif

    {{-- Конструктор страниц (модуль pagebuilder): если у инфоблока он включён и
         блоки заполнены — они выводятся вместо подробного текста. --}}
    @feature('pagebuilder')
        @php($pageBuilder = \Nexor\PageBuilder\PageBuilder::render($element)->toHtml())
    @endfeature

    @if (! empty($pageBuilder))
        {!! $pageBuilder !!}
    @else
        <div class="space-y-4 leading-relaxed text-slate-700">
            @if ($element->detail_text_type === 'html')
                {!! $element->detail_text !!}
            @else
                {!! nl2br(e($element->detail_text)) !!}
            @endif
        </div>
    @endif
</article>
