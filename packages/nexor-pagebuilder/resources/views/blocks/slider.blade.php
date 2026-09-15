{{--
    Блок «Слайдер».

    Приходит: $images — [{ src, alt, title }], $slidesPerView, $gap, $autoplay,
    $arrows, $dots, $cssClass, $blockId, $element.

    Карусель — Swiper, подключённый на сайте: скрипт модуля оживляет
    .js-pb-slider, если window.Swiper есть.
--}}

@if (! empty($images))
    <section class="pb-block pb-slider {{ $cssClass }}">
        <div class="pb-container">
            <div class="swiper js-pb-slider"
                 data-slides="{{ (int) $slidesPerView }}"
                 data-gap="{{ (int) $gap }}"
                 data-autoplay="{{ $autoplay ? 'true' : 'false' }}">

                <div class="swiper-wrapper">
                    @foreach ($images as $img)
                        <div class="swiper-slide">
                            <div class="pb-slider__item">
                                <img src="{{ $img['src'] }}"
                                     alt="{{ $img['alt'] }}"
                                     title="{{ $img['title'] }}"
                                     loading="lazy">
                            </div>
                        </div>
                    @endforeach
                </div>

                @if ($arrows)
                    <div class="swiper-button-prev"></div>
                    <div class="swiper-button-next"></div>
                @endif

                @if ($dots)
                    <div class="swiper-pagination"></div>
                @endif
            </div>
        </div>
    </section>
@endif
