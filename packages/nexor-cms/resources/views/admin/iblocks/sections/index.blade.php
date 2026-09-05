@extends('nexor::admin.layouts.app')

@section('title', 'Разделы — '.$iblock->name)

@section('content')
    <x-nexor::admin.page-header :title="($iblock->type?->sections_name ?? 'Разделы').': '.$iblock->name"
                         :back="route('admin.iblocks.elements.index', $iblock)"
                         description="Древовидная структура инфоблока.">
        <x-slot:breadcrumbs>
            <x-nexor::admin.breadcrumbs :items="[
                'Инфоблоки' => route('admin.iblocks.index'),
                $iblock->name => route('admin.iblocks.elements.index', $iblock),
                'Разделы' => null,
            ]" />
        </x-slot:breadcrumbs>

        <x-slot:actions>
            <x-nexor::admin.button :href="route('admin.iblocks.sections.create', $iblock)" icon="plus">Добавить раздел</x-nexor::admin.button>
        </x-slot:actions>
    </x-nexor::admin.page-header>

    <x-nexor::admin.card :padding="false">
        <div class="border-b border-[var(--surface-border)] p-4">
            <x-nexor::admin.filters placeholder="Название раздела..." />
        </div>

        @if ($sections->isEmpty())
            <x-nexor::admin.empty-state icon="folder" title="Разделов нет"
                                 description="Разделы позволяют разложить элементы по категориям.">
                <x-nexor::admin.button :href="route('admin.iblocks.sections.create', $iblock)" icon="plus">Создать раздел</x-nexor::admin.button>
            </x-nexor::admin.empty-state>
        @else
            <x-nexor::admin.table>
                <x-slot:head>
                    <x-nexor::admin.table.heading>Раздел</x-nexor::admin.table.heading>
                    <x-nexor::admin.table.heading>Код</x-nexor::admin.table.heading>
                    <x-nexor::admin.table.heading align="center" width="6rem">Сорт.</x-nexor::admin.table.heading>
                    <x-nexor::admin.table.heading align="center" width="8rem">Элементов</x-nexor::admin.table.heading>
                    <x-nexor::admin.table.heading align="center" width="7rem">Статус</x-nexor::admin.table.heading>
                    <x-nexor::admin.table.heading align="right" width="9rem">Действия</x-nexor::admin.table.heading>
                </x-slot:head>

                @foreach ($sections as $section)
                    <x-nexor::admin.table.row :id="$section->id">
                        <x-nexor::admin.table.cell>
                            <div class="flex items-center gap-2" style="padding-left: {{ $section->depth * 1.25 }}rem">
                                @if ($section->depth > 0)
                                    <span class="text-[var(--text-faint)]">└</span>
                                @endif

                                @if ($section->picture)
                                    <img src="{{ Storage::disk('public')->url($section->picture) }}" alt=""
                                         class="size-7 rounded object-cover">
                                @else
                                    <x-nexor::admin.icon name="folder" class="size-4 shrink-0 text-[var(--text-faint)]" />
                                @endif

                                <a href="{{ route('admin.iblocks.elements.index', [$iblock, 'section' => $section->id]) }}"
                                   class="font-medium text-[var(--text-strong)] hover:text-brand-600 hover:underline">
                                    {{ $section->name }}
                                </a>
                            </div>
                        </x-nexor::admin.table.cell>

                        <x-nexor::admin.table.cell muted>
                            <code class="font-mono text-xs">{{ $section->code ?? '—' }}</code>
                        </x-nexor::admin.table.cell>

                        <x-nexor::admin.table.cell align="center" muted>
                            <span class="font-mono text-xs">{{ $section->sort }}</span>
                        </x-nexor::admin.table.cell>

                        <x-nexor::admin.table.cell align="center" muted>{{ $section->elements_count }}</x-nexor::admin.table.cell>

                        <x-nexor::admin.table.cell align="center">
                            <x-nexor::admin.badge :color="$section->is_active ? 'green' : 'gray'">
                                {{ $section->is_active ? 'активен' : 'скрыт' }}
                            </x-nexor::admin.badge>
                        </x-nexor::admin.table.cell>

                        <x-nexor::admin.table.cell align="right">
                            <div class="flex items-center justify-end gap-0.5">
                                <a href="{{ route('admin.iblocks.sections.create', [$iblock, 'parent' => $section->id]) }}"
                                   title="Добавить подраздел"
                                   class="rounded-lg p-2 text-[var(--text-muted)] transition hover:bg-[var(--surface-muted)] hover:text-[var(--text-strong)]">
                                    <x-nexor::admin.icon name="plus" class="size-4" />
                                </a>

                                <a href="{{ route('admin.iblocks.sections.edit', [$iblock, $section]) }}" title="Изменить"
                                   class="rounded-lg p-2 text-[var(--text-muted)] transition hover:bg-[var(--surface-muted)] hover:text-[var(--text-strong)]">
                                    <x-nexor::admin.icon name="pencil" class="size-4" />
                                </a>

                                <x-nexor::admin.delete-button :action="route('admin.iblocks.sections.destroy', [$iblock, $section])"
                                                       title="Удалить раздел?"
                                                       :message="'Раздел «'.$section->name.'» и все вложенные разделы будут удалены. Элементы останутся, но потеряют привязку.'"
                                                       icon-only />
                            </div>
                        </x-nexor::admin.table.cell>
                    </x-nexor::admin.table.row>
                @endforeach
            </x-nexor::admin.table>
        @endif
    </x-nexor::admin.card>
@endsection
