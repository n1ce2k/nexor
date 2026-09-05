@props(['name', 'value' => null])

@php
    $errorKey = str_replace(['[', ']'], ['.', ''], $name);
    $hasError = $errors->has($errorKey);
@endphp

<div x-data="colorField(@js($value ?? ''))" class="flex items-center gap-2">
    <label class="relative size-9 shrink-0 cursor-pointer overflow-hidden rounded-lg border border-[var(--surface-border-strong)]"
           :style="`background-color: ${normalised}`">
        <input type="color" :value="normalised" @input="sync($event)"
               class="absolute inset-0 size-full cursor-pointer opacity-0">
    </label>

    <input type="text"
           name="{{ $name }}"
           id="{{ $name }}"
           x-model="value"
           placeholder="#ffffff"
           maxlength="7"
           {{ $attributes->merge(['class' => 'field-input font-mono uppercase'.($hasError ? ' has-error' : '')]) }}>
</div>
