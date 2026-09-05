@extends('nexor::admin.layouts.app')

@section('title', 'Роли и права')

@section('content')
    <x-nexor::admin.page-header title="Роли и права"
                         description="Набор прав, который назначается пользователям.">
        <x-slot:actions>
            @can('roles.create')
                <x-nexor::admin.button :href="route('admin.roles.create')" icon="plus">Добавить роль</x-nexor::admin.button>
            @endcan
        </x-slot:actions>
    </x-nexor::admin.page-header>

    <x-nexor::admin.card :padding="false">
        <x-nexor::admin.table>
            <x-slot:head>
                <x-nexor::admin.table.heading>Роль</x-nexor::admin.table.heading>
                <x-nexor::admin.table.heading>Код</x-nexor::admin.table.heading>
                <x-nexor::admin.table.heading align="center" width="8rem">Прав</x-nexor::admin.table.heading>
                <x-nexor::admin.table.heading align="center" width="10rem">Пользователей</x-nexor::admin.table.heading>
                <x-nexor::admin.table.heading align="right" width="7rem">Действия</x-nexor::admin.table.heading>
            </x-slot:head>

            @foreach ($roles as $role)
                <x-nexor::admin.table.row :id="$role->id">
                    <x-nexor::admin.table.cell>
                        <p class="flex items-center gap-2 font-medium text-[var(--text-strong)]">
                            {{ $role->name }}
                            @if ($role->is_system)
                                <x-nexor::admin.badge color="gray">системная</x-nexor::admin.badge>
                            @endif
                        </p>
                        @if ($role->description)
                            <p class="mt-0.5 text-xs text-[var(--text-muted)]">{{ $role->description }}</p>
                        @endif
                    </x-nexor::admin.table.cell>

                    <x-nexor::admin.table.cell muted>
                        <code class="font-mono text-xs">{{ $role->code }}</code>
                    </x-nexor::admin.table.cell>

                    <x-nexor::admin.table.cell align="center">
                        <x-nexor::admin.badge :color="$role->isSuperAdmin() ? 'violet' : 'blue'">
                            {{ $role->isSuperAdmin() ? 'все' : $role->permissions_count }}
                        </x-nexor::admin.badge>
                    </x-nexor::admin.table.cell>

                    <x-nexor::admin.table.cell align="center" muted>{{ $role->users_count }}</x-nexor::admin.table.cell>

                    <x-nexor::admin.table.cell align="right">
                        <div class="flex items-center justify-end gap-0.5">
                            @can('roles.update')
                                <a href="{{ route('admin.roles.edit', $role) }}" title="Изменить"
                                   class="rounded-lg p-2 text-[var(--text-muted)] transition hover:bg-[var(--surface-muted)] hover:text-[var(--text-strong)]">
                                    <x-nexor::admin.icon name="pencil" class="size-4" />
                                </a>
                            @endcan

                            @can('roles.delete')
                                @unless ($role->is_system)
                                    <x-nexor::admin.delete-button :action="route('admin.roles.destroy', $role)"
                                                           title="Удалить роль?"
                                                           :message="'Роль «'.$role->name.'» будет удалена.'"
                                                           icon-only />
                                @endunless
                            @endcan
                        </div>
                    </x-nexor::admin.table.cell>
                </x-nexor::admin.table.row>
            @endforeach
        </x-nexor::admin.table>

        <x-nexor::admin.pagination :paginator="$roles" />
    </x-nexor::admin.card>
@endsection
