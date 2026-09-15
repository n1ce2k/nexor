{{--
    Блок «Заголовок».

    Приходит: $text, $tag (h2–h5), $cssClass, $blockId, $element.
--}}

@php($tag = in_array($tag ?? 'h2', ['h2', 'h3', 'h4', 'h5'], true) ? $tag : 'h2')

@if ($text)
    <section class="pb-block pb-header {{ $cssClass }}">
        <div class="pb-container">
            <{{ $tag }} class="pb-header__title">
                {{ $text }}
            </{{ $tag }}>
        </div>
    </section>
@endif
