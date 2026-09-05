@props(['href' => null, 'icon' => null, 'danger' => false, 'type' => 'button'])

@php
    $classes = 'flex w-full items-center gap-2.5 rounded-lg px-3 py-2 text-sm transition '
        .($danger
            ? 'text-red-600 hover:bg-red-50 dark:text-red-400 dark:hover:bg-red-500/10'
            : 'text-[var(--text-base)] hover:bg-[var(--surface-muted)] hover:text-[var(--text-strong)]');
@endphp

@if ($href)
    <a href="{{ $href }}" {{ $attributes->merge(['class' => $classes]) }}>
        @if ($icon)
            <x-nexor::admin.icon :name="$icon" class="size-4 shrink-0" />
        @endif
        {{ $slot }}
    </a>
@else
    <button type="{{ $type }}" {{ $attributes->merge(['class' => $classes]) }}>
        @if ($icon)
            <x-nexor::admin.icon :name="$icon" class="size-4 shrink-0" />
        @endif
        {{ $slot }}
    </button>
@endif
