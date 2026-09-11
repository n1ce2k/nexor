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

    {{-- Цена и наличие — у элементов торгового каталога. --}}
    @if ($catalog = $element->catalog)
        <div class="mb-8 rounded-2xl border border-slate-200 p-5">
            @if ($catalog->hasPrice())
                <div class="flex flex-wrap items-baseline gap-3">
                    <span class="text-3xl font-semibold text-slate-900">
                        {{ \Nexor\Cms\Models\CatalogProduct::formatPrice($catalog->finalPrice()) }} ₽
                    </span>

                    @if ($catalog->hasDiscount())
                        <span class="text-lg text-slate-400 line-through">
                            {{ \Nexor\Cms\Models\CatalogProduct::formatPrice((float) $catalog->price) }} ₽
                        </span>
                        <span class="rounded-full bg-red-50 px-2 py-0.5 text-sm font-medium text-red-600">
                            −{{ \Nexor\Cms\Models\CatalogProduct::formatPrice((float) $catalog->discount_percent) }}%
                        </span>
                    @endif
                </div>
            @endif

            <p class="mt-2 text-sm {{ $catalog->isAvailable() ? 'text-green-700' : 'text-slate-500' }}">
                @if (! $catalog->isAvailable())
                    Нет в наличии
                @elseif ($catalog->quantity_trace && (float) $catalog->quantity > 0)
                    В наличии: {{ \Nexor\Cms\Models\CatalogProduct::formatPrice((float) $catalog->quantity) }} {{ $catalog->measure }}
                @elseif ($catalog->quantity_trace)
                    Под заказ
                @else
                    В наличии
                @endif
            </p>
        </div>
    @endif

    {{-- Торговые предложения товара. --}}
    @php($offers = $element->offerElements())

    @if ($offers->isNotEmpty())
        <div class="mb-8">
            <h2 class="mb-3 text-lg font-semibold text-slate-900">Варианты</h2>

            <ul class="divide-y divide-slate-100 rounded-2xl border border-slate-200">
                @foreach ($offers as $offer)
                    <li class="flex items-center justify-between gap-4 px-5 py-3">
                        <span class="text-slate-800">{{ $offer->name }}</span>

                        <span class="flex items-baseline gap-2">
                            @if ($offer->catalog?->hasPrice())
                                <span class="font-semibold text-slate-900">
                                    {{ \Nexor\Cms\Models\CatalogProduct::formatPrice($offer->catalog->finalPrice()) }} ₽
                                </span>
                            @endif

                            @if ($offer->catalog && ! $offer->catalog->isAvailable())
                                <span class="text-xs text-slate-400">нет в наличии</span>
                            @endif
                        </span>
                    </li>
                @endforeach
            </ul>
        </div>
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
