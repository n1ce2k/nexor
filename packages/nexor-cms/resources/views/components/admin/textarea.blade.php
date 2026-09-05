@props(['name' => null, 'value' => null, 'rows' => 4])

@php
    $errorKey = $name ? str_replace(['[', ']'], ['.', ''], $name) : null;
    $hasError = $errorKey && $errors->has($errorKey);
@endphp

<textarea @if ($name) name="{{ $name }}" id="{{ $name }}" @endif
          rows="{{ $rows }}"
          {{ $attributes->merge(['class' => 'field-input resize-y'.($hasError ? ' has-error' : '')]) }}>{{ $value }}</textarea>
