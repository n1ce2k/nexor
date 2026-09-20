{{--
    Картинка, правимая на сайте.

    Приходит: $url (правка или значение по умолчанию), $editing.
--}}
<img src="{{ $url }}" alt="{{ $alt }}" {{ $attributes }}
     @if ($editing)
         data-nexor-edit="{{ $key }}" data-nexor-type="image"
     @endif
>
