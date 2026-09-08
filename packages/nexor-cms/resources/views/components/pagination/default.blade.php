{{--
    Шаблон компонента pagination: номера страниц со стрелками.

    Приходит: $paginator, $iblock, $showLoadMore.

    Свой шаблон: php artisan nexor:component pagination
--}}

@if ($paginator && $paginator->hasPages())
    <nav class="mt-10 flex items-center justify-center gap-1" aria-label="Страницы">
        @if ($paginator->onFirstPage())
            <span class="flex size-9 items-center justify-center rounded-lg text-slate-300">&lsaquo;</span>
        @else
            <a href="{{ $paginator->previousPageUrl() }}" rel="prev"
               class="flex size-9 items-center justify-center rounded-lg text-slate-600 transition hover:bg-slate-100">&lsaquo;</a>
        @endif

        @foreach ($paginator->onEachSide(1)->linkCollection()->slice(1, -1) as $link)
            @if ($link['label'] === '...')
                <span class="px-2 text-slate-400">…</span>
            @elseif ($link['active'])
                <span class="flex size-9 items-center justify-center rounded-lg bg-brand-600 text-sm font-semibold text-white">
                    {{ $link['label'] }}
                </span>
            @else
                <a href="{{ $link['url'] }}"
                   class="flex size-9 items-center justify-center rounded-lg text-sm text-slate-600 transition hover:bg-slate-100">
                    {{ $link['label'] }}
                </a>
            @endif
        @endforeach

        @if ($paginator->hasMorePages())
            <a href="{{ $paginator->nextPageUrl() }}" rel="next"
               class="flex size-9 items-center justify-center rounded-lg text-slate-600 transition hover:bg-slate-100">&rsaquo;</a>
        @else
            <span class="flex size-9 items-center justify-center rounded-lg text-slate-300">&rsaquo;</span>
        @endif
    </nav>
@endif

@if ($showLoadMore && $paginator?->hasMorePages())
    @include('nexor::components.pagination.partials.load-more', ['paginator' => $paginator])
@endif
