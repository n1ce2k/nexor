{{-- Приходит: $summary, $deliveryPrice (необязательно), $grandTotal (необязательно). --}}

@php($shop = \Nexor\Shop\Support\Shop::class)

<dl class="space-y-2 text-sm">
    <div class="flex justify-between gap-4">
        <dt class="text-slate-500">Товары</dt>
        <dd class="text-slate-900">{{ $shop::format($summary->subtotal) }}</dd>
    </div>

    @if ($summary->discount > 0)
        <div class="flex justify-between gap-4">
            <dt class="text-slate-500">Скидка по промокоду {{ $summary->promocode?->code }}</dt>
            <dd class="text-green-700">−{{ $shop::format($summary->discount) }}</dd>
        </div>
    @endif

    @isset($deliveryPrice)
        <div class="flex justify-between gap-4">
            <dt class="text-slate-500">Доставка</dt>
            <dd class="text-slate-900">{{ $deliveryPrice > 0 ? $shop::format($deliveryPrice) : 'бесплатно' }}</dd>
        </div>
    @endisset

    <div class="flex justify-between gap-4 border-t border-slate-200 pt-3 text-base">
        <dt class="font-semibold text-slate-900">Итого</dt>
        <dd class="font-semibold text-slate-900">{{ $shop::format($grandTotal ?? $summary->total()) }}</dd>
    </div>
</dl>
