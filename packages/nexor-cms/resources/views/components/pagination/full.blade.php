{{--
    Шаблон компонента pagination: номера, переходы в начало и конец,
    подпись «показано N из M».
--}}

@if ($paginator && $paginator->total() > 0)
    <div class="mt-10 flex flex-col items-center gap-4">
        @if ($paginator->hasPages())
            <nav class="flex items-center gap-1" aria-label="Страницы">
                <a href="{{ $paginator->url(1) }}"
                   class="flex h-9 items-center rounded-lg px-3 text-sm text-slate-600 transition hover:bg-slate-100 {{ $paginator->onFirstPage() ? 'pointer-events-none text-slate-300' : '' }}">
                    &laquo; Первая
                </a>

                @foreach ($paginator->onEachSide(2)->linkCollection()->slice(1, -1) as $link)
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

                <a href="{{ $paginator->url($paginator->lastPage()) }}"
                   class="flex h-9 items-center rounded-lg px-3 text-sm text-slate-600 transition hover:bg-slate-100 {{ $paginator->currentPage() === $paginator->lastPage() ? 'pointer-events-none text-slate-300' : '' }}">
                    Последняя &raquo;
                </a>
            </nav>
        @endif

        <p class="text-sm text-slate-500">
            Показано {{ $paginator->firstItem() ?? 0 }}–{{ $paginator->lastItem() ?? 0 }} из {{ $paginator->total() }}
        </p>
    </div>
@endif

@if ($showLoadMore && $paginator?->hasMorePages())
    @include('nexor::components.pagination.partials.load-more', ['paginator' => $paginator])
@endif
