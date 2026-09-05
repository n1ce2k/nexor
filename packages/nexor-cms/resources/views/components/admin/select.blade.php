@props([
    'name' => null,
    'options' => [],
    'selected' => null,
    'placeholder' => null,
    'multiple' => false,
])

@php
    $errorKey = $name ? str_replace(['[', ']'], ['.', ''], $name) : null;
    $hasError = $errorKey && $errors->has($errorKey);
    $current = $multiple ? (array) $selected : $selected;

    $isSelected = fn ($value) => $multiple
        ? in_array((string) $value, array_map('strval', $current), true)
        : (string) $current === (string) $value;
@endphp

<select @if ($name) name="{{ $name }}{{ $multiple ? '[]' : '' }}" id="{{ $name }}" @endif
        @if ($multiple) multiple @endif
        {{ $attributes->merge(['class' => 'field-input pr-9'.($hasError ? ' has-error' : '')]) }}>
    @if ($placeholder && ! $multiple)
        <option value="">{{ $placeholder }}</option>
    @endif

    @foreach ($options as $value => $label)
        @if (is_array($label))
            <optgroup label="{{ $value }}">
                @foreach ($label as $groupValue => $groupLabel)
                    <option value="{{ $groupValue }}" @selected($isSelected($groupValue))>{{ $groupLabel }}</option>
                @endforeach
            </optgroup>
        @else
            <option value="{{ $value }}" @selected($isSelected($value))>{{ $label }}</option>
        @endif
    @endforeach

    {{ $slot }}
</select>
