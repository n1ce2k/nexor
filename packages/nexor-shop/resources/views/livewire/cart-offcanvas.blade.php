{{--
    Выезжающая корзина. Открывается браузерным событием `cart-open`.

    Приходит: $summary, $fields, $ultimate, $checkout, $enabled, $cartUrl, $checkoutUrl.
--}}

<div x-data="{ open: false }"
     @if ($enabled) x-on:cart-open.window="open = true" @endif
     x-on:keydown.escape.window="open = false">
    <div x-show="open" x-cloak x-transition.opacity class="fixed inset-0 z-[70] bg-slate-900/40" @click="open = false"></div>

    <aside x-show="open" x-cloak
           x-transition:enter="transition duration-200 ease-out" x-transition:enter-start="translate-x-full"
           x-transition:leave="transition duration-150 ease-in" x-transition:leave-end="translate-x-full"
           class="fixed inset-y-0 right-0 z-[80] flex w-full max-w-md flex-col bg-white shadow-2xl">
        <header class="flex items-center justify-between border-b border-slate-200 px-5 py-4">
            <p class="text-lg font-semibold text-slate-900">Корзина</p>

            <button type="button" @click="open = false" class="rounded-lg p-1.5 text-slate-500 transition hover:bg-slate-100">
                <svg class="size-5" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round">
                    <path d="M18 6 6 18M6 6l12 12" />
                </svg>
            </button>
        </header>

        <div class="flex-1 overflow-y-auto px-5">
            @if ($placedNumber)
                <div class="py-6">@include('nexor-shop::partials.success', ['number' => $placedNumber])</div>
            @elseif ($summary->isEmpty())
                <p class="py-10 text-center text-slate-500">В корзине пока ничего нет.</p>
            @else
                @include('nexor-shop::partials.lines', ['summary' => $summary, 'compact' => true])

                @error('cart')
                    <p class="mt-3 text-sm text-red-600">{{ $message }}</p>
                @enderror

                @unless ($ultimate)
                    <div class="border-t border-slate-200 py-5">
                        <p class="mb-4 font-semibold text-slate-900">Оформление заказа</p>

                        @include('nexor-shop::partials.order-form', ['fields' => $fields])
                    </div>
                @endunless
            @endif
        </div>

        @if (! $placedNumber && ! $summary->isEmpty())
            <footer class="space-y-4 border-t border-slate-200 px-5 py-4">
                @include('nexor-shop::partials.totals', ['summary' => $summary])

                @if ($ultimate)
                    <div class="grid gap-2">
                        @if ($checkout)
                            <a href="{{ $checkoutUrl }}"
                               class="rounded-xl bg-brand-600 px-5 py-3 text-center text-sm font-semibold text-white transition hover:bg-brand-700">
                                Перейти к оформлению
                            </a>
                        @endif
                        <a href="{{ $cartUrl }}"
                           class="rounded-xl border border-slate-300 px-5 py-3 text-center text-sm font-semibold text-slate-700 transition hover:bg-slate-50">
                            Открыть корзину и промокоды
                        </a>
                    </div>
                @else
                    <button type="button" wire:click="placeOrder" wire:loading.attr="disabled"
                            @disabled($summary->hasProblems())
                            class="w-full rounded-xl bg-brand-600 px-5 py-3 text-sm font-semibold text-white transition hover:bg-brand-700 disabled:opacity-60">
                        Оформить заказ
                    </button>
                @endif
            </footer>
        @endif
    </aside>
    @include('nexor-shop::partials.tab-sync')
</div>
