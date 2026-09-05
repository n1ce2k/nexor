@props(['paginator'])

@if ($paginator->hasPages() || $paginator->total() > 0)
    <div class="flex flex-wrap items-center justify-between gap-3 border-t border-[var(--surface-border)] px-4 py-3 text-sm">
        <p class="text-xs text-[var(--text-muted)]">
            Показано {{ $paginator->firstItem() ?? 0 }}–{{ $paginator->lastItem() ?? 0 }}
            из {{ $paginator->total() }}
        </p>

        @if ($paginator->hasPages())
            <nav class="flex items-center gap-1">
                @if ($paginator->onFirstPage())
                    <span class="flex size-8 items-center justify-center rounded-lg text-[var(--text-faint)]">
                        <x-nexor::admin.icon name="chevron-left" class="size-4" />
                    </span>
                @else
                    <a href="{{ $paginator->previousPageUrl() }}" rel="prev"
                       class="flex size-8 items-center justify-center rounded-lg text-[var(--text-muted)] transition hover:bg-[var(--surface-muted)] hover:text-[var(--text-strong)]">
                        <x-nexor::admin.icon name="chevron-left" class="size-4" />
                    </a>
                @endif

                @foreach ($paginator->onEachSide(1)->linkCollection()->slice(1, -1) as $item)
                    @if ($item['label'] === '...')
                        <span class="px-2 text-[var(--text-faint)]">…</span>
                    @elseif ($item['active'])
                        <span class="flex size-8 items-center justify-center rounded-lg bg-brand-600 text-xs font-semibold text-white">
                            {{ $item['label'] }}
                        </span>
                    @else
                        <a href="{{ $item['url'] }}"
                           class="flex size-8 items-center justify-center rounded-lg text-xs font-medium text-[var(--text-muted)] transition hover:bg-[var(--surface-muted)] hover:text-[var(--text-strong)]">
                            {{ $item['label'] }}
                        </a>
                    @endif
                @endforeach

                @if ($paginator->hasMorePages())
                    <a href="{{ $paginator->nextPageUrl() }}" rel="next"
                       class="flex size-8 items-center justify-center rounded-lg text-[var(--text-muted)] transition hover:bg-[var(--surface-muted)] hover:text-[var(--text-strong)]">
                        <x-nexor::admin.icon name="chevron-right" class="size-4" />
                    </a>
                @else
                    <span class="flex size-8 items-center justify-center rounded-lg text-[var(--text-faint)]">
                        <x-nexor::admin.icon name="chevron-right" class="size-4" />
                    </span>
                @endif
            </nav>
        @endif
    </div>
@endif
