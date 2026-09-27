{{--
    Выбор доставки СДЭК на оформлении: город → тариф → пункт выдачи или адрес.

    Приходит: $rates (тарифы с ценой и сроком), $tariff (выбранный), $points
    (пункты выдачи города), $settings (настройки способа), $ready (способ
    настроен), $addressField (поле заказа с адресом, если адрес берут оттуда),
    $deliveryError.

    Подключается внутри Livewire-компонента checkout, поэтому wire:model и
    wire:click уходят прямо в него.
--}}

@php($shop = \Nexor\Shop\Support\Shop::class)

<div class="mt-4 space-y-4 border-t border-slate-200 pt-4">
    @unless ($ready)
        <p class="text-sm text-amber-600">
            Доставка СДЭК ещё не настроена — выберите другой способ или напишите нам.
        </p>
    @else
        {{-- Шаг 1. Город: подсказки отдаёт сама служба. --}}
        <div>
            <label class="mb-1 block text-sm font-medium text-slate-700" for="cdek-city">Город доставки</label>

            <div class="flex gap-2">
                <div class="relative min-w-0 flex-1">
                    {{-- Живая привязка с задержкой: подсказки приходят на ввод,
                         но не на каждую букву. --}}
                    <input id="cdek-city" wire:model.live.debounce.500ms="cityQuery" wire:keydown.enter.prevent="searchCity"
                           placeholder="Начните вводить город"
                           class="w-full rounded-xl border border-slate-300 py-2 pr-10 pl-3 focus:border-brand-500 focus:ring-2 focus:ring-brand-500/20 focus:outline-none">

                    {{-- Пока служба ищет город, в поле крутится колесо. --}}
                    <svg wire:loading wire:target="cityQuery, searchCity" aria-hidden="true"
                         class="absolute top-1/2 right-3 size-4 -translate-y-1/2 animate-spin text-slate-400"
                         viewBox="0 0 24 24" fill="none">
                        <circle class="opacity-25" cx="12" cy="12" r="10" stroke="currentColor" stroke-width="3" />
                        <path class="opacity-90" fill="currentColor" d="M4 12a8 8 0 0 1 8-8v3a5 5 0 0 0-5 5H4Z" />
                    </svg>
                </div>

                <button type="button" wire:click="searchCity" wire:loading.attr="disabled" wire:target="searchCity"
                        class="rounded-xl border border-slate-300 px-4 py-2 text-sm font-semibold text-slate-700 transition hover:bg-slate-50">
                    <span wire:loading.remove wire:target="searchCity">Найти</span>
                    <span wire:loading wire:target="searchCity">Ищем…</span>
                </button>
            </div>

            @if ($cities)
                <ul class="mt-2 divide-y divide-slate-100 rounded-xl border border-slate-200">
                    @foreach ($cities as $city)
                        <li>
                            <button type="button" class="w-full px-3 py-2 text-left text-sm hover:bg-slate-50"
                                    wire:click="pickCity({{ $city['code'] }}, @js($city['full'] ?: $city['city']))">
                                {{ $city['full'] ?: $city['city'] }}
                            </button>
                        </li>
                    @endforeach
                </ul>
            @endif

            @if ($cityCode)
                <p class="mt-1 text-sm text-green-700">Город: {{ $cityName }}</p>
            @endif
        </div>

        @if ($deliveryError)
            <p class="text-sm text-red-600">{{ $deliveryError }}</p>
        @endif

        {{-- Шаг 2. Тариф: цену и срок считает служба под эту корзину. --}}
        @if ($cityCode && $rates)
            <div class="space-y-2" wire:loading.class="opacity-60" wire:target="pickCity, address">
                @foreach ($rates as $rate)
                    <label @class([
                        'flex cursor-pointer gap-3 rounded-xl border border-slate-200 p-3',
                        'has-[:checked]:border-brand-500 has-[:checked]:bg-brand-50' => $rate['error'] === null,
                        'cursor-not-allowed opacity-60' => $rate['error'] !== null,
                    ]) wire:key="tariff-{{ $rate['code'] }}">
                        <input type="radio" wire:model.live="tariffCode" value="{{ $rate['code'] }}" class="mt-1"
                               @disabled($rate['error'] !== null && $settings['price']['on_error'] === 'block')>

                        <span class="flex-1">
                            <span class="flex justify-between gap-3 font-medium text-slate-900">
                                {{ $rate['name'] }}
                                <span class="whitespace-nowrap">
                                    @if ($rate['price'] !== null)
                                        {{ $rate['price'] > 0 ? $shop::format($rate['price']) : 'бесплатно' }}
                                    @else
                                        —
                                    @endif
                                </span>
                            </span>

                            @if ($rate['error'])
                                <span class="mt-1 block text-sm text-red-600">{{ $rate['error'] }}</span>
                            @elseif ($settings['price']['show_period'] && $rate['period_min'] !== null)
                                <span class="mt-1 block text-sm text-slate-500">
                                    срок {{ $rate['period_min'] }}@if ($rate['period_max'] && $rate['period_max'] !== $rate['period_min'])–{{ $rate['period_max'] }}@endif дн.
                                </span>
                            @endif
                        </span>
                    </label>
                @endforeach
            </div>
        @endif

        {{-- Шаг 3. Куда везти: адрес для курьера или пункт выдачи. --}}
        @if ($tariff && $tariff['to_door'] && $settings['address']['own'])
            <div>
                <label class="mb-1 block text-sm font-medium text-slate-700" for="cdek-address">Адрес доставки</label>
                <input id="cdek-address" wire:model.blur="address" placeholder="Улица, дом, квартира"
                       class="w-full rounded-xl border border-slate-300 px-3 py-2 focus:border-brand-500 focus:ring-2 focus:ring-brand-500/20 focus:outline-none">
                <p class="mt-1 text-xs text-slate-500">Цена пересчитается по адресу.</p>
            </div>
        @elseif ($tariff && $tariff['to_door'])
            {{-- Адрес спрашивает поле заказа: своё поле здесь только мешало бы. --}}
            <p class="text-sm text-slate-500">
                Адрес доставки берём из поля «{{ $addressField?->name ?? $settings['address']['field'] }}» выше.
                @if ($addressField === null)
                    <span class="text-amber-600">Такого поля заказа нет — заведите его в админке.</span>
                @endif
            </p>
        @elseif ($tariff)
            @include('nexor-shop::partials.cdek-points', ['points' => $points, 'settings' => $settings])
        @endif

        @error('delivery')
            <p class="text-sm text-red-600">{{ $message }}</p>
        @enderror
    @endunless
</div>
