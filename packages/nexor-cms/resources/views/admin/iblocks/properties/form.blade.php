@extends('nexor::admin.layouts.app')

@section('title', $property->exists ? 'Изменение свойства' : 'Новое свойство')

@php
    use Nexor\Cms\Enums\PropertyType;

    $typeOptions = collect(PropertyType::grouped())
        ->map(fn ($cases) => collect($cases)->mapWithKeys(fn (PropertyType $case) => [$case->value => $case->label()])->all())
        ->all();

    $settingKeys = collect(PropertyType::cases())
        ->mapWithKeys(fn (PropertyType $case) => [$case->value => $case->settingKeys()])
        ->all();

    $enumTypes = collect(PropertyType::cases())
        ->filter(fn (PropertyType $case) => $case->usesEnums())
        ->map->value->values()->all();

    $currentType = old('type', $property->type?->value ?? PropertyType::String->value);

    $enumRows = old('enums', ($property->exists ? $property->enums : collect())->map(fn ($enum) => [
        'id' => $enum->id,
        'value' => $enum->value,
        'code' => $enum->code,
        'sort' => $enum->sort,
        'is_default' => $enum->is_default,
    ])->all());
@endphp

@section('content')
    <x-nexor::admin.page-header :title="$property->exists ? $property->name : 'Новое свойство'"
                         :back="route('admin.iblocks.properties.index', $iblock)"
                         :description="'Инфоблок «'.$iblock->name.'»'">
        <x-slot:breadcrumbs>
            <x-nexor::admin.breadcrumbs :items="[
                'Инфоблоки' => route('admin.iblocks.index'),
                $iblock->name => route('admin.iblocks.edit', $iblock),
                'Свойства' => route('admin.iblocks.properties.index', $iblock),
                ($property->exists ? $property->name : 'Новое') => null,
            ]" />
        </x-slot:breadcrumbs>
    </x-nexor::admin.page-header>

    <form method="POST"
          action="{{ $property->exists
              ? route('admin.iblocks.properties.update', [$iblock, $property])
              : route('admin.iblocks.properties.store', $iblock) }}"
          x-data="{
              type: @js($currentType),
              settingKeys: @js($settingKeys),
              enumTypes: @js($enumTypes),
              has(key) { return (this.settingKeys[this.type] || []).includes(key) },
              get hasSettings() { return (this.settingKeys[this.type] || []).length > 0 },
              get usesEnums() { return this.enumTypes.includes(this.type) },
          }"
          class="grid gap-6 lg:grid-cols-3">
        @csrf
        @if ($property->exists)
            @method('PUT')
        @endif

        <div class="space-y-6 lg:col-span-2">
            <x-nexor::admin.card title="Основное">
                <div class="grid gap-5 sm:grid-cols-2"
                     x-data="slugField(@js(old('code', $property->code)))">
                    <x-nexor::admin.field label="Название" name="name" required>
                        <x-nexor::admin.input name="name" :value="old('name', $property->name)" required
                                       @input="fromName($event)" />
                    </x-nexor::admin.field>

                    <x-nexor::admin.field label="Код свойства" name="code" required
                                   hint="Латиница, цифры, подчёркивание. Обращение в шаблоне: $element->property('CODE').">
                        <x-nexor::admin.input name="code" x-model="code" @input="markTouched(); code = code.toUpperCase().replace(/-/g, '_')"
                                       class="font-mono uppercase" required />
                    </x-nexor::admin.field>

                    <x-nexor::admin.field label="Тип данных" name="type" required class="sm:col-span-2">
                        <x-nexor::admin.select name="type" x-model="type" :selected="$currentType"
                                        :options="$typeOptions" required />
                    </x-nexor::admin.field>

                    <x-nexor::admin.field label="Подсказка для редактора" name="hint" class="sm:col-span-2">
                        <x-nexor::admin.input name="hint" :value="old('hint', $property->hint)"
                                       placeholder="Короткое пояснение под полем" />
                    </x-nexor::admin.field>

                    <x-nexor::admin.field label="Значение по умолчанию" name="default_value" class="sm:col-span-2">
                        <x-nexor::admin.input name="default_value" :value="old('default_value', $property->default_value)" />
                    </x-nexor::admin.field>

                    <x-nexor::admin.field label="Описание" name="description" class="sm:col-span-2"
                                   hint="Видно редактору под полем и доступно шаблонам сайта как description.">
                        <x-nexor::admin.textarea name="description" rows="3"
                                          :value="old('description', $property->description)" />
                    </x-nexor::admin.field>
                </div>
            </x-nexor::admin.card>

            {{-- Type-specific settings; only the keys the chosen type understands are shown. --}}
            <div x-show="hasSettings" x-cloak>
                <x-nexor::admin.card title="Параметры типа">
                    <div class="grid gap-5 sm:grid-cols-2">
                        <x-nexor::admin.field label="Placeholder" name="settings[placeholder]" x-show="has('placeholder')" x-cloak>
                            <x-nexor::admin.input name="settings[placeholder]" :value="old('settings.placeholder', $property->setting('placeholder'))" />
                        </x-nexor::admin.field>

                        <x-nexor::admin.field label="Максимальная длина" name="settings[max_length]" x-show="has('max_length')" x-cloak>
                            <x-nexor::admin.input name="settings[max_length]" type="number" min="1"
                                           :value="old('settings.max_length', $property->setting('max_length'))" />
                        </x-nexor::admin.field>

                        <x-nexor::admin.field label="Регулярное выражение" name="settings[pattern]" x-show="has('pattern')" x-cloak
                                       hint="Проверка формата, например ^[A-Z]{2}[0-9]{4}$">
                            <x-nexor::admin.input name="settings[pattern]" class="font-mono"
                                           :value="old('settings.pattern', $property->setting('pattern'))" />
                        </x-nexor::admin.field>

                        <x-nexor::admin.field label="Высота поля (строк)" name="settings[rows]" x-show="has('rows')" x-cloak>
                            <x-nexor::admin.input name="settings[rows]" type="number" min="1" max="50"
                                           :value="old('settings.rows', $property->setting('rows', 4))" />
                        </x-nexor::admin.field>

                        <x-nexor::admin.field label="Минимум" name="settings[min]" x-show="has('min')" x-cloak>
                            <x-nexor::admin.input name="settings[min]" type="number" step="any"
                                           :value="old('settings.min', $property->setting('min'))" />
                        </x-nexor::admin.field>

                        <x-nexor::admin.field label="Максимум" name="settings[max]" x-show="has('max')" x-cloak>
                            <x-nexor::admin.input name="settings[max]" type="number" step="any"
                                           :value="old('settings.max', $property->setting('max'))" />
                        </x-nexor::admin.field>

                        <x-nexor::admin.field label="Шаг" name="settings[step]" x-show="has('step')" x-cloak>
                            <x-nexor::admin.input name="settings[step]" type="number" step="any"
                                           :value="old('settings.step', $property->setting('step'))" />
                        </x-nexor::admin.field>

                        <x-nexor::admin.field label="Единица измерения" name="settings[suffix]" x-show="has('suffix')" x-cloak
                                       hint="Показывается справа от поля: кг, ₽, %">
                            <x-nexor::admin.input name="settings[suffix]" :value="old('settings.suffix', $property->setting('suffix'))" />
                        </x-nexor::admin.field>

                        <x-nexor::admin.field label="Допустимые форматы" name="settings[accept]" x-show="has('accept')" x-cloak
                                       hint="Например: image/*, .pdf,.docx">
                            <x-nexor::admin.input name="settings[accept]" class="font-mono"
                                           :value="old('settings.accept', $property->setting('accept'))" />
                        </x-nexor::admin.field>

                        <x-nexor::admin.field label="Макс. размер, КБ" name="settings[max_size]" x-show="has('max_size')" x-cloak>
                            <x-nexor::admin.input name="settings[max_size]" type="number" min="1"
                                           :value="old('settings.max_size', $property->setting('max_size', 4096))" />
                        </x-nexor::admin.field>

                        <x-nexor::admin.field label="Макс. ширина, px" name="settings[max_width]" x-show="has('max_width')" x-cloak>
                            <x-nexor::admin.input name="settings[max_width]" type="number" min="1"
                                           :value="old('settings.max_width', $property->setting('max_width'))" />
                        </x-nexor::admin.field>

                        <x-nexor::admin.field label="Макс. высота, px" name="settings[max_height]" x-show="has('max_height')" x-cloak>
                            <x-nexor::admin.input name="settings[max_height]" type="number" min="1"
                                           :value="old('settings.max_height', $property->setting('max_height'))" />
                        </x-nexor::admin.field>

                        <x-nexor::admin.field label="Связанный инфоблок" name="settings[link_iblock_id]"
                                       x-show="has('link_iblock_id')" x-cloak class="sm:col-span-2"
                                       hint="Из какого инфоблока выбирать значение.">
                            <x-nexor::admin.select name="settings[link_iblock_id]"
                                            :selected="old('settings.link_iblock_id', $property->setting('link_iblock_id'))"
                                            placeholder="Выберите инфоблок"
                                            :options="$linkableIblocks->pluck('name', 'id')->all()" />
                        </x-nexor::admin.field>
                    </div>
                </x-nexor::admin.card>
            </div>

            {{-- Enum editor for list-type properties. --}}
            <div x-show="usesEnums" x-cloak>
                <x-nexor::admin.card title="Варианты списка"
                              description="Значения, из которых редактор выбирает при заполнении элемента."
                              x-data="repeater(@js(array_values($enumRows)), { id: '', value: '', code: '', sort: '', is_default: false })">
                    <x-slot:actions>
                        <x-nexor::admin.button type="button" size="sm" variant="secondary" icon="plus" @click="add()">
                            Добавить вариант
                        </x-nexor::admin.button>
                    </x-slot:actions>

                    <div class="space-y-2">
                        <div class="hidden gap-3 px-1 text-xs font-medium text-[var(--text-muted)] sm:grid sm:grid-cols-[1fr_1fr_5rem_5rem_2.5rem]">
                            <span>Значение</span>
                            <span>Код</span>
                            <span>Сорт.</span>
                            <span>По умолч.</span>
                            <span></span>
                        </div>

                        <template x-for="(row, index) in rows" :key="index">
                            <div class="grid gap-3 sm:grid-cols-[1fr_1fr_5rem_5rem_2.5rem] sm:items-center">
                                <input type="hidden" :name="`enums[${index}][id]`" :value="row.id">

                                <input type="text" :name="`enums[${index}][value]`" x-model="row.value"
                                       placeholder="Название варианта" class="field-input">

                                <input type="text" :name="`enums[${index}][code]`" x-model="row.code"
                                       placeholder="code" class="field-input font-mono">

                                <input type="number" :name="`enums[${index}][sort]`" x-model="row.sort"
                                       placeholder="500" min="0" class="field-input">

                                <label class="flex items-center justify-center">
                                    <input type="hidden" :name="`enums[${index}][is_default]`" value="0">
                                    <input type="checkbox" :name="`enums[${index}][is_default]`" value="1"
                                           x-model="row.is_default"
                                           class="size-4 rounded border-[var(--surface-border-strong)] text-brand-600 focus:ring-brand-500/40">
                                </label>

                                <button type="button" @click="remove(index)"
                                        class="flex items-center justify-center rounded-lg p-2 text-[var(--text-muted)] transition hover:bg-red-50 hover:text-red-600 dark:hover:bg-red-500/10">
                                    <x-nexor::admin.icon name="trash" class="size-4" />
                                </button>
                            </div>
                        </template>
                    </div>
                </x-nexor::admin.card>
            </div>
        </div>

        <div class="space-y-6">
            <x-nexor::admin.card title="Поведение">
                <div class="space-y-5">
                    <x-nexor::admin.toggle name="is_multiple" label="Множественное"
                                    hint="Можно задать несколько значений."
                                    :checked="old('is_multiple', $property->is_multiple ?? false)" />

                    <x-nexor::admin.toggle name="is_required" label="Обязательное"
                                    hint="Элемент нельзя сохранить без значения."
                                    :checked="old('is_required', $property->is_required ?? false)" />

                    <x-nexor::admin.toggle name="is_filterable" label="Участвует в фильтре"
                                    hint="Появится в фильтре списка элементов."
                                    :checked="old('is_filterable', $property->is_filterable ?? false)" />

                    <x-nexor::admin.toggle name="is_searchable" label="Участвует в поиске"
                                    :checked="old('is_searchable', $property->is_searchable ?? false)" />

                    <x-nexor::admin.toggle name="is_shown_in_list" label="Колонка в списке"
                                    hint="Значение будет видно прямо в таблице элементов."
                                    :checked="old('is_shown_in_list', $property->is_shown_in_list ?? false)" />

                    <x-nexor::admin.toggle name="is_active" label="Активно"
                                    :checked="old('is_active', $property->is_active ?? true)" />
                </div>
            </x-nexor::admin.card>

            <x-nexor::admin.card title="Сортировка">
                <x-nexor::admin.field name="sort" hint="Порядок поля в форме элемента.">
                    <x-nexor::admin.input name="sort" type="number" min="0" :value="old('sort', $property->sort ?? 500)" />
                </x-nexor::admin.field>
            </x-nexor::admin.card>

            <div class="flex items-center gap-2">
                <x-nexor::admin.button type="submit" size="lg">Сохранить</x-nexor::admin.button>
                <x-nexor::admin.button :href="route('admin.iblocks.properties.index', $iblock)" variant="secondary" size="lg">
                    Отмена
                </x-nexor::admin.button>
            </div>
        </div>
    </form>
@endsection
