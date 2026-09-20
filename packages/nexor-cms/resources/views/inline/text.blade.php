{{--
    Блок текста, правимый на сайте.

    Приходит: $tag, $stored (правка или null), $type, $editing.
    Слот — значение по умолчанию из шаблона сайта.
--}}
@php($default = trim($slot->toHtml()))
@php($remember && $remember($default))

<{{ $tag }} {{ $attributes }}
    @if ($editing)
        data-nexor-edit="{{ $key }}" data-nexor-type="{{ $type }}"
        @if ($breaks) data-nexor-breaks="1" @endif
        @if ($stored !== null) data-nexor-edited="1" @endif
    @endif
>{!! $stored === null
        ? $default
        : ($type === 'html' ? $stored : ($breaks ? nl2br(e($stored)) : e($stored))) !!}</{{ $tag }}>
