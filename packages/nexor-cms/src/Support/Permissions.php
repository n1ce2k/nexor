<?php

namespace Nexor\Cms\Support;

use Nexor\Cms\Models\Iblock;
use Nexor\Cms\Models\Permission;
use Nexor\Cms\Models\Role;

/**
 * Single source of truth for the permission catalogue.
 *
 * Static permissions describe the admin panel itself; per-infoblock permissions
 * are generated from the `iblocks` table so a new infoblock immediately becomes
 * something roles can be granted access to.
 */
class Permissions
{
    public const ACCESS_ADMIN = 'admin.access';

    /**
     * @return array<string, array{label: string, sort: int, items: array<string, string>}>
     */
    public static function definitions(): array
    {
        return [
            'system' => [
                'label' => 'Система',
                'sort' => 100,
                'items' => [
                    self::ACCESS_ADMIN => 'Доступ в админку',
                ],
            ],
            'users' => [
                'label' => 'Пользователи',
                'sort' => 200,
                'items' => [
                    'users.view' => 'Просмотр пользователей',
                    'users.create' => 'Создание пользователей',
                    'users.update' => 'Изменение пользователей',
                    'users.delete' => 'Удаление пользователей',
                ],
            ],
            'roles' => [
                'label' => 'Роли и права',
                'sort' => 300,
                'items' => [
                    'roles.view' => 'Просмотр ролей',
                    'roles.create' => 'Создание ролей',
                    'roles.update' => 'Изменение ролей',
                    'roles.delete' => 'Удаление ролей',
                ],
            ],
            'iblock_types' => [
                'label' => 'Типы инфоблоков',
                'sort' => 400,
                'items' => [
                    'iblock_types.view' => 'Просмотр типов',
                    'iblock_types.create' => 'Создание типов',
                    'iblock_types.update' => 'Изменение типов',
                    'iblock_types.delete' => 'Удаление типов',
                ],
            ],
            'iblocks' => [
                'label' => 'Инфоблоки (структура)',
                'sort' => 500,
                'items' => [
                    'iblocks.view' => 'Просмотр инфоблоков',
                    'iblocks.create' => 'Создание инфоблоков',
                    'iblocks.update' => 'Изменение инфоблоков и свойств',
                    'iblocks.delete' => 'Удаление инфоблоков',
                ],
            ],
            'settings' => [
                'label' => 'Настройки',
                'sort' => 600,
                'items' => [
                    'settings.view' => 'Просмотр настроек',
                    'settings.update' => 'Изменение настроек',
                ],
            ],
            'mail' => [
                'label' => 'Почта',
                'sort' => 650,
                'items' => [
                    'mail.view' => 'Просмотр почтовых настроек и шаблонов',
                    'mail.update' => 'Изменение почтовых настроек и шаблонов',
                ],
            ],
            'logs' => [
                'label' => 'Журнал',
                'sort' => 700,
                'items' => [
                    'logs.view' => 'Просмотр журнала действий',
                ],
            ],
        ];
    }

    /**
     * Labels for the four abilities every infoblock gets.
     *
     * @return array<string, string>
     */
    public static function iblockAbilityLabels(): array
    {
        return [
            'view' => 'Просмотр',
            'create' => 'Добавление',
            'update' => 'Изменение',
            'delete' => 'Удаление',
        ];
    }

    /**
     * Create any missing permission rows for the static catalogue.
     */
    public static function syncStatic(): void
    {
        foreach (self::definitions() as $group => $definition) {
            $sort = $definition['sort'];

            foreach ($definition['items'] as $code => $name) {
                Permission::query()->updateOrCreate(
                    ['code' => $code],
                    [
                        'name' => $name,
                        'group' => $group,
                        'group_label' => $definition['label'],
                        'sort' => $sort += 10,
                    ],
                );
            }
        }
    }

    /**
     * Create the four content permissions for one infoblock.
     */
    public static function syncIblock(Iblock $iblock): void
    {
        $sort = 1000;

        foreach (self::iblockAbilityLabels() as $ability => $label) {
            Permission::query()->updateOrCreate(
                ['code' => $iblock->permissionCode($ability)],
                [
                    'name' => "{$label}: {$iblock->name}",
                    'group' => $iblock->permissionGroup(),
                    'group_label' => 'Контент: '.$iblock->name,
                    'sort' => $sort += 10,
                ],
            );
        }
    }

    public static function forgetIblock(Iblock $iblock): void
    {
        Permission::query()->where('group', $iblock->permissionGroup())->delete();
    }

    /**
     * Rebuild the whole catalogue, then grant everything to the super admin role.
     */
    public static function syncAll(): void
    {
        self::syncStatic();

        $iblocks = Iblock::query()->withTrashed()->get();

        $iblocks->each(self::syncIblock(...));

        // Drop content permissions left behind by infoblocks that no longer exist.
        Permission::query()
            ->where('group', 'like', 'iblock:%')
            ->whereNotIn('group', $iblocks->map->permissionGroup()->all())
            ->delete();

        $superAdmin = Role::query()->where('code', Role::SUPER_ADMIN)->first();

        $superAdmin?->permissions()->sync(Permission::query()->pluck('id'));
    }
}
