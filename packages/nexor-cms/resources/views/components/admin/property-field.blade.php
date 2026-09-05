@props(['property', 'value' => null])

@php
    use Nexor\Cms\Enums\PropertyType;

    $type = $property->type;
    $code = $property->code;
    $name = "properties[{$code}]";
    $old = old("properties.{$code}", $value ?? $property->default_value);

    // Multiple non-file properties always render as a list of rows.
    $rows = $property->is_multiple && ! $type->isFile()
        ? array_values(array_filter((array) $old, fn ($v) => $v !== null && $v !== ''))
        : [];

    if ($property->is_multiple && ! $type->isFile() && $rows === []) {
        $rows = [''];
    }

    $enumOptions = $type->usesEnums()
        ? $property->enums->pluck('value', 'id')->all()
        : [];

    $linkOptions = in_array($type, [PropertyType::Element, PropertyType::Section], true)
        ? ($property->linkOptions ?? collect())->pluck('name', 'id')->all()
        : [];
@endphp

<x-nexor::admin.field :label="$property->name" :name="'properties.'.$code"
               :hint="$property->hint" :required="$property->is_required">

    @if ($type->isFile())
        @php $files = is_array($value) ? $value : []; @endphp

        <div class="space-y-3">
            @foreach ($files as $file)
                <div class="flex items-center gap-3 rounded-lg border border-[var(--surface-border)] p-2">
                    @if ($type === PropertyType::Image)
                        <img src="{{ Storage::disk('public')->url($file['path']) }}" alt=""
                             class="size-14 rounded object-cover">
                    @else
                        <span class="flex size-14 items-center justify-center rounded bg-[var(--surface-muted)] text-[var(--text-muted)]">
                            <x-nexor::admin.icon name="document" class="size-5" />
                        </span>
                    @endif

                    <a href="{{ Storage::disk('public')->url($file['path']) }}" target="_blank" rel="noopener"
                       class="min-w-0 flex-1 truncate text-sm text-brand-600 hover:underline dark:text-brand-400">
                        {{ basename($file['path']) }}
                    </a>

                    <label class="flex cursor-pointer items-center gap-1.5 text-xs text-red-600 dark:text-red-400">
                        <input type="checkbox" name="property_remove[{{ $code }}][]" value="{{ $file['id'] }}"
                               class="size-3.5 rounded border-[var(--surface-border-strong)] text-red-600 focus:ring-red-500/40">
                        удалить
                    </label>
                </div>
            @endforeach

            <label class="inline-flex cursor-pointer items-center gap-2 rounded-lg border border-[var(--surface-border-strong)] px-3 py-2 text-sm font-medium text-[var(--text-base)] transition hover:bg-[var(--surface-muted)]">
                <x-nexor::admin.icon name="upload" class="size-4" />
                {{ $files ? 'Добавить ещё' : 'Выбрать файл' }}
                <input type="file"
                       name="property_files[{{ $code }}]{{ $property->is_multiple ? '[]' : '' }}"
                       @if ($property->is_multiple) multiple @endif
                       accept="{{ $property->setting('accept', $type === PropertyType::Image ? 'image/*' : null) }}"
                       class="sr-only">
            </label>
        </div>

    @elseif ($property->is_multiple)
        <div x-data="repeater(@js(array_map(fn ($v) => ['value' => $v], $rows)), { value: '' })" class="space-y-2">
            <template x-for="(row, index) in rows" :key="index">
                <div class="flex items-center gap-2">
                    @switch (true)
                        @case ($type->usesEnums())
                            <select name="{{ $name }}[]" x-model="row.value" class="field-input">
                                <option value="">— не выбрано —</option>
                                @foreach ($enumOptions as $id => $label)
                                    <option value="{{ $id }}">{{ $label }}</option>
                                @endforeach
                            </select>
                            @break

                        @case (in_array($type, [PropertyType::Element, PropertyType::Section], true))
                            <select name="{{ $name }}[]" x-model="row.value" class="field-input">
                                <option value="">— не выбрано —</option>
                                @foreach ($linkOptions as $id => $label)
                                    <option value="{{ $id }}">{{ $label }}</option>
                                @endforeach
                            </select>
                            @break

                        @case ($type === PropertyType::Text || $type === PropertyType::Html)
                            <textarea name="{{ $name }}[]" x-model="row.value"
                                      rows="{{ $property->setting('rows', 3) }}" class="field-input resize-y"></textarea>
                            @break

                        @default
                            <input type="{{ match ($type) {
                                PropertyType::Integer, PropertyType::Decimal => 'number',
                                PropertyType::Date => 'date',
                                PropertyType::DateTime => 'datetime-local',
                                PropertyType::Color => 'text',
                                default => 'text',
                            } }}"
                                   name="{{ $name }}[]" x-model="row.value"
                                   step="{{ $type === PropertyType::Decimal ? ($property->setting('step') ?: 'any') : ($property->setting('step') ?: null) }}"
                                   placeholder="{{ $property->setting('placeholder') }}"
                                   class="field-input">
                    @endswitch

                    <button type="button" @click="remove(index)"
                            class="shrink-0 rounded-lg p-2 text-[var(--text-muted)] transition hover:bg-red-50 hover:text-red-600 dark:hover:bg-red-500/10">
                        <x-nexor::admin.icon name="trash" class="size-4" />
                    </button>
                </div>
            </template>

            <button type="button" @click="add()"
                    class="inline-flex items-center gap-1.5 text-xs font-medium text-brand-600 transition hover:underline dark:text-brand-400">
                <x-nexor::admin.icon name="plus" class="size-3.5" />
                Добавить значение
            </button>
        </div>

    @else
        @switch ($type)
            @case (PropertyType::Text)
            @case (PropertyType::Html)
                <x-nexor::admin.textarea :name="$name" :value="$old"
                                  :rows="$property->setting('rows', $type === PropertyType::Html ? 10 : 4)"
                                  :placeholder="$property->setting('placeholder')"
                                  :class="$type === PropertyType::Html ? 'font-mono text-xs' : ''" />
                @break

            @case (PropertyType::Boolean)
                <x-nexor::admin.toggle :name="$name" :checked="(bool) $old" label="Да" />
                @break

            @case (PropertyType::Color)
                <x-nexor::admin.color-input :name="$name" :value="$old" />
                @break

            @case (PropertyType::Select)
                <x-nexor::admin.select :name="$name" :selected="$old" placeholder="— не выбрано —" :options="$enumOptions" />
                @break

            @case (PropertyType::Element)
            @case (PropertyType::Section)
                <x-nexor::admin.select :name="$name" :selected="$old" placeholder="— не выбрано —" :options="$linkOptions" />
                @break

            @case (PropertyType::User)
                <x-nexor::admin.select :name="$name" :selected="$old" placeholder="— не выбрано —"
                                :options="\App\Models\User::query()->active()->orderBy('name')->pluck('name', 'id')->all()" />
                @break

            @case (PropertyType::Integer)
                <x-nexor::admin.input :name="$name" type="number" :value="$old"
                               :min="$property->setting('min')" :max="$property->setting('max')"
                               :step="$property->setting('step', 1)"
                               :suffix="$property->setting('suffix')" />
                @break

            @case (PropertyType::Decimal)
                <x-nexor::admin.input :name="$name" type="number" :value="$old"
                               :min="$property->setting('min')" :max="$property->setting('max')"
                               :step="$property->setting('step', 'any')"
                               :suffix="$property->setting('suffix')" />
                @break

            @case (PropertyType::Date)
                <x-nexor::admin.input :name="$name" type="date"
                               :value="$old ? \Illuminate\Support\Carbon::parse($old)->format('Y-m-d') : null" />
                @break

            @case (PropertyType::DateTime)
                <x-nexor::admin.input :name="$name" type="datetime-local"
                               :value="$old ? \Illuminate\Support\Carbon::parse($old)->format('Y-m-d\TH:i') : null" />
                @break

            @case (PropertyType::Json)
                <x-nexor::admin.textarea :name="$name" rows="6" class="font-mono text-xs"
                                  :value="is_array($old) ? json_encode($old, JSON_PRETTY_PRINT | JSON_UNESCAPED_UNICODE) : $old" />
                @break

            @default
                <x-nexor::admin.input :name="$name" :value="$old"
                               :placeholder="$property->setting('placeholder')"
                               :maxlength="$property->setting('max_length')"
                               :pattern="$property->setting('pattern')" />
        @endswitch
    @endif
</x-nexor::admin.field>
