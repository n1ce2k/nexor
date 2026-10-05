{{-- Страница оформления заказа. Макет — config('nexor-shop.layout'). --}}

@extends(config('nexor-shop.layout', 'site.layout'))

@section('title', 'Оформление заказа')

{{-- Крошки выведет компонент крошек в макете сайта. Вызов, а не директива breadcrumb:
     модуль ставится и на ядро старше 0.3.29, где её ещё нет. --}}
@php
    if (class_exists(\Nexor\Cms\Support\CurrentPage::class)) {
        \Nexor\Cms\Support\CurrentPage::get()->crumb('Корзина', route('shop.cart'));
        \Nexor\Cms\Support\CurrentPage::get()->crumb('Оформление заказа');
    }
@endphp

@section('content')
    <div class="mx-auto max-w-6xl px-4 py-14 sm:px-6 lg:px-8">
        <h1 class="mb-8 text-3xl font-semibold tracking-tight text-slate-900">Оформление заказа</h1>

        <livewire:nexor-shop::checkout />
    </div>
@endsection

@push('scripts')
    <script>
        /*
         * Выбор пункта выдачи СДЭК: карта Яндекса и список.
         *
         * Компонент регистрируется на странице, а не в самом блоке: блок
         * появляется уже после обновления Livewire, а скрипты из таких
         * обновлений на страницу не попадают. Alpine мог стартовать и раньше
         * этого места — учитываем оба порядка.
         */
        (() => {
            const register = () => Alpine.data('nexorCdekPoints', (config) => {
                /*
                 * Карта и её объекты живут вне данных Alpine: тот оборачивает
                 * состояние в Proxy, а Яндекс.Карты этого не переносят —
                 * метки просто перестают рисоваться.
                 */
                let map = null;
                let marks = new Map();

                return {
                    points: config.points ?? [],
                    chosen: config.chosen ?? '',
                    withMap: Boolean(config.withMap),
                    apiKey: config.apiKey ?? '',
                    // Уже выбранный пункт виден в поиске — как название города выше.
                    search: config.chosenAddress ?? '',
                    mapFailed: false,

                    init() {
                        if (this.withMap) {
                            this.showMap().catch((error) => {
                                console.warn('Карта пунктов выдачи:', error);

                                // Прячем карту, только если её так и не построили:
                                // споткнуться на мелочи после отрисовки — не повод.
                                this.mapFailed = ! map;
                            });
                        }
                    },

                    get shown() {
                        const needle = this.search.trim().toLowerCase();

                        return needle === ''
                            ? this.points
                            : this.points.filter((point) => (point.name + ' ' + point.full).toLowerCase().includes(needle));
                    },

                    /* Карта грузится один раз и только когда пункты уже нужны. */
                    async showMap() {
                        await this.loadApi();
                        await ymaps.ready();

                        // Точки тоже берём «сырыми», без обёртки Alpine.
                        const points = Alpine.raw(this.points);

                        marks = new Map();
                        const located = points.filter((point) => point.latitude && point.longitude);
                        const first = located[0];
                        const container = this.$refs.map;

                        map = new ymaps.Map(container, {
                            center: first ? [first.latitude, first.longitude] : [55.75, 37.62],
                            zoom: 11,
                            controls: ['zoomControl', 'geolocationControl'],
                        });

                        const cluster = new ymaps.Clusterer({
                            groupByCoordinates: false,
                            clusterDisableClickZoom: false,
                            preset: 'islands#greenClusterIcons',
                        });

                        cluster.add(located.map((point) => {
                            const mark = new ymaps.Placemark([point.latitude, point.longitude], {
                                balloonContentHeader: point.name,
                                balloonContentBody: point.full + (point.work_time ? '<br>' + point.work_time : ''),
                                balloonContentFooter: '<button type="button" class="ymaps-pick" data-code="'
                                    + point.code + '">Выбрать пункт</button>',
                            }, {
                                // Пункты выдачи зелёные, выбранный — тёмно-зелёный.
                                preset: point.code === this.chosen ? 'islands#darkGreenDotIcon' : 'islands#greenDotIcon',
                            });

                            // Метку помним: по ней подсвечиваем выбор из списка.
                            marks.set(point.code, mark);

                            return mark;
                        }));

                        map.geoObjects.add(cluster);

                        const bounds = located.length > 1 ? cluster.getBounds() : null;

                        if (bounds) {
                            map.setBounds(bounds, { checkZoomRange: true, zoomMargin: 30 });
                        }

                        // Блок мог появиться, пока карта грузилась: пересчитать размер.
                        map.container.fitToViewport();

                        /* Кнопку внутри балуна ловим на всплытии: балун живёт вне Alpine. */
                        container.addEventListener('click', (event) => {
                            const button = event.target.closest('.ymaps-pick');

                            if (button) {
                                this.pick(points.find((item) => item.code === button.dataset.code));
                            }
                        });
                    },

                    loadApi() {
                        if (window.ymaps) {
                            return Promise.resolve();
                        }

                        return new Promise((resolve, reject) => {
                            const script = document.createElement('script');
                            script.src = 'https://api-maps.yandex.ru/2.1/?apikey=' + encodeURIComponent(this.apiKey) + '&lang=ru_RU';
                            script.onload = resolve;
                            script.onerror = () => reject(new Error('Яндекс.Карты не загрузились'));
                            document.head.appendChild(script);
                        });
                    },

                    pick(point) {
                        if (! point) {
                            return;
                        }

                        marks.get(this.chosen)?.options.set('preset', 'islands#greenDotIcon');
                        this.chosen = point.code;

                        marks.get(point.code)?.options.set('preset', 'islands#darkGreenDotIcon');

                        // Выбор из списка показываем и на карте.
                        if (map && point.latitude && point.longitude) {
                            map.panTo([point.latitude, point.longitude], { flying: true });
                        }

                        // Адрес встаёт в поле поиска — как название города выше.
                        this.search = point.full || point.address || '';
                        this.scrollToChosen();

                        this.$wire.pickPoint(point.code, point.full || point.address);
                    },

                    /* Выбранный пункт подводим к глазам: список длинный. */
                    scrollToChosen() {
                        this.$nextTick(() => {
                            this.$refs.list
                                ?.querySelector('[data-code="' + this.chosen + '"]')
                                ?.scrollIntoView({ block: 'nearest' });
                        });
                    },
                };
            });

            window.Alpine ? register() : document.addEventListener('alpine:init', register);
        })();
    </script>
@endpush
