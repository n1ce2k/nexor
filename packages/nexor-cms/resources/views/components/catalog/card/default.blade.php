{{--
    Карточка одного элемента.

    Подключается списком через проп `card`, поэтому свою карточку можно
    подставить, не копируя шаблон списка целиком:

        <x-nexor::catalog.section iblock="katalog" card="katalog.card" />
--}}

<a href="{{ $element->url() }}"
   class="group flex flex-col rounded-2xl border border-slate-200 p-6 transition hover:border-brand-300 hover:shadow-md">
    @if ($element->preview_picture_url)
        <img src="{{ $element->preview_picture_url }}" alt="{{ $element->name }}"
             class="mb-4 h-40 w-full rounded-xl object-cover">
    @endif

    <h2 class="text-lg font-semibold text-slate-900 transition group-hover:text-brand-600">
        {{ $element->name }}
    </h2>

    @if ($element->preview_text)
        <p class="mt-2 text-sm text-slate-600">{{ Str::limit(strip_tags($element->preview_text), 140) }}</p>
    @endif

    {{-- Цена — только у элементов торгового каталога. --}}
    @if ($element->catalog?->hasPrice())
        <div class="mt-auto flex items-baseline gap-2 pt-4">
            <span class="text-lg font-semibold text-slate-900">
                {{ $element->catalog->withCurrency($element->catalog->finalPrice()) }}
            </span>

            @if ($element->catalog->hasDiscount())
                <span class="text-sm text-slate-400 line-through">
                    {{ $element->catalog->withCurrency((float) $element->catalog->price) }}
                </span>
            @endif
        </div>
    @endif
</a>
