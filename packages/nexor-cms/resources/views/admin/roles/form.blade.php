@extends('nexor::admin.layouts.app')

@section('title', $role->exists ? 'Изменение роли' : 'Новая роль')

@section('content')
    <x-nexor::admin.page-header :title="$role->exists ? $role->name : 'Новая роль'"
                         :back="route('admin.roles.index')"
                         description="Отметьте права, которые получают пользователи с этой ролью.">
        <x-slot:breadcrumbs>
            <x-nexor::admin.breadcrumbs :items="[
                'Роли и права' => route('admin.roles.index'),
                ($role->exists ? $role->name : 'Новая роль') => null,
            ]" />
        </x-slot:breadcrumbs>
    </x-nexor::admin.page-header>

    <form method="POST"
          action="{{ $role->exists ? route('admin.roles.update', $role) : route('admin.roles.store') }}"
          x-data="slugField(@js(old('code', $role->code)))"
          class="grid gap-6 lg:grid-cols-3">
        @csrf
        @if ($role->exists)
            @method('PUT')
        @endif

        <div class="space-y-6 lg:col-span-2">
            @if ($role->isSuperAdmin())
                <p class="flex items-start gap-2 rounded-xl bg-violet-50 p-4 text-sm text-violet-800 dark:bg-violet-500/10 dark:text-violet-300">
                    <x-nexor::admin.icon name="shield" class="mt-0.5 size-4 shrink-0" />
                    Роль супер-администратора всегда получает все права, включая права новых инфоблоков.
                    Список ниже показан только для справки.
                </p>
            @endif

            @foreach ($groups as $groupLabel => $permissions)
                <x-nexor::admin.card :title="$groupLabel" x-data="{
                    toggleAll(checked) {
                        $el.querySelectorAll('[data-permission]').forEach((box) => {
                            if (! box.disabled) box.checked = checked;
                        });
                    },
                }">
                    <x-slot:actions>
                        <label class="flex cursor-pointer items-center gap-2 text-xs text-[var(--text-muted)]">
                            <input type="checkbox" @change="toggleAll($event.target.checked)"
                                   @disabled($role->isSuperAdmin())
                                   class="size-3.5 rounded border-[var(--surface-border-strong)] text-brand-600 focus:ring-brand-500/40">
                            выбрать все
                        </label>
                    </x-slot:actions>

                    <div class="grid gap-3 sm:grid-cols-2">
                        @foreach ($permissions as $permission)
                            <x-nexor::admin.checkbox name="permissions[]"
                                              :value="$permission->id"
                                              :hidden="false"
                                              :checked="$role->isSuperAdmin() || in_array($permission->id, old('permissions', $selected), false)"
                                              :label="$permission->name"
                                              :hint="$permission->code"
                                              :disabled="$role->isSuperAdmin()"
                                              data-permission />
                        @endforeach
                    </div>
                </x-nexor::admin.card>
            @endforeach
        </div>

        <div class="space-y-6">
            <x-nexor::admin.card title="Параметры роли">
                <div class="space-y-5">
                    <x-nexor::admin.field label="Название" name="name" required>
                        <x-nexor::admin.input name="name" :value="old('name', $role->name)" required
                                       @input="fromName($event)" />
                    </x-nexor::admin.field>

                    <x-nexor::admin.field label="Символьный код" name="code"
                                   :required="! $role->is_system"
                                   hint="Латиница, цифры, дефис. Используется в коде проверок прав.">
                        <x-nexor::admin.input name="code" x-model="code" @input="markTouched()"
                                       :disabled="$role->is_system"
                                       class="font-mono" />
                    </x-nexor::admin.field>

                    <x-nexor::admin.field label="Описание" name="description">
                        <x-nexor::admin.textarea name="description" :value="old('description', $role->description)" rows="3" />
                    </x-nexor::admin.field>

                    <x-nexor::admin.field label="Сортировка" name="sort">
                        <x-nexor::admin.input name="sort" type="number" :value="old('sort', $role->sort ?? 500)" min="0" />
                    </x-nexor::admin.field>
                </div>
            </x-nexor::admin.card>

            <div class="flex items-center gap-2">
                <x-nexor::admin.button type="submit" size="lg">Сохранить</x-nexor::admin.button>
                <x-nexor::admin.button :href="route('admin.roles.index')" variant="secondary" size="lg">Отмена</x-nexor::admin.button>
            </div>
        </div>
    </form>
@endsection
