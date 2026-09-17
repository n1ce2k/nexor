@extends('nexor::admin.layouts.app')

@section('title', $user->exists ? 'Изменение пользователя' : 'Новый пользователь')

@section('content')
    <x-nexor::admin.page-header :title="$user->exists ? $user->name : 'Новый пользователь'"
                         :back="route('admin.users.index')"
                         :description="$user->exists ? 'Изменение учётной записи и её ролей.' : 'Создание учётной записи с доступом в панель.'">
        <x-slot:breadcrumbs>
            <x-nexor::admin.breadcrumbs :items="[
                'Пользователи' => route('admin.users.index'),
                ($user->exists ? $user->name : 'Новый') => null,
            ]" />
        </x-slot:breadcrumbs>
    </x-nexor::admin.page-header>

    <form method="POST"
          action="{{ $user->exists ? route('admin.users.update', $user) : route('admin.users.store') }}"
          enctype="multipart/form-data"
          class="grid gap-6 lg:grid-cols-3">
        @csrf
        @if ($user->exists)
            @method('PUT')
        @endif

        <div class="space-y-6 lg:col-span-2">
            <x-nexor::admin.card title="Основное">
                <div class="grid gap-5 sm:grid-cols-2">
                    <x-nexor::admin.field label="Имя" name="name" required class="sm:col-span-2">
                        <x-nexor::admin.input name="name" :value="old('name', $user->name)" required />
                    </x-nexor::admin.field>

                    <x-nexor::admin.field label="Логин" name="login" required hint="Им можно входить вместо e-mail">
                        <x-nexor::admin.input name="login" :value="old('login', $user->login)" required />
                    </x-nexor::admin.field>

                    <x-nexor::admin.field label="E-mail" name="email" required>
                        <x-nexor::admin.input name="email" type="email" :value="old('email', $user->email)"
                                       autocomplete="off" required />
                    </x-nexor::admin.field>

                    <x-nexor::admin.field label="Телефон" name="phone">
                        <x-nexor::admin.input name="phone" :value="old('phone', $user->phone)" placeholder="+7 900 000-00-00" />
                    </x-nexor::admin.field>
                </div>
            </x-nexor::admin.card>

            <x-nexor::admin.card title="Пароль"
                          :description="$user->exists ? 'Оставьте поля пустыми, чтобы не менять пароль.' : null">
                <div class="grid gap-5 sm:grid-cols-2">
                    <x-nexor::admin.field label="Пароль" name="password" :required="! $user->exists">
                        <x-nexor::admin.input name="password" type="password" autocomplete="new-password"
                                       :required="! $user->exists" />
                    </x-nexor::admin.field>

                    <x-nexor::admin.field label="Повторите пароль" name="password_confirmation" :required="! $user->exists">
                        <x-nexor::admin.input name="password_confirmation" type="password" autocomplete="new-password"
                                       :required="! $user->exists" />
                    </x-nexor::admin.field>
                </div>
            </x-nexor::admin.card>

            <x-nexor::admin.card title="Роли" description="Права доступа определяются набором ролей.">
                @if ($user->is(auth()->user()))
                    <p class="mb-4 flex items-start gap-2 rounded-lg bg-amber-50 p-3 text-sm text-amber-800 dark:bg-amber-500/10 dark:text-amber-400">
                        <x-nexor::admin.icon name="info" class="mt-0.5 size-4 shrink-0" />
                        Собственные роли и статус изменить нельзя — это защита от потери доступа.
                    </p>
                @endif

                <div class="space-y-3">
                    @foreach ($roles as $role)
                        <x-nexor::admin.checkbox name="roles[]"
                                          :value="$role->id"
                                          :hidden="false"
                                          :checked="in_array($role->id, old('roles', $selectedRoles), false)"
                                          :label="$role->name"
                                          :hint="$role->description"
                                          :disabled="$user->is(auth()->user())" />
                    @endforeach
                </div>
            </x-nexor::admin.card>
        </div>

        <div class="space-y-6">
            <x-nexor::admin.card title="Статус">
                <x-nexor::admin.toggle name="is_active"
                                label="Учётная запись активна"
                                hint="Заблокированный пользователь не сможет войти."
                                :checked="old('is_active', $user->is_active ?? true)"
                                :disabled="$user->is(auth()->user())" />
            </x-nexor::admin.card>

            <x-nexor::admin.card title="Аватар">
                <x-nexor::admin.field name="avatar">
                    <x-nexor::admin.file-input name="avatar" :value="$user->avatar" accept="image/*" />
                </x-nexor::admin.field>
            </x-nexor::admin.card>

            @if ($user->exists)
                <x-nexor::admin.card title="Сведения">
                    <dl class="space-y-2.5 text-sm">
                        <div class="flex justify-between gap-3">
                            <dt class="text-[var(--text-muted)]">Создан</dt>
                            <dd class="text-[var(--text-strong)]">{{ $user->created_at?->format('d.m.Y H:i') }}</dd>
                        </div>
                        <div class="flex justify-between gap-3">
                            <dt class="text-[var(--text-muted)]">Последний вход</dt>
                            <dd class="text-[var(--text-strong)]">{{ $user->last_login_at?->format('d.m.Y H:i') ?? '—' }}</dd>
                        </div>
                        <div class="flex justify-between gap-3">
                            <dt class="text-[var(--text-muted)]">IP входа</dt>
                            <dd class="font-mono text-xs text-[var(--text-strong)]">{{ $user->last_login_ip ?? '—' }}</dd>
                        </div>
                    </dl>
                </x-nexor::admin.card>
            @endif

            <div class="flex items-center gap-2">
                <x-nexor::admin.button type="submit" size="lg">Сохранить</x-nexor::admin.button>
                <x-nexor::admin.button :href="route('admin.users.index')" variant="secondary" size="lg">Отмена</x-nexor::admin.button>
            </div>
        </div>
    </form>
@endsection

