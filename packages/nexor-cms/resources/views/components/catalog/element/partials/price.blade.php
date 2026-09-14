{{--
    Цена со скидкой и наличие — у товара и у строки предложения.

    Приходит: $catalog (CatalogProduct), $compact (узкая строка списка предложений).
--}}

@php($compact ??= false)

@if ($catalog->hasPrice())
    <div @class(['flex flex-wrap items-baseline', 'gap-3' => ! $compact, 'mt-1 gap-2' => $compact])>
        <span @class(['font-semibold text-slate-900', 'text-3xl' => ! $compact, 'text-lg' => $compact])>
            {{ $catalog->withCurrency($catalog->finalPrice()) }}
        </span>

        @if ($catalog->hasDiscount())
            <span @class(['text-slate-400 line-through', 'text-lg' => ! $compact, 'text-sm' => $compact])>
                {{ $catalog->withCurrency((float) $catalog->price) }}
            </span>
            <span class="rounded-full bg-red-50 px-2 py-0.5 text-sm font-medium text-red-600">
                −{{ \Nexor\Cms\Models\CatalogProduct::formatPrice((float) $catalog->discount_percent) }}%
            </span>
        @endif
    </div>
@endif

<p @class(['text-sm', 'mt-2' => ! $compact, 'mt-1' => $compact, 'text-green-700' => $catalog->isAvailable(), 'text-slate-500' => ! $catalog->isAvailable()])>
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
