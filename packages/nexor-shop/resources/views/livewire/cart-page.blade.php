{{--
    Корзина отдельной страницей.

    Приходит: $summary, $fields, $ultimate, $promocodes, $checkout, $checkoutUrl.
--}}

<div>
    @if ($placedNumber)
        @include('nexor-shop::partials.success', ['number' => $placedNumber, 'paymentUrl' => $paymentUrl])
    @elseif ($summary->isEmpty())
        <div class="rounded-2xl border border-dashed border-slate-300 p-10 text-center">
            <p class="text-lg font-medium text-slate-900">В корзине пока ничего нет</p>
            <a href="{{ url('/') }}" class="mt-3 inline-block text-sm font-medium text-brand-600 hover:underline">Перейти к покупкам</a>
        </div>
    @else
        <div class="grid gap-8 lg:grid-cols-[1fr_22rem]">
            <div>
                <div class="rounded-2xl border border-slate-200 px-5">
                    @include('nexor-shop::partials.lines', ['summary' => $summary])
                </div>

                <div class="mt-3 flex justify-end">
                    <button type="button" wire:click="clear" wire:confirm="Очистить корзину?"
                            class="text-sm text-slate-500 hover:text-red-600">Очистить корзину</button>
                </div>

                @error('cart')
                    <p class="mt-3 text-sm text-red-600">{{ $message }}</p>
                @enderror

                @unless ($ultimate)
                    <div class="mt-8 rounded-2xl border border-slate-200 p-5">
                        <h2 class="mb-4 text-lg font-semibold text-slate-900">Оформление заказа</h2>

                        @include('nexor-shop::partials.order-form', ['fields' => $fields])
                    </div>
                @endunless
            </div>

            <aside class="space-y-5">
                @if ($promocodes)
                    <div class="rounded-2xl border border-slate-200 p-5">
                        <p class="mb-3 font-semibold text-slate-900">Промокод</p>

                        @if ($summary->promocode)
                            <div class="flex items-center justify-between gap-3 rounded-xl bg-green-50 px-3 py-2">
                                <span class="font-mono text-sm font-semibold text-green-800">{{ $summary->promocode->code }}</span>
                                <button type="button" wire:click="removePromocode" class="text-sm text-green-800 hover:underline">убрать</button>
                            </div>
                        @else
                            <form wire:submit="applyPromocode" class="flex gap-2">
                                <input wire:model="promocodeInput" placeholder="Введите код"
                                       class="min-w-0 flex-1 rounded-xl border border-slate-300 px-3 py-2 uppercase focus:border-brand-500 focus:ring-2 focus:ring-brand-500/20 focus:outline-none">
                                <button type="submit" class="rounded-xl border border-slate-300 px-4 py-2 text-sm font-semibold text-slate-700 hover:bg-slate-50">
                                    Применить
                                </button>
                            </form>
                        @endif

                        @error('promocode')
                            <p class="mt-2 text-sm text-red-600">{{ $message }}</p>
                        @enderror

                        @if ($promocodeMessage && $summary->promocode)
                            <p class="mt-2 text-sm text-green-700">{{ $promocodeMessage }}</p>
                        @endif
                    </div>
                @endif

                <div class="space-y-4 rounded-2xl border border-slate-200 p-5">
                    @include('nexor-shop::partials.totals', ['summary' => $summary])

                    @if ($ultimate)
                        @if ($checkout)
                            <a href="{{ $checkoutUrl }}"
                               @class([
                                   'block rounded-xl px-5 py-3 text-center text-sm font-semibold text-white transition',
                                   'bg-brand-600 hover:bg-brand-700' => $summary->canOrder(),
                                   'pointer-events-none bg-slate-300' => ! $summary->canOrder(),
                               ])>
                                Перейти к оформлению
                            </a>
                        @endif
                    @else
                        <button type="button" wire:click="placeOrder" wire:loading.attr="disabled"
                                @disabled($summary->hasProblems())
                                class="w-full rounded-xl bg-brand-600 px-5 py-3 text-sm font-semibold text-white transition hover:bg-brand-700 disabled:opacity-60">
                            Оформить заказ
                        </button>
                    @endif
                </div>
            </aside>
        </div>
    @endif
    @include('nexor-shop::partials.tab-sync')
</div>
