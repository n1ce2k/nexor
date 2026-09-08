{{--
    Шаблон компонента search.page.

    Приходит: $query (что искали), $groups (результаты по инфоблокам),
    $only (код инфоблока, если выбран один), $searched.

    У группы: name, code, elements, paged, more, url.

    Свой шаблон: php artisan nexor:component search.page my_template
--}}

@if (! $searched)
    <p class="text-slate-500">Введите запрос, чтобы найти страницы, товары и новости.</p>
@elseif ($query === '')
    <p class="text-slate-500">Запрос пустой — напишите, что ищете.</p>
@elseif (! $groups)
    <p class="text-slate-500">По запросу «{{ $query }}» ничего не нашлось.</p>
@else
    <div class="space-y-10">
        @foreach ($groups as $group)
            <section>
                <h2 class="mb-3 text-sm font-semibold tracking-wide text-slate-500 uppercase">
                    {{ $group['name'] }}
                </h2>

                <ul id="nexor-items" class="divide-y divide-slate-100">
                    @foreach ($group['elements'] as $element)
                        <li class="py-3">
                            <a href="{{ $element->url() }}"
                               class="font-medium text-slate-900 transition hover:text-brand-600">
                                {{ $element->name }}
                            </a>

                            @if ($element->preview_text)
                                <p class="mt-1 text-sm text-slate-600">
                                    {{ Str::limit(strip_tags($element->preview_text), 180) }}
                                </p>
                            @endif
                        </li>
                    @endforeach
                </ul>

                @if ($group['more'])
                    <a href="{{ $group['url'] }}"
                       class="mt-3 inline-block text-sm font-medium text-brand-600 transition hover:underline">
                        Показать все результаты в разделе «{{ $group['name'] }}»
                    </a>
                @endif

                @if ($group['paged'])
                    <x-nexor::pagination :paginator="$group['elements']" :iblock="$group['iblock']" />
                @endif
            </section>
        @endforeach
    </div>
@endif
