@props(['icon' => 'document', 'title' => 'Пока пусто', 'description' => null])

<div {{ $attributes->merge(['class' => 'flex flex-col items-center justify-center px-6 py-14 text-center']) }}>
    <span class="flex size-12 items-center justify-center rounded-full bg-[var(--surface-muted)] text-[var(--text-faint)]">
        <x-nexor::admin.icon :name="$icon" class="size-6" />
    </span>

    <h3 class="mt-4 text-sm font-semibold text-[var(--text-strong)]">{{ $title }}</h3>

    @if ($description)
        <p class="mt-1 max-w-sm text-sm text-[var(--text-muted)]">{{ $description }}</p>
    @endif

    @if ($slot->isNotEmpty())
        <div class="mt-5 flex items-center gap-2">{{ $slot }}</div>
    @endif
</div>
