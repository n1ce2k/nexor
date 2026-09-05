@extends('nexor::admin.layouts.app')

@section('title', 'Мой профиль')

@section('content')
    <x-nexor::admin.page-header title="Мой профиль" description="Личные данные и пароль вашей учётной записи." />

    <div class="grid gap-6 lg:grid-cols-3">
        <div class="space-y-6 lg:col-span-2">
            <form method="POST" action="{{ route('admin.profile.update') }}" enctype="multipart/form-data">
                @csrf
                @method('PUT')

                <x-nexor::admin.card title="Личные данные">
                    <div class="grid gap-5 sm:grid-cols-2">
                        <x-nexor::admin.field label="Имя" name="name" required class="sm:col-span-2">
                            <x-nexor::admin.input name="name" :value="old('name', $user->name)" required />
                        </x-nexor::admin.field>

                        <x-nexor::admin.field label="E-mail" name="email" required>
                            <x-nexor::admin.input name="email" type="email" :value="old('email', $user->email)" required />
                        </x-nexor::admin.field>

                        <x-nexor::admin.field label="Телефон" name="phone">
                            <x-nexor::admin.input name="phone" :value="old('phone', $user->phone)" />
                        </x-nexor::admin.field>

                        <x-nexor::admin.field label="Аватар" name="avatar" class="sm:col-span-2">
                            <x-nexor::admin.file-input name="avatar" :value="$user->avatar" accept="image/*" />
                        </x-nexor::admin.field>
                    </div>

                    <x-slot:footer>
                        <x-nexor::admin.button type="submit">Сохранить</x-nexor::admin.button>
                    </x-slot:footer>
                </x-nexor::admin.card>
            </form>

            <form method="POST" action="{{ route('admin.profile.password') }}">
                @csrf
                @method('PUT')

                <x-nexor::admin.card title="Смена пароля">
                    <div class="grid gap-5 sm:grid-cols-2">
                        <x-nexor::admin.field label="Текущий пароль" name="current_password" required class="sm:col-span-2">
                            <x-nexor::admin.input name="current_password" type="password" autocomplete="current-password" required />
                        </x-nexor::admin.field>

                        <x-nexor::admin.field label="Новый пароль" name="password" required>
                            <x-nexor::admin.input name="password" type="password" autocomplete="new-password" required />
                        </x-nexor::admin.field>

                        <x-nexor::admin.field label="Повторите новый пароль" name="password_confirmation" required>
                            <x-nexor::admin.input name="password_confirmation" type="password" autocomplete="new-password" required />
                        </x-nexor::admin.field>
                    </div>

                    <x-slot:footer>
                        <x-nexor::admin.button type="submit">Изменить пароль</x-nexor::admin.button>
                    </x-slot:footer>
                </x-nexor::admin.card>
            </form>
        </div>

        <div class="space-y-6">
            <x-nexor::admin.card title="Мои роли">
                @forelse ($user->roles as $role)
                    <div class="py-1.5">
                        <p class="text-sm font-medium text-[var(--text-strong)]">{{ $role->name }}</p>
                        @if ($role->description)
                            <p class="text-xs text-[var(--text-muted)]">{{ $role->description }}</p>
                        @endif
                    </div>
                @empty
                    <p class="text-sm text-[var(--text-muted)]">Роли не назначены.</p>
                @endforelse
            </x-nexor::admin.card>

            <x-nexor::admin.card title="Активность">
                <dl class="space-y-2.5 text-sm">
                    <div class="flex justify-between gap-3">
                        <dt class="text-[var(--text-muted)]">Последний вход</dt>
                        <dd class="text-[var(--text-strong)]">{{ $user->last_login_at?->format('d.m.Y H:i') ?? '—' }}</dd>
                    </div>
                    <div class="flex justify-between gap-3">
                        <dt class="text-[var(--text-muted)]">IP</dt>
                        <dd class="font-mono text-xs text-[var(--text-strong)]">{{ $user->last_login_ip ?? '—' }}</dd>
                    </div>
                    <div class="flex justify-between gap-3">
                        <dt class="text-[var(--text-muted)]">Аккаунт создан</dt>
                        <dd class="text-[var(--text-strong)]">{{ $user->created_at?->format('d.m.Y') }}</dd>
                    </div>
                </dl>
            </x-nexor::admin.card>
        </div>
    </div>
@endsection
