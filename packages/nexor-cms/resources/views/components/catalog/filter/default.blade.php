{{--
    Шаблон компонента catalog.filter.

    Приходит: $properties (свойства с галочкой «участвует в фильтре»),
    $options (варианты списков), $values (текущий запрос), $formAction,
    $currentSection.

    Форма отправляется методом GET, поэтому выбор виден в адресе и работает
    кнопка «назад». Список читает те же параметры сам.
--}}

@if ($properties->isNotEmpty())
    <form method="get" action="{{ $formAction }}" class="mb-8 space-y-4 rounded-2xl border border-slate-200 p-5">
        {{-- Раздел выбирают не здесь, но при отправке его терять нельзя. --}}
        @if ($currentSection !== '')
            <input type="hidden" name="section" value="{{ $currentSection }}">
        @endif

        @foreach ($properties as $property)
            <div>
                <p class="mb-1.5 text-sm font-medium text-slate-900">{{ $property->name }}</p>

                @if ($property->type->usesEnums())
                    <div class="space-y-1.5">
                        @foreach ($options[$property->code] ?? [] as $option)
                            <label class="flex cursor-pointer items-center gap-2 text-sm text-slate-600">
                                <input type="checkbox" name="{{ $property->code }}[]" value="{{ $option['value'] }}"
                                       @checked(in_array($option['value'], (array) ($values[$property->code] ?? []), true))
                                       class="size-4 rounded border-slate-300 text-brand-600 focus:ring-2 focus:ring-brand-500/40">
                                {{ $option['value'] }}
                            </label>
                        @endforeach
                    </div>
                @elseif (in_array($property->type->value, ['integer', 'decimal'], true))
                    <div class="flex items-center gap-2">
                        <input type="number" name="{{ $property->code }}_FROM" placeholder="от"
                               value="{{ $values[$property->code.'_FROM'] ?? '' }}"
                               class="w-full rounded-lg border border-slate-300 px-3 py-2 text-sm">
                        <span class="text-slate-400">—</span>
                        <input type="number" name="{{ $property->code }}_TO" placeholder="до"
                               value="{{ $values[$property->code.'_TO'] ?? '' }}"
                               class="w-full rounded-lg border border-slate-300 px-3 py-2 text-sm">
                    </div>
                @else
                    <input type="text" name="{{ $property->code }}" value="{{ $values[$property->code] ?? '' }}"
                           class="w-full rounded-lg border border-slate-300 px-3 py-2 text-sm">
                @endif
            </div>
        @endforeach

        <div class="flex items-center gap-2 pt-1">
            <button type="submit"
                    class="rounded-lg bg-brand-600 px-4 py-2 text-sm font-medium text-white transition hover:bg-brand-700">
                Показать
            </button>

            <a href="{{ $formAction }}" class="text-sm text-slate-500 transition hover:text-slate-900">Сбросить</a>
        </div>
    </form>
@endif
