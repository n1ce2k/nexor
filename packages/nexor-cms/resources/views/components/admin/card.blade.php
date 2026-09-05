@props(['title' => null, 'description' => null, 'padding' => true])

<section {{ $attributes->merge(['class' => 'surface rounded-[var(--radius-card)] border shadow-sm']) }}>
    @if ($title || isset($actions))
        <header class="flex items-start justify-between gap-4 border-b border-[var(--surface-border)] px-5 py-4">
            <div>
                @if ($title)
                    <h2 class="text-sm font-semibold text-[var(--text-strong)]">{{ $title }}</h2>
                @endif
                @if ($description)
                    <p class="mt-1 text-xs text-[var(--text-muted)]">{{ $description }}</p>
                @endif
            </div>
            @isset($actions)
                <div class="flex shrink-0 items-center gap-2">{{ $actions }}</div>
            @endisset
        </header>
    @endif

    <div class="{{ $padding ? 'p-5' : '' }}">
        {{ $slot }}
    </div>

    @isset($footer)
        <footer class="flex items-center justify-end gap-2 border-t border-[var(--surface-border)] px-5 py-4">
            {{ $footer }}
        </footer>
    @endisset
</section>
