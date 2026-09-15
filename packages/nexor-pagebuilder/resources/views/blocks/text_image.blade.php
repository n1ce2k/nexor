{{--
    Блок «Текст + Фото».

    Приходит: $text (HTML, очищен при сохранении), $image — { src, alt, title }
    или null, $imagePosition (left / right), $cssClass, $blockId, $element.
--}}

@if ($text || $image)
    <section class="pb-block pb-text-image {{ $imagePosition === 'right' ? 'pb-text-image--right' : '' }} {{ $cssClass }}">
        <div class="pb-container">
            <div class="pb-text-image__grid">
                <div class="pb-text-image__col-img">
                    @if (! empty($image['src']))
                        <div class="pb-text-image__img-wrapper">
                            <img src="{{ $image['src'] }}"
                                 alt="{{ $image['alt'] }}"
                                 title="{{ $image['title'] }}"
                                 loading="lazy">
                        </div>
                    @endif
                </div>
                <div class="pb-text-image__col-text">
                    <div class="pb-text__content">
                        {!! $text !!}
                    </div>
                </div>
            </div>
        </div>
    </section>
@endif
