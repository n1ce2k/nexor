<?php

namespace Nexor\Cms\Database\Seeders;

use Illuminate\Database\Seeder;
use Nexor\Cms\Models\Permission;
use Nexor\Cms\Models\Role;
use Nexor\Cms\Support\Permissions;

class RoleSeeder extends Seeder
{
    public function run(): void
    {
        Permissions::syncStatic();

        $roles = [
            [
                'code' => Role::SUPER_ADMIN,
                'name' => 'Супер-администратор',
                'description' => 'Полный доступ ко всем разделам без ограничений.',
                'is_system' => true,
                'sort' => 100,
                'permissions' => '*',
            ],
            [
                'code' => 'administrator',
                'name' => 'Администратор',
                'description' => 'Управляет контентом, пользователями и настройками.',
                'is_system' => true,
                'sort' => 200,
                'permissions' => [
                    Permissions::ACCESS_ADMIN,
                    'users.view', 'users.create', 'users.update', 'users.delete',
                    'roles.view',
                    'iblock_types.view',
                    'iblocks.view', 'iblocks.create', 'iblocks.update',
                    'settings.view', 'settings.update',
                    'logs.view',
                ],
            ],
            [
                'code' => 'content-manager',
                'name' => 'Контент-менеджер',
                'description' => 'Наполняет инфоблоки контентом, структуру не меняет.',
                'is_system' => true,
                'sort' => 300,
                'permissions' => [
                    Permissions::ACCESS_ADMIN,
                    'iblocks.view',
                    'iblock_types.view',
                ],
            ],
            [
                'code' => 'viewer',
                'name' => 'Наблюдатель',
                'description' => 'Только просмотр разделов админки.',
                'is_system' => true,
                'sort' => 400,
                'permissions' => [
                    Permissions::ACCESS_ADMIN,
                    'users.view',
                    'roles.view',
                    'iblocks.view',
                    'iblock_types.view',
                    'settings.view',
                    'logs.view',
                ],
            ],
        ];

        foreach ($roles as $definition) {
            $permissions = $definition['permissions'];
            unset($definition['permissions']);

            $role = Role::query()->updateOrCreate(['code' => $definition['code']], $definition);

            $ids = $permissions === '*'
                ? Permission::query()->pluck('id')
                : Permission::query()->whereIn('code', $permissions)->pluck('id');

            $role->permissions()->sync($ids);
        }
    }
}
