{{--
    Пункты выдачи СДЭК: карта Яндекса или список с поиском.

    Приходит: $points (пункты города), $settings (настройки способа).

    Карта включается в админке галочкой и ключом Яндекс.Карт. Без неё — список,
    и никакие внешние скрипты не подключаются. Выбранный пункт уходит в
    компонент методом pickPoint, поэтому и карта, и список дают один результат.

    Логика лежит отдельным компонентом Alpine, а не в атрибуте: внутри тега
    Livewire-шаблона нельзя ни условий Blade (он оборачивает их маркерами, и
    они рвут разметку), ни длинных выражений — Alpine разбирает их как одно
    выражение и спотыкается.
--}}

@php($withMap = $settings['map']['enabled'] && $settings['map']['yandex_key'] !== '' && $points !== [])

{{--
    Ключ держит блок на месте при обновлениях Livewire: без него выбор пункта
    пересоздавал бы блок, и набранный поиск с подсветкой пропадали бы сразу
    после клика. Смена города или тарифа ключ меняет — там пункты уже другие.
--}}
<div class="space-y-3" wire:key="cdek-points-{{ $cityCode }}-{{ $tariffCode }}"
     x-data="nexorCdekPoints(@js([
    'points' => $points,
    'chosen' => $pointCode,
    'chosenAddress' => $pointAddress,
    'withMap' => $withMap,
    'apiKey' => $settings['map']['yandex_key'],
]))">
    <p class="text-sm font-medium text-slate-700">Пункт выдачи</p>

    @if ($points === [])
        <p class="text-sm text-slate-500">В этом городе пунктов выдачи не нашлось — выберите доставку до двери.</p>
    @else
        <div x-ref="map" x-show="withMap && ! mapFailed"
             class="h-80 w-full overflow-hidden rounded-xl"></div>

        <p x-show="mapFailed" x-cloak class="text-sm text-amber-600">
            Карта не загрузилась — выберите пункт из списка.
        </p>

        <input x-model="search" placeholder="Поиск по адресу"
               class="w-full rounded-xl border border-slate-300 px-3 py-2 focus:border-brand-500 focus:ring-2 focus:ring-brand-500/20 focus:outline-none">

        <ul x-ref="list" class="max-h-64 divide-y divide-slate-100 overflow-y-auto rounded-xl border border-slate-200">
            <template x-for="point in shown" :key="point.code">
                <li>
                    {{-- Выбранный пункт помечаем зелёным — как и его метку на карте. --}}
                    <button type="button" :data-code="point.code"
                            class="w-full border-l-4 px-3 py-2 text-left text-sm transition hover:bg-slate-50"
                            :class="point.code === chosen
                                ? 'border-green-500 bg-green-500/10 font-medium'
                                : 'border-transparent'"
                            x-on:click="pick(point)">
                        <span x-text="point.name"></span>
                        <span class="block text-slate-500" x-text="point.full"></span>
                        <span class="block text-xs text-slate-400" x-text="point.work_time"></span>
                    </button>
                </li>
            </template>
        </ul>
    @endif

    @if ($pointCode)
        <p class="text-sm text-green-700">Выбран пункт {{ $pointCode }}{{ $pointAddress ? ': '.$pointAddress : '' }}</p>
    @endif
</div>
