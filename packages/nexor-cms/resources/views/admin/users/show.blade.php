@extends('nexor::admin.layouts.app')

@section('title', $user->name)

@section('content')
    <x-nexor::admin.page-header :title="$user->name" :back="route('admin.users.index')" :description="$user->email">
        <x-slot:breadcrumbs>
            <x-nexor::admin.breadcrumbs :items="['Пользователи' => route('admin.users.index'), $user->name => null]" />
        </x-slot:breadcrumbs>

        <x-slot:actions>
            @can('users.update')
                <x-nexor::admin.button :href="route('admin.users.edit', $user)" icon="pencil">Изменить</x-nexor::admin.button>
            @endcan
        </x-slot:actions>
    </x-nexor::admin.page-header>

    <div class="grid gap-6 lg:grid-cols-3">
        <x-nexor::admin.card title="Роли" class="lg:col-span-1">
            @forelse ($user->roles as $role)
                <div class="flex items-center justify-between gap-3 py-1.5">
                    <span class="text-sm text-[var(--text-strong)]">{{ $role->name }}</span>
                    <x-nexor::admin.badge color="gray">{{ $role->permissions->count() }} прав</x-nexor::admin.badge>
                </div>
            @empty
                <p class="text-sm text-[var(--text-muted)]">Роли не назначены.</p>
            @endforelse
        </x-nexor::admin.card>

        <x-nexor::admin.card title="Итоговые права" description="Объединение прав всех назначенных ролей." class="lg:col-span-2">
            @if ($user->isSuperAdmin())
                <p class="flex items-start gap-2 rounded-lg bg-violet-50 p-3 text-sm text-violet-800 dark:bg-violet-500/10 dark:text-violet-300">
                    <x-nexor::admin.icon name="shield" class="mt-0.5 size-4 shrink-0" />
                    Супер-администратор — доступ ко всем разделам без ограничений.
                </p>
            @else
                @php
                    $granted = $user->roles->flatMap->permissions->unique('id')->groupBy('group_label');
                @endphp

                @forelse ($granted as $group => $permissions)
                    <div class="mb-4 last:mb-0">
                        <p class="mb-1.5 text-xs font-semibold tracking-wide text-[var(--text-muted)] uppercase">{{ $group }}</p>
                        <div class="flex flex-wrap gap-1.5">
                            @foreach ($permissions as $permission)
                                <x-nexor::admin.badge color="blue">{{ $permission->name }}</x-nexor::admin.badge>
                            @endforeach
                        </div>
                    </div>
                @empty
                    <p class="text-sm text-[var(--text-muted)]">Прав нет — пользователь не сможет открыть админку.</p>
                @endforelse
            @endif
        </x-nexor::admin.card>
    </div>
@endsection
