@props(['color' => 'gray', 'icon' => null])

@php
    $colors = [
        'gray' => 'bg-slate-100 text-slate-700 dark:bg-slate-800 dark:text-slate-300',
        'green' => 'bg-emerald-100 text-emerald-700 dark:bg-emerald-500/15 dark:text-emerald-400',
        'red' => 'bg-red-100 text-red-700 dark:bg-red-500/15 dark:text-red-400',
        'amber' => 'bg-amber-100 text-amber-700 dark:bg-amber-500/15 dark:text-amber-400',
        'blue' => 'bg-brand-100 text-brand-700 dark:bg-brand-500/15 dark:text-brand-300',
        'violet' => 'bg-violet-100 text-violet-700 dark:bg-violet-500/15 dark:text-violet-400',
    ];
@endphp

<span {{ $attributes->merge([
    'class' => 'inline-flex items-center gap-1 rounded-md px-2 py-0.5 text-xs font-medium '.($colors[$color] ?? $colors['gray']),
]) }}>
    @if ($icon)
        <x-nexor::admin.icon :name="$icon" class="size-3" />
    @endif
    {{ $slot }}
</span>
