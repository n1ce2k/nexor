{{--
    Кнопка «В корзину» с количеством.

    Приходит: $mode (button|counter), $step (шаг количества), $available (сколько
    ещё можно добавить, null — без предела), $inCart (сколько уже в корзине),
    $cartUrl, $feedback (button|toast).

    Режим button (по умолчанию): «− 1 +» и кнопка. Количество меняется в браузере
    без запросов и уходит в корзину нажатием кнопки — $wire.add(qty).

    Режим counter: <livewire:nexor-shop::add-to-cart :element-id="…" mode="counter" />
    Пока товара нет в корзине — кнопка; после — счётчик, который меняет количество
    прямо в корзине (wire:click="change(±1)"), а на нуле убирает товар.

    Если корзина при добавлении не выезжает, компонент бросает браузерное событие
    `cart-added` c {elementId, name} — на него отвечает кнопка или всплывашка.

    Свой шаблон: php artisan nexor-shop:component add-to-cart
--}}

@php($formatQuantity = fn (float $value): string => rtrim(rtrim(number_format($value, 3, '.', ''), '0'), '.'))

<div class="mt-4"
     x-data="{
         added: false, name: '', timer: null,
         qty: {{ $step }},
         {{-- Кратно шагу, не меньше шага и не больше того, что ещё можно добавить. --}}
         clamp(value) {
             const step = $wire.step;
             let next = Math.ceil(Math.round((Number(value) || step) / step * 1e6) / 1e6) * step;
             next = Math.max(next, step);

             if ($wire.available !== null) {
                 const cap = Math.floor(Math.round($wire.available / step * 1e6) / 1e6) * step;
                 next = cap >= step ? Math.min(next, cap) : step;
             }

             return Math.round(next * 1000) / 1000;
         },
         canIncrease() {
             return $wire.available === null || this.qty + $wire.step <= $wire.available + 0.0001;
         },
     }"
     x-effect="qty = clamp(qty)"
     x-on:cart-added.window="if ($event.detail.elementId === {{ $elementId }}) {
         added = true; name = $event.detail.name;
         clearTimeout(timer); timer = setTimeout(() => added = false, {{ $feedback === 'toast' ? 3500 : 2000 }});
     }">
    <div class="flex flex-wrap items-center gap-3">
        @if ($mode === 'counter' && $inCart > 0)
            {{-- Счётчик корзины: минус на последнем шаге убирает товар. --}}
            <div class="inline-flex items-center rounded-xl border border-slate-200" wire:loading.class="opacity-60" wire:target="change">
                <button type="button" wire:click="change(-1)" wire:loading.attr="disabled" wire:target="change"
                        class="px-3 py-2 text-slate-600 transition hover:text-slate-900 disabled:opacity-40"
                        title="{{ $inCart <= $step ? 'Убрать из корзины' : 'Меньше' }}">−</button>
                <span class="min-w-12 text-center text-sm font-medium text-slate-900">{{ $formatQuantity($inCart) }}</span>
                <button type="button" wire:click="change(1)" wire:loading.attr="disabled" wire:target="change"
                        class="px-3 py-2 text-slate-600 transition hover:text-slate-900 disabled:opacity-40"
                        @disabled($available !== null && $available < $step - 0.0001)
                        title="Больше">+</button>
            </div>

            <a href="{{ $cartUrl }}" class="text-sm text-slate-600 hover:text-brand-600">В корзине</a>
        @else
            @if ($mode === 'button')
                {{-- Сколько положить: считается в браузере, в корзину уходит кнопкой. --}}
                <div class="inline-flex items-center rounded-xl border border-slate-200">
                    <button type="button" x-on:click="qty = clamp(qty - $wire.step)" :disabled="qty <= $wire.step"
                            class="px-3 py-2 text-slate-600 transition hover:text-slate-900 disabled:opacity-40"
                            aria-label="Меньше">−</button>
                    <input type="number" inputmode="decimal" x-model.number="qty" x-on:change="qty = clamp(qty)"
                           :step="$wire.step" :min="$wire.step" aria-label="Количество"
                           class="w-14 [appearance:textfield] border-0 bg-transparent p-0 text-center text-sm font-medium text-slate-900 focus:ring-0 [&::-webkit-inner-spin-button]:appearance-none [&::-webkit-outer-spin-button]:appearance-none">
                    <button type="button" x-on:click="qty = clamp(qty + $wire.step)" :disabled="! canIncrease()"
                            class="px-3 py-2 text-slate-600 transition hover:text-slate-900 disabled:opacity-40"
                            aria-label="Больше">+</button>
                </div>
            @endif

            <button type="button"
                    @if ($mode === 'button')
                        x-on:click="$wire.add(qty)"
                    @else
                        wire:click="add"
                    @endif
                    wire:loading.attr="disabled" wire:target="add"
                    @if ($feedback === 'button')
                        :class="added ? 'bg-green-600 hover:bg-green-700' : 'bg-brand-600 hover:bg-brand-700'"
                    @else
                        :class="'bg-brand-600 hover:bg-brand-700'"
                    @endif
                    class="inline-flex items-center gap-2 rounded-xl px-5 py-2.5 text-sm font-semibold text-white transition disabled:opacity-60">
                <svg wire:loading.remove wire:target="add" class="size-4" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round">
                    <circle cx="9" cy="21" r="1" /><circle cx="20" cy="21" r="1" />
                    <path d="M1 1h4l2.68 13.39a2 2 0 0 0 2 1.61h9.72a2 2 0 0 0 2-1.61L23 6H6" />
                </svg>
                <svg wire:loading wire:target="add" class="size-4 animate-spin" viewBox="0 0 24 24" fill="none">
                    <circle class="opacity-25" cx="12" cy="12" r="10" stroke="currentColor" stroke-width="3" />
                    <path class="opacity-90" fill="currentColor" d="M4 12a8 8 0 0 1 8-8v3a5 5 0 0 0-5 5H4Z" />
                </svg>

                @if ($feedback === 'button')
                    <span x-show="! added">{{ $label }}</span>
                    <span x-show="added" x-cloak>Добавлено ✓</span>
                @else
                    <span>{{ $label }}</span>
                @endif
            </button>

            @if ($inCart > 0)
                <a href="{{ $cartUrl }}" class="text-sm text-slate-600 hover:text-brand-600">
                    В корзине: {{ $formatQuantity($inCart) }}
                </a>
            @endif
        @endif
    </div>

    @if ($error)
        <p class="mt-2 text-sm text-red-600">{{ $error }}</p>
    @endif

    @if ($feedback === 'toast')
        <div x-show="added" x-cloak x-transition.opacity
             class="fixed right-4 bottom-4 z-[90] flex max-w-sm items-start gap-3 rounded-2xl bg-slate-900 px-4 py-3 text-sm text-white shadow-2xl">
            <svg class="mt-0.5 size-5 shrink-0 text-green-400" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2.5" stroke-linecap="round">
                <path d="M20 6 9 17l-5-5" />
            </svg>
            <div class="min-w-0">
                <p>«<span x-text="name"></span>» добавлен в корзину</p>
                <a href="{{ $cartUrl }}" class="mt-1 inline-block font-semibold text-white underline underline-offset-2">Перейти в корзину</a>
            </div>
            <button type="button" @click="added = false" class="ml-1 text-slate-400 hover:text-white">×</button>
        </div>
    @endif
    @include('nexor-shop::partials.tab-sync')
</div>
