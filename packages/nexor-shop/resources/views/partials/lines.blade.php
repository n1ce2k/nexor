{{--
    Строки корзины с количеством. Подключается внутри Livewire-компонента:
    wire:click уходит в него.

    Приходит: $summary, $compact (узкая панель).
--}}

@php($compact ??= false)

<ul class="divide-y divide-slate-100">
    @foreach ($summary->lines as $line)
        <li class="flex gap-4 py-4" wire:key="line-{{ $line->id }}">
            @if ($line->image)
                <img src="{{ $line->image }}" alt="{{ $line->name }}"
                     class="{{ $compact ? 'size-16' : 'size-20' }} shrink-0 rounded-xl object-cover">
            @endif

            <div class="min-w-0 flex-1">
                <div class="flex items-start justify-between gap-3">
                    <a href="{{ $line->url }}" class="font-medium text-slate-900 hover:text-brand-600">{{ $line->name }}</a>

                    <button type="button" wire:click="remove({{ $line->id }})" title="Убрать из корзины"
                            class="shrink-0 rounded-lg p-1 text-slate-400 transition hover:bg-slate-100 hover:text-red-600">
                        <svg class="size-4" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round">
                            <path d="M18 6 6 18M6 6l12 12" />
                        </svg>
                    </button>
                </div>

                @if ($line->hasProblem())
                    <p class="mt-1 text-sm text-red-600">{{ $line->problem }}</p>
                @else
                    <p class="mt-1 text-sm text-slate-500">
                        @if ($line->converted)
                            <span title="Цена товара сконвертирована по курсу: {{ \Nexor\Cms\Models\CatalogProduct::formatPrice($line->originalPrice) }} {{ $line->originalCurrency->symbol() }}"
                                  class="cursor-help font-medium text-amber-600">≈</span>
                        @endif
                        {{ \Nexor\Shop\Support\Shop::format($line->unitPrice) }} за {{ $line->measure }}

                        @if ($line->hasDiscount())
                            <span class="ml-1 text-slate-400 line-through">{{ \Nexor\Shop\Support\Shop::format($line->basePrice) }}</span>
                        @endif
                    </p>
                @endif

                <div class="mt-3 flex flex-wrap items-center justify-between gap-3">
                    <div class="inline-flex items-center rounded-xl border border-slate-200">
                        <button type="button" wire:click="decrease({{ $line->id }})"
                                class="px-3 py-1.5 text-slate-600 transition hover:text-slate-900 disabled:opacity-40"
                                @disabled($line->quantity <= $line->step)>−</button>
                        <span class="min-w-12 text-center text-sm font-medium text-slate-900">
                            {{ rtrim(rtrim(number_format($line->quantity, 3, '.', ''), '0'), '.') }}
                        </span>
                        <button type="button" wire:click="increase({{ $line->id }})"
                                class="px-3 py-1.5 text-slate-600 transition hover:text-slate-900 disabled:opacity-40"
                                @disabled(! $line->canIncrease() || $line->hasProblem())>+</button>
                    </div>

                    @unless ($line->hasProblem())
                        <div class="text-right">
                            <p class="font-semibold text-slate-900">{{ \Nexor\Shop\Support\Shop::format($line->sum()) }}</p>

                            @if ($line->discount > 0)
                                <p class="text-xs text-green-700">−{{ \Nexor\Shop\Support\Shop::format($line->discount) }} по промокоду</p>
                            @endif

                            @if ($line->max !== null)
                                <p class="text-xs text-slate-400">в наличии {{ rtrim(rtrim(number_format($line->max, 3, '.', ''), '0'), '.') }} {{ $line->measure }}</p>
                            @endif
                        </div>
                    @endunless
                </div>
            </div>
        </li>
    @endforeach
</ul>

@if ($summary->hasConverted())
    <p class="mt-2 text-xs text-slate-500">
        <span class="font-medium text-amber-600">≈</span> — цена товара сконвертирована по курсу магазина.
    </p>
@endif
