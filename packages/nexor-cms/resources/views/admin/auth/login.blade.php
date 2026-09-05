@extends('nexor::admin.layouts.auth')

@section('title', 'Вход')

@section('content')
    <x-nexor::admin.card>
        <form method="POST" action="{{ route('admin.login') }}" class="space-y-5">
            @csrf

            @if ($errors->has('email'))
                <div class="flex items-start gap-2 rounded-lg bg-red-50 p-3 text-sm text-red-700 dark:bg-red-500/10 dark:text-red-400">
                    <x-nexor::admin.icon name="alert" class="mt-0.5 size-4 shrink-0" />
                    <span>{{ $errors->first('email') }}</span>
                </div>
            @endif

            <x-nexor::admin.field label="E-mail" name="email" required>
                <x-nexor::admin.input name="email" type="email" :value="old('email')"
                               autocomplete="username" autofocus required placeholder="admin@example.com" />
            </x-nexor::admin.field>

            <x-nexor::admin.field label="Пароль" name="password" required>
                <x-nexor::admin.input name="password" type="password"
                               autocomplete="current-password" required placeholder="••••••••" />
            </x-nexor::admin.field>

            <x-nexor::admin.checkbox name="remember" label="Запомнить меня" :checked="old('remember')" :hidden="false" />

            <x-nexor::admin.button type="submit" class="w-full" size="lg">Войти</x-nexor::admin.button>
        </form>
    </x-nexor::admin.card>
@endsection
