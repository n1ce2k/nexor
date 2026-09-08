{{--
    Шаблон компонента catalog.element: детальная карточка.

    Приходит: $element, $block (инфоблок), $values (значения свойств по кодам),
    $showProperties.

    Свой шаблон: php artisan nexor:component catalog.element
--}}

<article>
    <header class="mb-8">
        <h1 class="text-3xl font-semibold tracking-tight text-slate-900 sm:text-4xl">{{ $element->name }}</h1>
    </header>

    @if ($element->detail_picture_url)
        <img src="{{ $element->detail_picture_url }}" alt="{{ $element->name }}"
             class="mb-8 w-full rounded-2xl object-cover">
    @endif

    @if ($element->preview_text)
        <p class="mb-8 border-l-4 border-brand-200 pl-4 text-lg text-slate-600">{{ $element->preview_text }}</p>
    @endif

    <div class="space-y-4 leading-relaxed text-slate-700">
        @if ($element->detail_text_type === 'html')
            {!! $element->detail_text !!}
        @else
            {!! nl2br(e($element->detail_text)) !!}
        @endif
    </div>

    @if ($showProperties)
        <dl class="mt-8 grid gap-2 text-sm">
            @foreach ($block->properties as $property)
                @continue(! $property->is_active)

                @php($value = $values->get($property->code))
                @continue($value === null || $value === '' || (is_iterable($value) && count($value) === 0))

                <div class="flex justify-between gap-4 border-b border-slate-100 py-2">
                    <dt class="text-slate-500">{{ $property->name }}</dt>
                    <dd class="text-slate-900">{{ $element->displayValue($property) }}</dd>
                </div>
            @endforeach
        </dl>
    @endif
</article>
