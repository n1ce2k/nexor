@props([
    'variant' => 'primary',
    'size' => 'md',
    'href' => null,
    'icon' => null,
    'type' => 'button',
])

@php
    $variants = [
        'primary' => 'bg-brand-600 text-white hover:bg-brand-700 focus-visible:outline-brand-600 shadow-sm',
        'secondary' => 'border border-[var(--surface-border-strong)] bg-[var(--surface-panel)] text-[var(--text-base)] hover:bg-[var(--surface-muted)]',
        'danger' => 'bg-red-600 text-white hover:bg-red-700 focus-visible:outline-red-600 shadow-sm',
        'ghost' => 'text-[var(--text-muted)] hover:bg-[var(--surface-muted)] hover:text-[var(--text-strong)]',
        'link' => 'text-brand-600 hover:text-brand-700 hover:underline dark:text-brand-400',
    ];

    $sizes = [
        'sm' => 'gap-1.5 px-2.5 py-1.5 text-xs',
        'md' => 'gap-2 px-3.5 py-2 text-sm',
        'lg' => 'gap-2 px-4 py-2.5 text-sm',
        'icon' => 'p-2',
    ];

    $classes = implode(' ', [
        'inline-flex items-center justify-center rounded-lg font-medium transition',
        'focus-visible:outline focus-visible:outline-2 focus-visible:outline-offset-2',
        'disabled:cursor-not-allowed disabled:opacity-50',
        $variants[$variant] ?? $variants['primary'],
        $sizes[$size] ?? $sizes['md'],
    ]);
@endphp

@if ($href)
    <a href="{{ $href }}" {{ $attributes->merge(['class' => $classes]) }}>
        @if ($icon)
            <x-nexor::admin.icon :name="$icon" class="size-4" />
        @endif
        {{ $slot }}
    </a>
@else
    <button type="{{ $type }}" {{ $attributes->merge(['class' => $classes]) }}>
        @if ($icon)
            <x-nexor::admin.icon :name="$icon" class="size-4" />
        @endif
        {{ $slot }}
    </button>
@endif
