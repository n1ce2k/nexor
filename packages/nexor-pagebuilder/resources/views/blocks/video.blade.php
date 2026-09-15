{{--
    Блок «Видео».

    Приходит: $videoUrl — адрес плеера (собран из распознанной ссылки) или файла,
    $isIframe, $videoAttrString (autoplay loop muted controls playsinline),
    $poster — { src, … } или null, $layout (full / caption / split), $layoutClass,
    $text (HTML, очищен при сохранении), $cssClass, $blockId, $element.
--}}

@if ($videoUrl)
    <section class="pb-block pb-video {{ $layoutClass }} {{ $cssClass }}">
        <div class="pb-container">
            <div class="pb-video__grid">
                <div class="pb-video__col-player">
                    <div class="pb-video__wrapper">
                        @if ($isIframe)
                            <iframe src="{{ $videoUrl }}"
                                    frameborder="0"
                                    allow="autoplay; fullscreen; picture-in-picture"
                                    allowfullscreen
                                    loading="lazy"></iframe>
                        @else
                            <video src="{{ $videoUrl }}"
                                   poster="{{ $poster['src'] ?? '' }}"
                                {{ $videoAttrString }}>
                            </video>
                        @endif
                    </div>
                </div>

                @if ($layout !== 'full' && $text)
                    <div class="pb-video__col-text">
                        <div class="pb-text__content">
                            {!! $text !!}
                        </div>
                    </div>
                @endif
            </div>
        </div>
    </section>
@endif
