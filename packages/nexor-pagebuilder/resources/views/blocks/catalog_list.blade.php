{{--
    Блок «Каталог».

    Приходит: $title, $description (HTML, очищен при сохранении),
    $items — коллекция элементов инфоблока, $cardView — вьюха карточки
    (nexor::components. + «Шаблон карточки» из блока, по умолчанию
    catalog.card.default), $cardTemplate, $view — { slidesPerView, gap,
    arrows, dots }, $source, $cssClass, $blockId, $element.

    Карточка сама проверяет модуль магазина: кнопка «В корзину» — внутри
    @feature('shop'). Карусель — Swiper сайта, как у «Слайдера».
--}}

@if ($items->isNotEmpty())
    <section class="pb-block pb-catalog-list {{ $cssClass }}">
        <div class="pb-container">

            @if ($title || $description)
                <div class="pb-block-header">
                    @if ($title)
                        <h2 class="pb-block-title">{{ $title }}</h2>
                    @endif
                    @if ($description)
                        <div class="pb-block-description">{!! $description !!}</div>
                    @endif
                </div>
            @endif

            <div class="pb-catalog-list__wrapper">
                <div class="swiper js-pb-slider"
                     data-slides="{{ (int) $view['slidesPerView'] }}"
                     data-gap="{{ (int) $view['gap'] }}">

                    <div class="swiper-wrapper">
                        @foreach ($items as $item)
                            <div class="swiper-slide">
                                @include($cardView, ['element' => $item])
                            </div>
                        @endforeach
                    </div>

                    @if ($view['arrows'])
                        <div class="swiper-button-prev"></div>
                        <div class="swiper-button-next"></div>
                    @endif

                    @if ($view['dots'])
                        <div class="swiper-pagination"></div>
                    @endif
                </div>
            </div>

        </div>
    </section>
@endif
