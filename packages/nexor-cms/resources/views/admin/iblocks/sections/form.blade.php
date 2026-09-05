@extends('nexor::admin.layouts.app')

@section('title', $section->exists ? 'Изменение раздела' : 'Новый раздел')

@section('content')
    <x-nexor::admin.page-header :title="$section->exists ? $section->name : 'Новый раздел'"
                         :back="route('admin.iblocks.sections.index', $iblock)"
                         :description="'Инфоблок «'.$iblock->name.'»'">
        <x-slot:breadcrumbs>
            <x-nexor::admin.breadcrumbs :items="[
                'Инфоблоки' => route('admin.iblocks.index'),
                $iblock->name => route('admin.iblocks.elements.index', $iblock),
                'Разделы' => route('admin.iblocks.sections.index', $iblock),
                ($section->exists ? $section->name : 'Новый') => null,
            ]" />
        </x-slot:breadcrumbs>
    </x-nexor::admin.page-header>

    <form method="POST"
          action="{{ $section->exists
              ? route('admin.iblocks.sections.update', [$iblock, $section])
              : route('admin.iblocks.sections.store', $iblock) }}"
          enctype="multipart/form-data"
          x-data="slugField(@js(old('code', $section->code)))"
          class="grid gap-6 lg:grid-cols-3">
        @csrf
        @if ($section->exists)
            @method('PUT')
        @endif

        <div class="space-y-6 lg:col-span-2">
            <x-nexor::admin.card title="Основное">
                <div class="grid gap-5 sm:grid-cols-2">
                    <x-nexor::admin.field label="Название" name="name" required>
                        <x-nexor::admin.input name="name" :value="old('name', $section->name)" required @input="fromName($event)" />
                    </x-nexor::admin.field>

                    <x-nexor::admin.field label="Символьный код" name="code" hint="Используется в URL раздела.">
                        <x-nexor::admin.input name="code" x-model="code" @input="markTouched()" class="font-mono" />
                    </x-nexor::admin.field>

                    <x-nexor::admin.field label="Родительский раздел" name="parent_id">
                        <x-nexor::admin.select name="parent_id" :selected="old('parent_id', $section->parent_id)"
                                        placeholder="— верхний уровень —"
                                        :options="$parents->pluck('indented_name', 'id')->all()" />
                    </x-nexor::admin.field>

                    <x-nexor::admin.field label="Сортировка" name="sort">
                        <x-nexor::admin.input name="sort" type="number" min="0" :value="old('sort', $section->sort ?? 500)" />
                    </x-nexor::admin.field>

                    <x-nexor::admin.field label="Описание" name="description" class="sm:col-span-2">
                        <x-nexor::admin.textarea name="description" :value="old('description', $section->description)" rows="5" />
                    </x-nexor::admin.field>
                </div>
            </x-nexor::admin.card>

            <x-nexor::admin.card title="SEO">
                <div class="space-y-5">
                    <x-nexor::admin.field label="Заголовок страницы (title)" name="meta_title">
                        <x-nexor::admin.input name="meta_title" :value="old('meta_title', $section->meta_title)" />
                    </x-nexor::admin.field>

                    <x-nexor::admin.field label="Описание (description)" name="meta_description">
                        <x-nexor::admin.textarea name="meta_description" :value="old('meta_description', $section->meta_description)" rows="2" />
                    </x-nexor::admin.field>

                    <x-nexor::admin.field label="Ключевые слова" name="meta_keywords">
                        <x-nexor::admin.input name="meta_keywords" :value="old('meta_keywords', $section->meta_keywords)" />
                    </x-nexor::admin.field>
                </div>
            </x-nexor::admin.card>
        </div>

        <div class="space-y-6">
            <x-nexor::admin.card title="Параметры">
                <x-nexor::admin.toggle name="is_active" label="Активен"
                                :checked="old('is_active', $section->is_active ?? true)" />
            </x-nexor::admin.card>

            <x-nexor::admin.card title="Картинка">
                <x-nexor::admin.field name="picture">
                    <x-nexor::admin.file-input name="picture" :value="$section->picture" accept="image/*" />
                </x-nexor::admin.field>
            </x-nexor::admin.card>

            <div class="flex items-center gap-2">
                <x-nexor::admin.button type="submit" size="lg">Сохранить</x-nexor::admin.button>
                <x-nexor::admin.button :href="route('admin.iblocks.sections.index', $iblock)" variant="secondary" size="lg">
                    Отмена
                </x-nexor::admin.button>
            </div>
        </div>
    </form>
@endsection
