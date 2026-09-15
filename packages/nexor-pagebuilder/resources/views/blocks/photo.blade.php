{{--
    Блок «Фото / Галерея».

    Приходит: $images — [{ src, alt, title }], $gridClass (pb-layout-2,
    pb-layout-3_asym--top, pb-layout-custom…), $customCols (для своей сетки),
    $visibleCount, $hiddenCount, $showOverlay, $cssClass, $blockId, $element.

    Картинки открываются через Fancybox (data-fancybox), если он подключён на сайте.
--}}

@php
    $galleryId = 'gallery-'.$blockId;
@endphp

@if (! empty($images))
    <section class="pb-block pb-photo {{ $cssClass }}">
        <div class="pb-container">
            <div class="pb-photo__grid {{ $gridClass }}" @if ($customCols) style="--custom-cols: {{ (int) $customCols }};" @endif>

                @foreach ($images as $idx => $img)
                    @php
                        $isVisible = ! $showOverlay || ($idx < $visibleCount);
                        $isLastVisible = $showOverlay && ($idx === $visibleCount - 1);
                    @endphp
                    <a href="{{ $img['src'] }}"
                       class="pb-photo__item {{ $isVisible ? '' : 'pb-photo__item--hidden' }}"
                       data-fancybox="{{ $galleryId }}"
                       data-caption="{{ $img['title'] ?: $img['alt'] }}">

                        <img src="{{ $img['src'] }}"
                             alt="{{ $img['alt'] }}"
                             title="{{ $img['title'] }}"
                             loading="lazy"
                             class="pb-photo__img">

                        @if ($isLastVisible && $hiddenCount > 0)
                            <div class="pb-photo__more-overlay">
                                <span>+{{ $hiddenCount }}</span>
                            </div>
                        @endif
                    </a>
                @endforeach

            </div>
        </div>
    </section>
@endif
