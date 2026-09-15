{{--
    Блок «Текст».

    Приходит: $text и $text2 (HTML, очищен при сохранении), $cols (1 или 2),
    $cssClass, $blockId, $element.
--}}

@if ($text)
    <section class="pb-block pb-text {{ $cssClass }}">
        <div class="pb-container">
            <div class="pb-text__content {{ (int) $cols === 2 ? 'is-col-2' : '' }}">
                <div class="pb-text__col">
                    {!! $text !!}
                </div>
                @if ((int) $cols === 2)
                    <div class="pb-text__col">
                        {!! $text2 !!}
                    </div>
                @endif
            </div>
        </div>
    </section>
@endif
