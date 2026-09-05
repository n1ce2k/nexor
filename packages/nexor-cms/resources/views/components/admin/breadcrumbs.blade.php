@props(['items' => []])

<nav aria-label="Хлебные крошки" {{ $attributes->merge(['class' => 'flex items-center gap-1.5 text-xs text-[var(--text-muted)]']) }}>
    @foreach ($items as $label => $url)
        @if (! $loop->first)
            <x-nexor::admin.icon name="chevron-right" class="size-3 opacity-50" />
        @endif

        @if ($url && ! $loop->last)
            <a href="{{ $url }}" class="truncate transition hover:text-[var(--text-strong)]">{{ $label }}</a>
        @else
            <span class="truncate text-[var(--text-base)]">{{ $label }}</span>
        @endif
    @endforeach
</nav>
