{{--
    Шаблон компонента catalog.element: детальная карточка.

    Приходит: $element, $block (инфоблок), $values (значения свойств по кодам),
    $showProperties, $offers (активные предложения товара), $offer (выбранное),
    $offersByProperties — как товар выводит предложения (галочка «Выводить через
    свойства» на вкладке «Предложения» в админке).

    Подключение:
        <x-nexor::catalog.element :element="$element" :offer="$offer" />   на странице товара
        <x-nexor::catalog.element iblock="katalog" :id="15" />             в любом месте, по id
        <x-nexor::catalog.element iblock="katalog" code="stul" />          или по символьному коду
    Скрытый или удалённый элемент — компонент ничего не выводит.

    Свой шаблон: php artisan nexor:component catalog.element

    ─── Два вида предложений ──────────────────────────────────────────────────

    Через свойства ($offersByProperties = true). У каждого предложения свой адрес:
    /katalog/razdel1/tovar/<код предложения>. Кнопки переключения — обычные ссылки
    на эти адреса (работают и без JS, их видят поисковики). Скрипт внизу
    перехватывает клик, запрашивает страницу предложения и подменяет на месте
    только куски с атрибутом data-offer-zone, меняет адрес (history.pushState),
    <title> и <link rel="canonical">. Кнопки «назад/вперёд» возвращают прошлое
    предложение. Цена, наличие и кнопка — у выбранного предложения.

    Списком ($offersByProperties = false). Сверху товар, под ним все предложения
    строками, у каждой своя цена и кнопка. $offer здесь всегда null: адрес
    предложения отвечает 301 на страницу товара.

    Чтобы canonical обновлялся, в <head> макета должна быть строка:
        <link rel="canonical" href="@yield('canonical', url()->current())">
--}}

@php
    /** Что показываем в цене, наличии и кнопке: выбранное предложение или сам товар. */
    $priced = $offer ?? $element;
    $catalog = $priced->catalog;

    /** У товара с предложениями своей цены нет — её показывают предложения. */
    $showsOwnPrice = $catalog && ! ($catalog->usesOffers() && $block->hasOffers());

    /** Подпись варианта. В своём шаблоне сюда удобно подставить свойство: $item->property('CODE') ?: $item->name. */
    $offerLabel = fn ($item) => $item->name;
@endphp

<article>
    {{-- Заголовок: название товара и выбранного варианта. --}}
    <header class="mb-8" data-offer-zone="title">
        <h1 class="text-3xl font-semibold tracking-tight text-slate-900 sm:text-4xl">
            {{ $element->name }}
            @if ($offer)
                <span class="text-slate-500">— {{ $offer->name }}</span>
            @endif
        </h1>
    </header>

    {{-- Картинка предложения, если есть, иначе картинка товара. --}}
    <div data-offer-zone="picture">
        @if ($picture = $offer?->detail_picture_url ?? $element->detail_picture_url)
            <img src="{{ $picture }}" alt="{{ $priced->name }}" class="mb-8 w-full rounded-2xl object-cover">
        @endif
    </div>

    @if ($element->preview_text)
        <p class="mb-8 border-l-4 border-brand-200 pl-4 text-lg text-slate-600">{{ $element->preview_text }}</p>
    @endif

    {{-- Через свойства: выбор предложения ссылками на их адреса. --}}
    @if ($offersByProperties && $offers->isNotEmpty())
        <nav class="mb-6 flex flex-wrap gap-2" data-offer-zone="switcher" aria-label="Варианты товара">
            @foreach ($offers as $item)
                @php($isCurrent = $offer?->id === $item->id)

                <a href="{{ $item->url() }}" data-offer-link
                   @if ($isCurrent) aria-current="true" @endif
                   @class([
                       'rounded-xl border px-4 py-2 text-sm font-medium transition',
                       'border-brand-600 bg-brand-600 text-white' => $isCurrent,
                       'border-slate-300 text-slate-700 hover:border-brand-500' => ! $isCurrent,
                       'opacity-50' => $item->catalog && ! $item->catalog->isAvailable(),
                   ])>
                    {{ $offerLabel($item) }}
                </a>
            @endforeach
        </nav>
    @endif

    {{-- Цена, наличие и кнопка — выбранного предложения или самого товара. --}}
    <div data-offer-zone="price">
        @if ($showsOwnPrice)
            <div class="mb-8 rounded-2xl border border-slate-200 p-5">
                @include('nexor::components.catalog.element.partials.price', ['catalog' => $catalog])

                {{-- Кнопка корзины — модуль «Магазин». Счётчик количества — внутри кнопки. --}}
                @feature('shop')
                    @if ($catalog->hasPrice())
                        <livewire:nexor-shop::add-to-cart :element-id="$priced->id" :key="'buy-'.$priced->id" />
                    @endif
                @endfeature
            </div>
        @endif
    </div>

    {{-- Списком: все предложения под товаром, у каждого своя кнопка. --}}
    @if (! $offersByProperties && $offers->isNotEmpty())
        <section class="mb-8">
            <h2 class="mb-3 text-lg font-semibold text-slate-900">Варианты</h2>

            <ul class="divide-y divide-slate-100 rounded-2xl border border-slate-200">
                @foreach ($offers as $item)
                    <li class="grid gap-3 px-5 py-4 sm:grid-cols-[1fr_auto] sm:items-center" id="offer-{{ $item->code ?: $item->id }}">
                        <div class="min-w-0">
                            <p class="font-medium text-slate-900">{{ $offerLabel($item) }}</p>

                            @if ($item->catalog)
                                @include('nexor::components.catalog.element.partials.price', ['catalog' => $item->catalog, 'compact' => true])
                            @endif
                        </div>

                        @feature('shop')
                            @if ($item->catalog?->hasPrice())
                                <livewire:nexor-shop::add-to-cart :element-id="$item->id" :key="'buy-offer-'.$item->id" />
                            @endif
                        @endfeature
                    </li>
                @endforeach
            </ul>
        </section>
    @endif

    {{-- Конструктор страниц (модуль pagebuilder): если у инфоблока он включён и
         блоки заполнены — они выводятся вместо подробного текста. --}}
    @feature('pagebuilder')
        @php($pageBuilder = \Nexor\PageBuilder\PageBuilder::render($element)->toHtml())
    @endfeature

    @if (! empty($pageBuilder))
        {!! $pageBuilder !!}
    @else
        <div class="space-y-4 leading-relaxed text-slate-700">
            @if ($element->detail_text_type === 'html')
                {!! $element->detail_text !!}
            @else
                {!! nl2br(e($element->detail_text)) !!}
            @endif
        </div>
    @endif

    @if ($showProperties)
        <dl class="mt-8 grid gap-2 text-sm">
            @foreach ($block->properties as $property)
                @continue(! $property->is_active)

                @php($value = $values->get($property->code))
                @continue($value === null || $value === '' || (is_iterable($value) && count($value) === 0))

                <div class="flex justify-between gap-4 border-b border-slate-100 py-2">
                    <dt class="text-slate-500">{{ $property->name }}</dt>
                    <dd class="text-slate-900">{{ $element->displayValue($property) }}</dd>
                </div>
            @endforeach
        </dl>
    @endif
