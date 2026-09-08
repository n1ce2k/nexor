{{--
    Шаблон компонента news.list: короткий список ссылок с датами.

    Для боковой колонки и подвала, где место дороже анонса.
--}}

<ul id="nexor-items" class="space-y-2">
    @forelse ($elements as $element)
        <li class="flex items-baseline gap-3">
            <time datetime="{{ $element->created_at?->toDateString() }}"
                  class="shrink-0 text-xs text-slate-400">
                {{ $element->created_at?->format('d.m.Y') }}
            </time>

            <a href="{{ $element->url() }}" class="text-sm text-slate-700 transition hover:text-brand-600">
                {{ $element->name }}
            </a>
        </li>
    @empty
        <li class="text-sm text-slate-500">Новостей пока нет.</li>
    @endforelse
</ul>

@if ($elements instanceof \Illuminate\Contracts\Pagination\LengthAwarePaginator)
    <x-nexor::pagination :paginator="$elements" :iblock="$block" />
@endif
