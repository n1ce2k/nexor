{{--
    Текст соглашения: HTML из редактора админки или обычный текст.

    Приходит: $agreement — модель соглашения. HTML пишут администраторы с
    правом на соглашения, он выводится как есть.
--}}

@if ($agreement->isHtml())
    {!! $agreement->text !!}
@else
    {!! nl2br(e((string) $agreement->text)) !!}
@endif
