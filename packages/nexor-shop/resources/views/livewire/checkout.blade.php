{{--
    Оформление заказа (Ultimate).

    Приходит: $summary, $fields, $deliveries, $payments, $deliveryPrice, $grandTotal, $cartUrl.
--}}

<div>
    @if ($placedNumber)
        @include('nexor-shop::partials.success', ['number' => $placedNumber, 'paymentUrl' => $paymentUrl])
    @elseif ($summary->isEmpty())
        <div class="rounded-2xl border border-dashed border-slate-300 p-10 text-center">
            <p class="text-lg font-medium text-slate-900">Корзина пуста — оформлять нечего</p>
            <a href="{{ url('/') }}" class="mt-3 inline-block text-sm font-medium text-brand-600 hover:underline">Перейти к покупкам</a>
        </div>
    @else
        <form wire:submit="placeOrder" class="grid gap-8 lg:grid-cols-[1fr_22rem]">
            <div class="space-y-6">
                <section class="rounded-2xl border border-slate-200 p-5">
                    <h2 class="mb-4 text-lg font-semibold text-slate-900">Покупатель</h2>

                    @include('nexor-shop::partials.order-form', ['fields' => $fields])
                </section>

                @if ($deliveries->isNotEmpty())
                    <section class="rounded-2xl border border-slate-200 p-5">
                        <h2 class="mb-4 text-lg font-semibold text-slate-900">Доставка</h2>

                        <div class="space-y-2">
                            @foreach ($deliveries as $delivery)
                                <label class="flex cursor-pointer gap-3 rounded-xl border border-slate-200 p-3 has-[:checked]:border-brand-500 has-[:checked]:bg-brand-50"
                                       wire:key="delivery-{{ $delivery->id }}">
                                    <input type="radio" wire:model.live="deliveryId" value="{{ $delivery->id }}" class="mt-1">
                                    <span class="flex-1">
                                        <span class="flex justify-between gap-3 font-medium text-slate-900">
                                            {{ $delivery->name }}
                                            <span class="whitespace-nowrap">
                                                {{ $delivery->priceFor($summary->total()) > 0 ? \Nexor\Shop\Support\Shop::format($delivery->priceFor($summary->total())) : 'бесплатно' }}
                                            </span>
                                        </span>
                                        @if ($delivery->description)
                                            <span class="mt-1 block text-sm text-slate-500">{{ $delivery->description }}</span>
                                        @endif
                                        @if ($delivery->free_from !== null && $delivery->priceFor($summary->total()) > 0)
                                            <span class="mt-1 block text-xs text-slate-500">
                                                Бесплатно от {{ \Nexor\Shop\Support\Shop::format((float) $delivery->free_from) }}
                                            </span>
                                        @endif
                                    </span>
                                </label>
                            @endforeach
                        </div>

                        @error('delivery')
                            <p class="mt-2 text-sm text-red-600">{{ $message }}</p>
                        @enderror
                    </section>
                @endif

                @if ($payments->isNotEmpty())
                    <section class="rounded-2xl border border-slate-200 p-5">
                        <h2 class="mb-4 text-lg font-semibold text-slate-900">Оплата</h2>

                        <div class="space-y-2">
                            @foreach ($payments as $payment)
                                <label class="flex cursor-pointer gap-3 rounded-xl border border-slate-200 p-3 has-[:checked]:border-brand-500 has-[:checked]:bg-brand-50"
                                       wire:key="payment-{{ $payment->id }}">
                                    <input type="radio" wire:model="paymentId" value="{{ $payment->id }}" class="mt-1">
                                    <span class="flex-1">
                                        <span class="font-medium text-slate-900">{{ $payment->name }}</span>
                                        @if ($payment->description)
                                            <span class="mt-1 block text-sm text-slate-500">{{ $payment->description }}</span>
                                        @endif
                                    </span>
                                </label>
                            @endforeach
                        </div>

                        @error('payment')
                            <p class="mt-2 text-sm text-red-600">{{ $message }}</p>
                        @enderror
                    </section>
                @endif
            </div>

            <aside class="space-y-4">
                <div class="space-y-4 rounded-2xl border border-slate-200 p-5">
                    <p class="font-semibold text-slate-900">Ваш заказ</p>

                    <ul class="space-y-2 text-sm">
                        @foreach ($summary->lines as $line)
                            <li class="flex justify-between gap-3" wire:key="summary-{{ $line->id }}">
                                <span class="text-slate-600">
                                    {{ $line->name }} × {{ rtrim(rtrim(number_format($line->quantity, 3, '.', ''), '0'), '.') }}
                                </span>
                                <span class="whitespace-nowrap text-slate-900">
                                    @if ($line->converted)<span class="text-amber-600" title="Цена товара сконвертирована по курсу">≈</span>@endif
                                    {{ \Nexor\Shop\Support\Shop::format($line->sum()) }}
                                </span>
                            </li>
                        @endforeach
                    </ul>

                    @include('nexor-shop::partials.totals', compact('summary', 'deliveryPrice', 'grandTotal'))

                    @foreach (['cart', 'promocode'] as $key)
                        @error($key)
                            <p class="text-sm text-red-600">{{ $message }}</p>
                        @enderror
                    @endforeach

                    <button type="submit" wire:loading.attr="disabled" @disabled($summary->hasProblems())
                            class="w-full rounded-xl bg-brand-600 px-5 py-3 text-sm font-semibold text-white transition hover:bg-brand-700 disabled:opacity-60">
                        Подтвердить заказ
                    </button>

                    <a href="{{ $cartUrl }}" class="block text-center text-sm text-slate-500 hover:text-brand-600">Вернуться в корзину</a>
                </div>
            </aside>
        </form>
    @endif
    @include('nexor-shop::partials.tab-sync')
</div>
