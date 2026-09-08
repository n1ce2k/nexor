{{--
    Шаблон компонента catalog.section-list: карточки подразделов.

    Приходит: $sections (прямые потомки), $parent (раздел, чьи это дети),
    $block, $showCount.

    Свой шаблон: php artisan nexor:component catalog.section-list my_template
--}}

@if ($sections)
    <div class="grid gap-6 sm:grid-cols-2 lg:grid-cols-3">
        @foreach ($sections as $item)
            <a href="{{ $item['url'] }}"
               class="group flex flex-col rounded-2xl border border-slate-200 p-6 transition hover:border-brand-300 hover:shadow-md">
                @if ($item['picture'])
                    <img src="{{ $item['picture'] }}" alt="{{ $item['name'] }}"
                         class="mb-4 h-32 w-full rounded-xl object-cover">
                @endif

                <h3 class="text-lg font-semibold text-slate-900 transition group-hover:text-brand-600">
                    {{ $item['name'] }}
                </h3>

                @if ($item['description'])
                    <p class="mt-2 text-sm text-slate-600">
                        {{ Str::limit(strip_tags($item['description']), 120) }}
                    </p>
                @endif

                @if ($showCount)
                    <span class="mt-3 text-xs text-slate-400">{{ $item['count'] }} шт.</span>
                @endif
            </a>
        @endforeach
    </div>
@endif
