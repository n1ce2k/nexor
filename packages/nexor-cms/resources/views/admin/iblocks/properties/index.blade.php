@extends('nexor::admin.layouts.app')

@section('title', 'Свойства — '.$iblock->name)

@section('content')
    <x-nexor::admin.page-header :title="'Свойства: '.$iblock->name"
                         :back="route('admin.iblocks.index')"
                         description="Набор полей, который будет у каждого элемента этого инфоблока.">
        <x-slot:breadcrumbs>
            <x-nexor::admin.breadcrumbs :items="[
                'Инфоблоки' => route('admin.iblocks.index'),
                $iblock->name => route('admin.iblocks.edit', $iblock),
                'Свойства' => null,
            ]" />
        </x-slot:breadcrumbs>

        <x-slot:actions>
            <x-nexor::admin.button :href="route('admin.iblocks.elements.index', $iblock)" variant="secondary" icon="document">
                Наполнение
            </x-nexor::admin.button>
            <x-nexor::admin.button :href="route('admin.iblocks.properties.create', $iblock)" icon="plus">
                Добавить свойство
            </x-nexor::admin.button>
        </x-slot:actions>
    </x-nexor::admin.page-header>

    <x-nexor::admin.card :padding="false">
        @if ($properties->isEmpty())
            <x-nexor::admin.empty-state icon="grip" title="Свойств пока нет"
                                 description="У элементов уже есть базовые поля (название, картинка, текст). Свойства добавляют к ним ваши собственные данные — например, цену, HEX-код цвета или привязку к другому инфоблоку.">
                <x-nexor::admin.button :href="route('admin.iblocks.properties.create', $iblock)" icon="plus">
                    Добавить первое свойство
                </x-nexor::admin.button>
            </x-nexor::admin.empty-state>
        @else
            <x-nexor::admin.table>
                <x-slot:head>
                    <x-nexor::admin.table.heading width="4rem">Сорт.</x-nexor::admin.table.heading>
                    <x-nexor::admin.table.heading>Название</x-nexor::admin.table.heading>
                    <x-nexor::admin.table.heading>Код</x-nexor::admin.table.heading>
                    <x-nexor::admin.table.heading>Тип</x-nexor::admin.table.heading>
                    <x-nexor::admin.table.heading>Флаги</x-nexor::admin.table.heading>
                    <x-nexor::admin.table.heading align="center" width="8rem">Значений</x-nexor::admin.table.heading>
                    <x-nexor::admin.table.heading align="right" width="7rem">Действия</x-nexor::admin.table.heading>
                </x-slot:head>

                @foreach ($properties as $property)
                    <x-nexor::admin.table.row :id="$property->id">
                        <x-nexor::admin.table.cell muted>
                            <span class="font-mono text-xs">{{ $property->sort }}</span>
                        </x-nexor::admin.table.cell>

                        <x-nexor::admin.table.cell>
                            <div class="flex items-center gap-2">
                                <p class="font-medium text-[var(--text-strong)]">{{ $property->name }}</p>
                                @unless ($property->is_active)
                                    <x-nexor::admin.badge color="gray">выключено</x-nexor::admin.badge>
                                @endunless
                            </div>
                            @if ($property->hint)
                                <p class="mt-0.5 text-xs text-[var(--text-muted)]">{{ $property->hint }}</p>
                            @endif
                        </x-nexor::admin.table.cell>

                        <x-nexor::admin.table.cell muted>
                            <code class="font-mono text-xs">{{ $property->code }}</code>
                        </x-nexor::admin.table.cell>

                        <x-nexor::admin.table.cell>
                            <x-nexor::admin.badge color="blue">{{ $property->type->label() }}</x-nexor::admin.badge>
                            @if ($property->type->usesEnums())
                                <span class="ml-1 text-xs text-[var(--text-muted)]">{{ $property->enums->count() }} знач.</span>
                            @endif
                        </x-nexor::admin.table.cell>

                        <x-nexor::admin.table.cell>
                            <div class="flex flex-wrap gap-1">
                                @if ($property->is_multiple)
                                    <x-nexor::admin.badge color="violet">множественное</x-nexor::admin.badge>
                                @endif
                                @if ($property->is_required)
                                    <x-nexor::admin.badge color="red">обязательное</x-nexor::admin.badge>
                                @endif
                                @if ($property->is_filterable)
                                    <x-nexor::admin.badge color="amber">фильтр</x-nexor::admin.badge>
                                @endif
                                @if ($property->is_searchable)
                                    <x-nexor::admin.badge color="green">поиск</x-nexor::admin.badge>
                                @endif
                                @if ($property->is_shown_in_list)
                                    <x-nexor::admin.badge color="gray">в списке</x-nexor::admin.badge>
                                @endif
                            </div>
                        </x-nexor::admin.table.cell>

                        <x-nexor::admin.table.cell align="center" muted>{{ $property->values_count }}</x-nexor::admin.table.cell>

                        <x-nexor::admin.table.cell align="right">
                            <div class="flex items-center justify-end gap-0.5">
                                <a href="{{ route('admin.iblocks.properties.edit', [$iblock, $property]) }}" title="Изменить"
                                   class="rounded-lg p-2 text-[var(--text-muted)] transition hover:bg-[var(--surface-muted)] hover:text-[var(--text-strong)]">
                                    <x-nexor::admin.icon name="pencil" class="size-4" />
                                </a>

                                <x-nexor::admin.delete-button :action="route('admin.iblocks.properties.destroy', [$iblock, $property])"
                                                       title="Удалить свойство?"
                                                       :message="'Свойство «'.$property->name.'» и все его значения ('.$property->values_count.' шт.) будут удалены безвозвратно.'"
                                                       icon-only />
                            </div>
                        </x-nexor::admin.table.cell>
                    </x-nexor::admin.table.row>
                @endforeach
            </x-nexor::admin.table>
        @endif
    </x-nexor::admin.card>
@endsection