</article>

@if ($offersByProperties && $offers->isNotEmpty())
    @once
        @push('scripts')
            <script>
                /*
                 * Переключение предложений: подмена зон data-offer-zone без перезагрузки.
                 * Livewire сам поднимает кнопку «В корзину» из новой зоны.
                 */
                (() => {
                    let loading = null;

                    async function showOffer(url, push) {
                        loading?.abort();
                        loading = new AbortController();

                        let html;

                        try {
                            const response = await fetch(url, {
                                headers: { 'X-Requested-With': 'XMLHttpRequest' },
                                signal: loading.signal,
                            });

                            if (!response.ok) {
                                throw new Error(response.status);
                            }

                            html = await response.text();
                        } catch (error) {
                            if (error.name !== 'AbortError') {
                                window.location.href = url; // Не вышло — обычный переход.
                            }

                            return;
                        }

                        const fresh = new DOMParser().parseFromString(html, 'text/html');

                        document.querySelectorAll('[data-offer-zone]').forEach((zone) => {
                            const next = fresh.querySelector(`[data-offer-zone="${zone.dataset.offerZone}"]`);

                            if (next) {
                                zone.innerHTML = next.innerHTML;
                            }
                        });

                        document.title = fresh.title;

                        const canonical = document.querySelector('link[rel="canonical"]');
                        const nextCanonical = fresh.querySelector('link[rel="canonical"]');

                        if (canonical && nextCanonical) {
                            canonical.href = nextCanonical.href;
                        }

                        if (push) {
                            history.pushState({ offerUrl: url }, '', url);
                        }

                        // Для корзины, аналитики и своих скриптов: предложение сменилось.
                        window.dispatchEvent(new CustomEvent('nexor:offer-changed', { detail: { url } }));
                    }

                    document.addEventListener('click', (event) => {
                        const link = event.target.closest('a[data-offer-link]');

                        // Ctrl/Cmd-клик — открыть в новой вкладке, как обычная ссылка.
                        if (!link || event.button !== 0 || event.metaKey || event.ctrlKey || event.shiftKey || event.altKey) {
                            return;
                        }

                        event.preventDefault();

                        if (link.getAttribute('aria-current') !== 'true') {
                            showOffer(link.href, true);
                        }
                    });

                    window.addEventListener('popstate', () => showOffer(window.location.href, false));
                })();
            </script>
        @endpush
    @endonce
@endif
