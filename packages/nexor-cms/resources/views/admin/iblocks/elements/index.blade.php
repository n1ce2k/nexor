@extends('nexor::admin.layouts.app')

@section('title', $iblock->name)

@section('content')
    <x-nexor::admin.page-header :title="$iblock->name"
                         :description="$iblock->description ? Str::limit($iblock->description, 120) : ($iblock->type?->elements_name ?? 'Элементы').' инфоблока'">
        <x-slot:breadcrumbs>
            <x-nexor::admin.breadcrumbs :items="[
                ($iblock->type?->name ?? 'Контент') => null,
                $iblock->name => null,
            ]" />
        </x-slot:breadcrumbs>

        <x-slot:actions>
            @if ($iblock->has_sections)
                <x-nexor::admin.button :href="route('admin.iblocks.sections.index', $iblock)" variant="secondary" icon="folder">
                    Разделы
                </x-nexor::admin.button>
            @endif

            @can('iblocks.update')
                <x-nexor::admin.button :href="route('admin.iblocks.properties.index', $iblock)" variant="secondary" icon="grip">
                    Свойства
                </x-nexor::admin.button>
            @endcan

            @if (auth()->user()->hasPermission($iblock->permissionCode('create')))
                <x-nexor::admin.button :href="route('admin.iblocks.elements.create', $iblock)" icon="plus">Добавить</x-nexor::admin.button>
            @endif
        </x-slot:actions>
    </x-nexor::admin.page-header>

    <x-nexor::admin.card :padding="false">
        <div class="border-b border-[var(--surface-border)] p-4">
            <x-nexor::admin.filters placeholder="Название или код...">
                @if ($sections->isNotEmpty())
                    <x-nexor::admin.select name="section" :selected="request('section')" placeholder="Все разделы"
                                    :options="$sections->pluck('indented_name', 'id')->all()" class="w-auto" />
                @endif

                <x-nexor::admin.select name="status" :selected="request('status')" placeholder="Любой статус"
                                :options="['active' => 'Активные', 'hidden' => 'Скрытые']" class="w-auto" />

                @foreach ($filterProperties as $property)
                    @if ($property->type->usesEnums())
                        <x-nexor::admin.select :name="'prop['.$property->code.']'"
                                        :selected="request('prop.'.$property->code)"
                                        :placeholder="$property->name"
                                        :options="$property->enums->pluck('value', 'id')->all()" class="w-auto" />
                    @else
                        <x-nexor::admin.input :name="'prop['.$property->code.']'"
                                       :value="request('prop.'.$property->code)"
                                       :placeholder="$property->name" class="w-auto" />
                    @endif
                @endforeach
            </x-nexor::admin.filters>
        </div>

        @if ($elements->isEmpty())
            <x-nexor::admin.empty-state icon="document" title="Элементов нет"
                                 description="Добавьте первую запись в этот инфоблок.">
                @if (auth()->user()->hasPermission($iblock->permissionCode('create')))
                    <x-nexor::admin.button :href="route('admin.iblocks.elements.create', $iblock)" icon="plus">Добавить элемент</x-nexor::admin.button>
                @endif
            </x-nexor::admin.empty-state>
        @else
            <x-nexor::admin.table>
                <x-slot:head>
                    <x-nexor::admin.table.heading sort="sort" width="5rem">Сорт.</x-nexor::admin.table.heading>
                    <x-nexor::admin.table.heading sort="name">Название</x-nexor::admin.table.heading>

                    @if ($iblock->has_sections)
                        <x-nexor::admin.table.heading>Раздел</x-nexor::admin.table.heading>
                    @endif

                    @foreach ($listProperties as $property)
                        <x-nexor::admin.table.heading>{{ $property->name }}</x-nexor::admin.table.heading>
                    @endforeach

                    <x-nexor::admin.table.heading align="center" width="7rem">Статус</x-nexor::admin.table.heading>
                    <x-nexor::admin.table.heading sort="updated_at" width="9rem">Изменён</x-nexor::admin.table.heading>
                    <x-nexor::admin.table.heading align="right" width="7rem">Действия</x-nexor::admin.table.heading>
                </x-slot:head>

                @foreach ($elements as $element)
                    <x-nexor::admin.table.row :id="$element->id">
                        <x-nexor::admin.table.cell muted>
                            <span class="font-mono text-xs">{{ $element->sort }}</span>
                        </x-nexor::admin.table.cell>

                        <x-nexor::admin.table.cell>
                            <div class="flex items-center gap-3">
                                @if ($element->preview_picture_url)
                                    <img src="{{ $element->preview_picture_url }}" alt=""
                                         class="size-9 shrink-0 rounded object-cover">
                                @endif

                                <div class="min-w-0">
                                    <a href="{{ route('admin.iblocks.elements.edit', [$iblock, $element]) }}"
                                       class="truncate font-medium text-[var(--text-strong)] hover:text-brand-600 hover:underline">
                                        {{ $element->name }}
                                    </a>
                                    @if ($element->code)
                                        <p class="truncate text-xs text-[var(--text-muted)]">
                                            <code class="font-mono">{{ $element->code }}</code>
                                        </p>
                                    @endif
                                </div>
                            </div>
                        </x-nexor::admin.table.cell>

                        @if ($iblock->has_sections)
                            <x-nexor::admin.table.cell muted>
                                <span class="text-xs">{{ $element->section?->name ?? '—' }}</span>
                            </x-nexor::admin.table.cell>
                        @endif

                        @foreach ($listProperties as $property)
                            <x-nexor::admin.table.cell muted>
                                @if ($property->type === \Nexor\Cms\Enums\PropertyType::Color)
                                    @php $hex = $element->displayValue($property); @endphp
                                    <span class="inline-flex items-center gap-1.5">
                                        <span class="size-4 rounded border border-[var(--surface-border)]"
                                              style="background-color: {{ $hex !== '—' ? $hex : 'transparent' }}"></span>
                                        <code class="font-mono text-xs">{{ $hex }}</code>
                                    </span>
                                @else
                                    <span class="text-xs">{{ Str::limit($element->displayValue($property), 40) }}</span>
                                @endif
                            </x-nexor::admin.table.cell>
                        @endforeach

                        <x-nexor::admin.table.cell align="center">
                            <x-nexor::admin.badge :color="$element->is_active ? 'green' : 'gray'">
                                {{ $element->is_active ? 'активен' : 'скрыт' }}
                            </x-nexor::admin.badge>
                        </x-nexor::admin.table.cell>

                        <x-nexor::admin.table.cell muted>
                            <span class="text-xs">{{ $element->updated_at?->format('d.m.Y H:i') }}</span>
                        </x-nexor::admin.table.cell>

                        <x-nexor::admin.table.cell align="right">
                            <div class="flex items-center justify-end gap-0.5">
                                @if (auth()->user()->hasPermission($iblock->permissionCode('update')))
                                    <a href="{{ route('admin.iblocks.elements.edit', [$iblock, $element]) }}" title="Изменить"
                                       class="rounded-lg p-2 text-[var(--text-muted)] transition hover:bg-[var(--surface-muted)] hover:text-[var(--text-strong)]">
                                        <x-nexor::admin.icon name="pencil" class="size-4" />
                                    </a>
                                @endif

                                @if (auth()->user()->hasPermission($iblock->permissionCode('delete')))
                                    <x-nexor::admin.delete-button :action="route('admin.iblocks.elements.destroy', [$iblock, $element])"
                                                           title="Удалить элемент?"
                                                           :message="'Элемент «'.$element->name.'» будет удалён.'"
                                                           icon-only />
                                @endif
                            </div>
                        </x-nexor::admin.table.cell>
                    </x-nexor::admin.table.row>
                @endforeach
            </x-nexor::admin.table>

            <x-nexor::admin.pagination :paginator="$elements" />
        @endif
    </x-nexor::admin.card>
@endsection
