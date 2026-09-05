@extends('nexor::admin.layouts.app')

@section('title', $type->exists ? 'Изменение типа' : 'Новый тип инфоблоков')

@section('content')
    <x-nexor::admin.page-header :title="$type->exists ? $type->name : 'Новый тип инфоблоков'"
                         :back="route('admin.iblock-types.index')">
        <x-slot:breadcrumbs>
            <x-nexor::admin.breadcrumbs :items="[
                'Типы инфоблоков' => route('admin.iblock-types.index'),
                ($type->exists ? $type->name : 'Новый тип') => null,
            ]" />
        </x-slot:breadcrumbs>
    </x-nexor::admin.page-header>

    <form method="POST"
          action="{{ $type->exists ? route('admin.iblock-types.update', $type) : route('admin.iblock-types.store') }}"
          x-data="slugField(@js(old('code', $type->code)))"
          class="grid gap-6 lg:grid-cols-3">
        @csrf
        @if ($type->exists)
            @method('PUT')
        @endif

        <div class="space-y-6 lg:col-span-2">
            <x-nexor::admin.card title="Основное">
                <div class="grid gap-5 sm:grid-cols-2">
                    <x-nexor::admin.field label="Название" name="name" required>
                        <x-nexor::admin.input name="name" :value="old('name', $type->name)" required @input="fromName($event)" />
                    </x-nexor::admin.field>

                    <x-nexor::admin.field label="Символьный код" name="code" required
                                   hint="Латиница, цифры, дефис.">
                        <x-nexor::admin.input name="code" x-model="code" @input="markTouched()" class="font-mono" required />
                    </x-nexor::admin.field>

                    <x-nexor::admin.field label="Описание" name="description" class="sm:col-span-2">
                        <x-nexor::admin.textarea name="description" :value="old('description', $type->description)" rows="3" />
                    </x-nexor::admin.field>
                </div>
            </x-nexor::admin.card>

            <x-nexor::admin.card title="Названия в интерфейсе"
                          description="Как называть разделы и элементы инфоблоков этого типа.">
                <div class="grid gap-5 sm:grid-cols-2">
                    <x-nexor::admin.field label="Разделы" name="sections_name" hint="Например: «Категории», «Рубрики».">
                        <x-nexor::admin.input name="sections_name"
                                       :value="old('sections_name', $type->getRawOriginal('sections_name'))"
                                       placeholder="Разделы" />
                    </x-nexor::admin.field>

                    <x-nexor::admin.field label="Элементы" name="elements_name" hint="Например: «Товары», «Новости», «Цвета».">
                        <x-nexor::admin.input name="elements_name"
                                       :value="old('elements_name', $type->getRawOriginal('elements_name'))"
                                       placeholder="Элементы" />
                    </x-nexor::admin.field>
                </div>
            </x-nexor::admin.card>
        </div>

        <div class="space-y-6">
            <x-nexor::admin.card title="Параметры">
                <div class="space-y-5">
                    <x-nexor::admin.toggle name="has_sections" label="Использовать разделы"
                                    hint="Древовидная структура внутри инфоблоков."
                                    :checked="old('has_sections', $type->has_sections ?? true)" />

                    <x-nexor::admin.toggle name="is_active" label="Активен"
                                    :checked="old('is_active', $type->is_active ?? true)" />

                    <x-nexor::admin.field label="Сортировка" name="sort">
                        <x-nexor::admin.input name="sort" type="number" :value="old('sort', $type->sort ?? 500)" min="0" />
                    </x-nexor::admin.field>
                </div>
            </x-nexor::admin.card>

            <div class="flex items-center gap-2">
                <x-nexor::admin.button type="submit" size="lg">Сохранить</x-nexor::admin.button>
                <x-nexor::admin.button :href="route('admin.iblock-types.index')" variant="secondary" size="lg">Отмена</x-nexor::admin.button>
            </div>
        </div>
    </form>
@endsection
