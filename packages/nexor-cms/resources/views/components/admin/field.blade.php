@props([
    'label' => null,
    'name' => null,
    'hint' => null,
    'required' => false,
    'inline' => false,
])

@php
    $errorKey = $name ? str_replace(['[', ']'], ['.', ''], $name) : null;
    $error = $errorKey ? $errors->first($errorKey) : null;
@endphp

<div {{ $attributes->merge(['class' => 'space-y-1.5']) }}>
    @if ($label && ! $inline)
        <label @if ($name) for="{{ $name }}" @endif
               class="block text-sm font-medium text-[var(--text-strong)]">
            {{ $label }}
            @if ($required)
                <span class="text-red-500">*</span>
            @endif
        </label>
    @endif

    {{ $slot }}

    @if ($hint && ! $error)
        <p class="text-xs text-[var(--text-muted)]">{{ $hint }}</p>
    @endif

    @if ($error)
        <p class="flex items-center gap-1 text-xs text-red-600 dark:text-red-400">
            <x-nexor::admin.icon name="alert" class="size-3.5" />
            {{ $error }}
        </p>
    @endif
</div>
