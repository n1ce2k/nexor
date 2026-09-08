{{--
    Кнопка «Показать ещё», общая для всех шаблонов пагинации.

    Работает и без JavaScript: это обычная ссылка на ?page=N. Скрипт лишь
    заменяет переход дописыванием следующей порции в #nexor-items.
--}}

<div class="mt-10 flex flex-col items-center gap-3" data-nexor-loadmore>
    <a href="{{ $paginator->nextPageUrl() }}" rel="next"
       class="inline-flex items-center gap-2 rounded-lg border border-slate-300 px-6 py-3 text-sm font-medium text-slate-700 transition hover:bg-slate-50">
        Показать ещё
    </a>

    <p class="text-xs text-slate-500">
        Показано {{ $paginator->lastItem() ?? 0 }} из {{ $paginator->total() }}
    </p>
</div>

@once
    @push('scripts')
        <script>
            document.addEventListener('click', async (event) => {
                const link = event.target.closest('[data-nexor-loadmore] a');

                if (!link) {
                    return;
                }

                event.preventDefault();

                const block = link.closest('[data-nexor-loadmore]');
                const list = document.querySelector('#nexor-items');

                if (!list) {
                    return;
                }

                const label = link.textContent;

                link.textContent = 'Загружаем…';

                try {
                    const html = await fetch(link.href, { headers: { 'X-Requested-With': 'fetch' } })
                        .then((response) => response.text());

                    const page = new DOMParser().parseFromString(html, 'text/html');

                    list.append(...page.querySelectorAll('#nexor-items > *'));

                    const next = page.querySelector('[data-nexor-loadmore]');

                    next ? block.replaceWith(next) : block.remove();

                    history.replaceState(null, '', link.href);
                } catch (error) {
                    link.textContent = label;
                }
            });
        </script>
    @endpush
@endonce
