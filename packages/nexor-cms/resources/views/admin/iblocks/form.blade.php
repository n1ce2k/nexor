@extends('nexor::admin.layouts.app')

@section('title', $iblock->exists ? 'Настройки инфоблока' : 'Новый инфоблок')

@section('content')
    <x-nexor::admin.page-header :title="$iblock->exists ? $iblock->name : 'Новый инфоблок'"
                         :back="route('admin.iblocks.index')"
                         description="Общие параметры инфоблока. Свойства настраиваются отдельно.">
        <x-slot:breadcrumbs>
            <x-nexor::admin.breadcrumbs :items="[
                'Инфоблоки' => route('admin.iblocks.index'),
                ($iblock->exists ? $iblock->name : 'Новый инфоблок') => null,
            ]" />
        </x-slot:breadcrumbs>

        @if ($iblock->exists)
            <x-slot:actions>
                <x-nexor::admin.button :href="route('admin.iblocks.properties.index', $iblock)" variant="secondary" icon="grip">
                    Свойства
                </x-nexor::admin.button>
                <x-nexor::admin.button :href="route('admin.iblocks.elements.index', $iblock)" variant="secondary" icon="document">
                    Наполнение
                </x-nexor::admin.button>
            </x-slot:actions>
        @endif
    </x-nexor::admin.page-header>

    <form method="POST"
          action="{{ $iblock->exists ? route('admin.iblocks.update', $iblock) : route('admin.iblocks.store') }}"
          enctype="multipart/form-data"
          x-data="slugField(@js(old('code', $iblock->code)))"
          class="grid gap-6 lg:grid-cols-3">
        @csrf
        @if ($iblock->exists)
            @method('PUT')
        @endif

        <div class="space-y-6 lg:col-span-2">
            <x-nexor::admin.card title="Основное">
                <div class="grid gap-5 sm:grid-cols-2">
                    <x-nexor::admin.field label="Название" name="name" required>
                        <x-nexor::admin.input name="name" :value="old('name', $iblock->name)" required @input="fromName($event)" />
                    </x-nexor::admin.field>

                    <x-nexor::admin.field label="Символьный код" name="code" required
                                   hint="Уникальный код инфоблока, используется в шаблонах.">
                        <x-nexor::admin.input name="code" x-model="code" @input="markTouched()" class="font-mono" required />
                    </x-nexor::admin.field>

                    <x-nexor::admin.field label="Тип инфоблока" name="iblock_type_id" required>
                        <x-nexor::admin.select name="iblock_type_id"
                                        :selected="old('iblock_type_id', $iblock->iblock_type_id)"
                                        placeholder="Выберите тип"
                                        :options="$types->pluck('name', 'id')->all()" required />
                    </x-nexor::admin.field>

                    <x-nexor::admin.field label="Сортировка" name="sort">
                        <x-nexor::admin.input name="sort" type="number" :value="old('sort', $iblock->sort ?? 500)" min="0" />
                    </x-nexor::admin.field>

                    <x-nexor::admin.field label="Описание" name="description" class="sm:col-span-2">
                        <x-nexor::admin.textarea name="description" :value="old('description', $iblock->description)" rows="3" />
                    </x-nexor::admin.field>
                </div>
            </x-nexor::admin.card>

            <x-nexor::admin.card title="Адреса на сайте"
                          description="Шаблоны URL для публичной части. Доступны подстановки #ID#, #CODE#, #SECTION_CODE#.">
                <div class="space-y-5">
                    <x-nexor::admin.field label="URL списка" name="list_url">
                        <x-nexor::admin.input name="list_url" :value="old('list_url', $iblock->list_url)"
                                       placeholder="/catalog" class="font-mono" />
                    </x-nexor::admin.field>

                    <x-nexor::admin.field label="URL раздела" name="section_url">
                        <x-nexor::admin.input name="section_url" :value="old('section_url', $iblock->section_url)"
                                       placeholder="/catalog/#SECTION_CODE#" class="font-mono" />
                    </x-nexor::admin.field>

                    <x-nexor::admin.field label="URL элемента" name="detail_url">
                        <x-nexor::admin.input name="detail_url" :value="old('detail_url', $iblock->detail_url)"
                                       placeholder="/catalog/#SECTION_CODE#/#CODE#" class="font-mono" />
                    </x-nexor::admin.field>
                </div>
            </x-nexor::admin.card>
        </div>

        <div class="space-y-6">
            <x-nexor::admin.card title="Параметры">
                <div class="space-y-5">
                    <x-nexor::admin.toggle name="has_sections" label="Использовать разделы"
                                    hint="Древовидная структура внутри инфоблока."
                                    :checked="old('has_sections', $iblock->has_sections ?? true)" />

                    <x-nexor::admin.toggle name="is_active" label="Активен"
                                    :checked="old('is_active', $iblock->is_active ?? true)" />
                </div>
            </x-nexor::admin.card>

            <x-nexor::admin.card title="Картинка">
                <x-nexor::admin.field name="picture">
                    <x-nexor::admin.file-input name="picture" :value="$iblock->picture" accept="image/*" />
                </x-nexor::admin.field>
            </x-nexor::admin.card>

            @if ($iblock->exists)
                <x-nexor::admin.card title="Права доступа">
                    <p class="text-sm text-[var(--text-muted)]">
                        Для этого инфоблока созданы отдельные права
                        (<code class="font-mono text-xs">{{ $iblock->permissionCode('view') }}</code> и другие).
                        Выдайте их ролям в разделе
                        <a href="{{ route('admin.roles.index') }}" class="text-brand-600 hover:underline dark:text-brand-400">Роли и права</a>.
                    </p>
                </x-nexor::admin.card>
            @endif

            <div class="flex items-center gap-2">
                <x-nexor::admin.button type="submit" size="lg">Сохранить</x-nexor::admin.button>
                <x-nexor::admin.button :href="route('admin.iblocks.index')" variant="secondary" size="lg">Отмена</x-nexor::admin.button>
            </div>
        </div>
    </form>
@endsection
