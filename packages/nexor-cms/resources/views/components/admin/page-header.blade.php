@props(['title', 'description' => null, 'back' => null])

<div {{ $attributes->merge(['class' => 'mb-6 flex flex-wrap items-start justify-between gap-4']) }}>
    <div class="min-w-0">
        @isset($breadcrumbs)
            <div class="mb-2">{{ $breadcrumbs }}</div>
        @endisset

        <div class="flex items-center gap-3">
            @if ($back)
                <a href="{{ $back }}"
                   class="flex size-8 shrink-0 items-center justify-center rounded-lg border border-[var(--surface-border-strong)] text-[var(--text-muted)] transition hover:bg-[var(--surface-muted)] hover:text-[var(--text-strong)]"
                   title="Назад">
                    <x-nexor::admin.icon name="chevron-left" class="size-4" />
                </a>
            @endif

            <h1 class="truncate text-xl font-semibold text-[var(--text-strong)]">{{ $title }}</h1>
        </div>

        @if ($description)
            <p class="mt-1.5 text-sm text-[var(--text-muted)]">{{ $description }}</p>
        @endif
    </div>

    @isset($actions)
        <div class="flex shrink-0 flex-wrap items-center gap-2">{{ $actions }}</div>
    @endisset
</div>
