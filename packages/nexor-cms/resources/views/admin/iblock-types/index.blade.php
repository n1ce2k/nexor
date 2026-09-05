@extends('nexor::admin.layouts.app')

@section('title', 'Типы инфоблоков')

@section('content')
    <x-nexor::admin.page-header title="Типы инфоблоков"
                         description="Верхний уровень структуры: группируют инфоблоки по назначению.">
        <x-slot:actions>
            @can('iblock_types.create')
                <x-nexor::admin.button :href="route('admin.iblock-types.create')" icon="plus">Добавить тип</x-nexor::admin.button>
            @endcan
        </x-slot:actions>
    </x-nexor::admin.page-header>

    <x-nexor::admin.card :padding="false">
        @if ($types->isEmpty())
            <x-nexor::admin.empty-state icon="database" title="Типов пока нет"
                                 description="Тип инфоблоков — это, например, «Контент», «Каталог» или «Служебные».">
                @can('iblock_types.create')
                    <x-nexor::admin.button :href="route('admin.iblock-types.create')" icon="plus">Создать тип</x-nexor::admin.button>
                @endcan
            </x-nexor::admin.empty-state>
        @else
            <x-nexor::admin.table>
                <x-slot:head>
                    <x-nexor::admin.table.heading>Название</x-nexor::admin.table.heading>
                    <x-nexor::admin.table.heading>Код</x-nexor::admin.table.heading>
                    <x-nexor::admin.table.heading>Разделы</x-nexor::admin.table.heading>
                    <x-nexor::admin.table.heading align="center" width="9rem">Инфоблоков</x-nexor::admin.table.heading>
                    <x-nexor::admin.table.heading align="center" width="7rem">Статус</x-nexor::admin.table.heading>
                    <x-nexor::admin.table.heading align="right" width="7rem">Действия</x-nexor::admin.table.heading>
                </x-slot:head>

                @foreach ($types as $type)
                    <x-nexor::admin.table.row :id="$type->id">
                        <x-nexor::admin.table.cell>
                            <p class="font-medium text-[var(--text-strong)]">{{ $type->name }}</p>
                            @if ($type->description)
                                <p class="mt-0.5 text-xs text-[var(--text-muted)]">{{ Str::limit($type->description, 80) }}</p>
                            @endif
                        </x-nexor::admin.table.cell>

                        <x-nexor::admin.table.cell muted>
                            <code class="font-mono text-xs">{{ $type->code }}</code>
                        </x-nexor::admin.table.cell>

                        <x-nexor::admin.table.cell>
                            <x-nexor::admin.badge :color="$type->has_sections ? 'blue' : 'gray'">
                                {{ $type->has_sections ? 'используются' : 'не используются' }}
                            </x-nexor::admin.badge>
                        </x-nexor::admin.table.cell>

                        <x-nexor::admin.table.cell align="center" muted>{{ $type->iblocks_count }}</x-nexor::admin.table.cell>

                        <x-nexor::admin.table.cell align="center">
                            <x-nexor::admin.badge :color="$type->is_active ? 'green' : 'gray'">
                                {{ $type->is_active ? 'активен' : 'выключен' }}
                            </x-nexor::admin.badge>
                        </x-nexor::admin.table.cell>

                        <x-nexor::admin.table.cell align="right">
                            <div class="flex items-center justify-end gap-0.5">
                                @can('iblock_types.update')
                                    <a href="{{ route('admin.iblock-types.edit', $type) }}" title="Изменить"
                                       class="rounded-lg p-2 text-[var(--text-muted)] transition hover:bg-[var(--surface-muted)] hover:text-[var(--text-strong)]">
                                        <x-nexor::admin.icon name="pencil" class="size-4" />
                                    </a>
                                @endcan

                                @can('iblock_types.delete')
                                    <x-nexor::admin.delete-button :action="route('admin.iblock-types.destroy', $type)"
                                                           title="Удалить тип инфоблоков?"
                                                           :message="'Тип «'.$type->name.'» будет удалён.'"
                                                           icon-only />
                                @endcan
                            </div>
                        </x-nexor::admin.table.cell>
                    </x-nexor::admin.table.row>
                @endforeach
            </x-nexor::admin.table>

            <x-nexor::admin.pagination :paginator="$types" />
        @endif
    </x-nexor::admin.card>
@endsection
