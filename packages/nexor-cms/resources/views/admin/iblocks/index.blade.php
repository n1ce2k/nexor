@extends('nexor::admin.layouts.app')

@section('title', 'Инфоблоки')

@section('content')
    <x-nexor::admin.page-header title="Инфоблоки"
                         description="Структура данных сайта: наборы элементов со своими свойствами.">
        <x-slot:actions>
            @can('iblocks.create')
                <x-nexor::admin.button :href="route('admin.iblocks.create')" icon="plus">Создать инфоблок</x-nexor::admin.button>
            @endcan
        </x-slot:actions>
    </x-nexor::admin.page-header>

    <x-nexor::admin.card :padding="false">
        <div class="border-b border-[var(--surface-border)] p-4">
            <x-nexor::admin.filters placeholder="Название или код...">
                <x-nexor::admin.select name="type" :selected="request('type')" placeholder="Все типы"
                                :options="$types->pluck('name', 'id')->all()" class="w-auto" />
            </x-nexor::admin.filters>
        </div>

        @if ($iblocks->isEmpty())
            <x-nexor::admin.empty-state icon="layers" title="Инфоблоков нет"
                                 description="Инфоблок — это набор однотипных записей: новости, товары, палитра цветов.">
                @can('iblocks.create')
                    <x-nexor::admin.button :href="route('admin.iblocks.create')" icon="plus">Создать инфоблок</x-nexor::admin.button>
                @endcan
            </x-nexor::admin.empty-state>
        @else
            <x-nexor::admin.table>
                <x-slot:head>
                    <x-nexor::admin.table.heading sort="name">Инфоблок</x-nexor::admin.table.heading>
                    <x-nexor::admin.table.heading>Тип</x-nexor::admin.table.heading>
                    <x-nexor::admin.table.heading align="center" width="7rem">Свойств</x-nexor::admin.table.heading>
                    <x-nexor::admin.table.heading align="center" width="7rem">Разделов</x-nexor::admin.table.heading>
                    <x-nexor::admin.table.heading align="center" width="8rem">Элементов</x-nexor::admin.table.heading>
                    <x-nexor::admin.table.heading align="right" width="12rem">Действия</x-nexor::admin.table.heading>
                </x-slot:head>

                @foreach ($iblocks as $iblock)
                    <x-nexor::admin.table.row :id="$iblock->id">
                        <x-nexor::admin.table.cell>
                            <div class="flex items-center gap-2">
                                <p class="font-medium text-[var(--text-strong)]">{{ $iblock->name }}</p>
                                @unless ($iblock->is_active)
                                    <x-nexor::admin.badge color="gray">выключен</x-nexor::admin.badge>
                                @endunless
                            </div>
                            <p class="mt-0.5 text-xs text-[var(--text-muted)]">
                                код <code class="font-mono">{{ $iblock->code }}</code>
                            </p>
                        </x-nexor::admin.table.cell>

                        <x-nexor::admin.table.cell muted>{{ $iblock->type?->name ?? '—' }}</x-nexor::admin.table.cell>

                        <x-nexor::admin.table.cell align="center">
                            <a href="{{ route('admin.iblocks.properties.index', $iblock) }}"
                               class="font-medium text-brand-600 hover:underline dark:text-brand-400">
                                {{ $iblock->properties_count }}
                            </a>
                        </x-nexor::admin.table.cell>

                        <x-nexor::admin.table.cell align="center" muted>
                            @if ($iblock->has_sections)
                                <a href="{{ route('admin.iblocks.sections.index', $iblock) }}"
                                   class="hover:text-[var(--text-strong)] hover:underline">{{ $iblock->sections_count }}</a>
                            @else
                                —
                            @endif
                        </x-nexor::admin.table.cell>

                        <x-nexor::admin.table.cell align="center">
                            <a href="{{ route('admin.iblocks.elements.index', $iblock) }}"
                               class="font-medium text-brand-600 hover:underline dark:text-brand-400">
                                {{ $iblock->elements_count }}
                            </a>
                        </x-nexor::admin.table.cell>

                        <x-nexor::admin.table.cell align="right">
                            <div class="flex items-center justify-end gap-0.5">
                                <a href="{{ route('admin.iblocks.elements.index', $iblock) }}" title="Наполнение"
                                   class="rounded-lg p-2 text-[var(--text-muted)] transition hover:bg-[var(--surface-muted)] hover:text-[var(--text-strong)]">
                                    <x-nexor::admin.icon name="document" class="size-4" />
                                </a>

                                @can('iblocks.update')
                                    <a href="{{ route('admin.iblocks.properties.index', $iblock) }}" title="Свойства"
                                       class="rounded-lg p-2 text-[var(--text-muted)] transition hover:bg-[var(--surface-muted)] hover:text-[var(--text-strong)]">
                                        <x-nexor::admin.icon name="grip" class="size-4" />
                                    </a>

                                    <a href="{{ route('admin.iblocks.edit', $iblock) }}" title="Настройки"
                                       class="rounded-lg p-2 text-[var(--text-muted)] transition hover:bg-[var(--surface-muted)] hover:text-[var(--text-strong)]">
                                        <x-nexor::admin.icon name="pencil" class="size-4" />
                                    </a>
                                @endcan

                                @can('iblocks.delete')
                                    <x-nexor::admin.delete-button :action="route('admin.iblocks.destroy', $iblock)"
                                                           title="Удалить инфоблок?"
                                                           :message="'Инфоблок «'.$iblock->name.'» и все его элементы будут удалены.'"
                                                           icon-only />
                                @endcan
                            </div>
                        </x-nexor::admin.table.cell>
                    </x-nexor::admin.table.row>
                @endforeach
            </x-nexor::admin.table>

            <x-nexor::admin.pagination :paginator="$iblocks" />
        @endif
    </x-nexor::admin.card>
@endsection
