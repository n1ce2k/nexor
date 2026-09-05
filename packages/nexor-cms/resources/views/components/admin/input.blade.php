@props(['name' => null, 'type' => 'text', 'value' => null, 'prefix' => null, 'suffix' => null])

@php
    $errorKey = $name ? str_replace(['[', ']'], ['.', ''], $name) : null;
    $hasError = $errorKey && $errors->has($errorKey);
@endphp

@if ($prefix || $suffix)
    <div class="flex items-stretch">
        @if ($prefix)
            <span class="inline-flex items-center rounded-l-lg border border-r-0 border-[var(--surface-border-strong)] bg-[var(--surface-muted)] px-3 text-sm text-[var(--text-muted)]">
                {{ $prefix }}
            </span>
        @endif

        <input type="{{ $type }}"
               @if ($name) name="{{ $name }}" id="{{ $name }}" @endif
               value="{{ $value }}"
               {{ $attributes->merge([
                   'class' => 'field-input '
                       . ($hasError ? 'has-error ' : '')
                       . ($prefix ? 'rounded-l-none ' : '')
                       . ($suffix ? 'rounded-r-none' : ''),
               ]) }}>

        @if ($suffix)
            <span class="inline-flex items-center rounded-r-lg border border-l-0 border-[var(--surface-border-strong)] bg-[var(--surface-muted)] px-3 text-sm text-[var(--text-muted)]">
                {{ $suffix }}
            </span>
        @endif
    </div>
@else
    <input type="{{ $type }}"
           @if ($name) name="{{ $name }}" id="{{ $name }}" @endif
           value="{{ $value }}"
           {{ $attributes->merge(['class' => 'field-input'.($hasError ? ' has-error' : '')]) }}>
@endif
