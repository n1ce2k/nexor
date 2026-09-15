{{--
    Блок «Цитата».

    Приходит: $text (HTML, очищен при сохранении), $showAuthor, $author, $role,
    $avatar — { src, alt, title } или null, $cssClass, $blockId, $element.
--}}

@php($avatarSrc = $avatar['src'] ?? '')

@if ($text)
    <section class="pb-block pb-quote {{ $cssClass }}">
        <div class="pb-container">
            <div class="pb-quote__wrapper">
                <div class="pb-quote__text">
                    {!! $text !!}
                </div>
                @if ($showAuthor && ($author || $role || $avatarSrc))
                    <div class="pb-quote__footer">
                        @if ($avatarSrc)
                            <div class="pb-quote__avatar">
                                <img src="{{ $avatarSrc }}" alt="{{ $author }}">
                            </div>
                        @endif
                        @if ($author || $role)
                            <div class="pb-quote__info">
                                @if ($author)
                                    <div class="pb-quote__author">{{ $author }}</div>
                                @endif
                                @if ($role)
                                    <div class="pb-quote__role">{{ $role }}</div>
                                @endif
                            </div>
                        @endif
                    </div>
                @endif
            </div>
        </div>
    </section>
@endif
