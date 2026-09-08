{{--
    Карточка новости: дата и анонс вместо картинки товара.

    Подставляется списком через проп `card`.
--}}

<article class="border-b border-slate-100 py-5 last:border-0">
    <time datetime="{{ $element->created_at?->toDateString() }}" class="text-xs text-slate-400">
        {{ $element->created_at?->format('d.m.Y') }}
    </time>

    <h2 class="mt-1 text-lg font-semibold text-slate-900">
        <a href="{{ $element->url() }}" class="transition hover:text-brand-600">{{ $element->name }}</a>
    </h2>

    @if ($element->preview_text)
        <p class="mt-2 text-sm text-slate-600">{{ Str::limit(strip_tags($element->preview_text), 220) }}</p>
    @endif
</article>
