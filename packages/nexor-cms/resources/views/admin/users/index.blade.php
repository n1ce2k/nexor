@extends('nexor::admin.layouts.app')

@section('title', 'Пользователи')

@section('content')
    <x-nexor::admin.page-header title="Пользователи" description="Учётные записи с доступом в панель управления.">
        <x-slot:actions>
            @can('users.create')
                <x-nexor::admin.button :href="route('admin.users.create')" icon="plus">Добавить пользователя</x-nexor::admin.button>
            @endcan
        </x-slot:actions>
    </x-nexor::admin.page-header>

    <x-nexor::admin.card :padding="false">
        <div class="border-b border-[var(--surface-border)] p-4">
            <x-nexor::admin.filters placeholder="Имя или e-mail...">
                <x-nexor::admin.select name="role" :selected="request('role')" placeholder="Все роли"
                                :options="$roles->pluck('name', 'id')->all()" class="w-auto" />

                <x-nexor::admin.select name="status" :selected="request('status')" placeholder="Любой статус"
                                :options="['active' => 'Активные', 'blocked' => 'Заблокированные']" class="w-auto" />
            </x-nexor::admin.filters>
        </div>

        @if ($users->isEmpty())
            <x-nexor::admin.empty-state icon="users" title="Пользователи не найдены"
                                 description="Измените условия поиска или добавьте нового пользователя." />
        @else
            <x-nexor::admin.table>
                <x-slot:head>
                    <x-nexor::admin.table.heading sort="name">Пользователь</x-nexor::admin.table.heading>
                    <x-nexor::admin.table.heading>Роли</x-nexor::admin.table.heading>
                    <x-nexor::admin.table.heading>Статус</x-nexor::admin.table.heading>
                    <x-nexor::admin.table.heading sort="last_login_at">Последний вход</x-nexor::admin.table.heading>
                    <x-nexor::admin.table.heading align="right" width="7rem">Действия</x-nexor::admin.table.heading>
                </x-slot:head>

                @foreach ($users as $user)
                    <x-nexor::admin.table.row :id="$user->id">
                        <x-nexor::admin.table.cell>
                            <div class="flex items-center gap-3">
                                @if ($user->avatar_url)
                                    <img src="{{ $user->avatar_url }}" alt="" class="size-9 rounded-full object-cover">
                                @else
                                    <span class="flex size-9 shrink-0 items-center justify-center rounded-full bg-[var(--surface-muted)] text-xs font-semibold text-[var(--text-muted)]">
                                        {{ \Nexor\Cms\Support\Nexor::initials($user->name) }}
                                    </span>
                                @endif

                                <div class="min-w-0">
                                    <p class="truncate font-medium text-[var(--text-strong)]">{{ $user->name }}</p>
                                    <p class="truncate text-xs text-[var(--text-muted)]">{{ $user->login }} · {{ $user->email }}</p>
                                </div>
                            </div>
                        </x-nexor::admin.table.cell>

                        <x-nexor::admin.table.cell>
                            <div class="flex flex-wrap gap-1">
                                @forelse ($user->roles as $role)
                                    <x-nexor::admin.badge :color="$role->isSuperAdmin() ? 'violet' : 'blue'">{{ $role->name }}</x-nexor::admin.badge>
                                @empty
                                    <span class="text-xs text-[var(--text-faint)]">—</span>
                                @endforelse
                            </div>
                        </x-nexor::admin.table.cell>

                        <x-nexor::admin.table.cell>
                            <x-nexor::admin.badge :color="$user->is_active ? 'green' : 'red'">
                                {{ $user->is_active ? 'Активен' : 'Заблокирован' }}
                            </x-nexor::admin.badge>
                        </x-nexor::admin.table.cell>

                        <x-nexor::admin.table.cell muted>
                            <span class="text-xs">
                                {{ $user->last_login_at?->format('d.m.Y H:i') ?? 'ни разу' }}
                            </span>
                        </x-nexor::admin.table.cell>

                        <x-nexor::admin.table.cell align="right">
                            <div class="flex items-center justify-end gap-0.5">
                                @can('users.update')
                                    <a href="{{ route('admin.users.edit', $user) }}" title="Изменить"
                                       class="rounded-lg p-2 text-[var(--text-muted)] transition hover:bg-[var(--surface-muted)] hover:text-[var(--text-strong)]">
                                        <x-nexor::admin.icon name="pencil" class="size-4" />
                                    </a>
                                @endcan

                                @can('users.delete')
                                    @unless ($user->is(auth()->user()))
                                        <x-nexor::admin.delete-button :action="route('admin.users.destroy', $user)"
                                                               title="Удалить пользователя?"
                                                               :message="'Учётная запись «'.$user->name.'» будет удалена.'"
                                                               icon-only />
                                    @endunless
                                @endcan
                            </div>
                        </x-nexor::admin.table.cell>
                    </x-nexor::admin.table.row>
                @endforeach
            </x-nexor::admin.table>

            <x-nexor::admin.pagination :paginator="$users" />
        @endif
    </x-nexor::admin.card>
@endsection
