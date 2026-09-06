<?php

namespace Nexor\Cms\Support;

use Illuminate\Support\Collection;
use Nexor\Cms\Contracts\NexorUser;
use Nexor\Cms\Models\Iblock;

/**
 * Builds the admin sidebar for the signed-in user.
 *
 * The content section is generated from the infoblocks that actually exist, so a
 * newly created infoblock shows up in the menu without touching this class. URLs
 * and active state are resolved here, keeping the Blade partial declarative.
 *
 * @phpstan-type NavItem array{label: string, icon?: string, url?: string, active: bool, children?: array<int, NavItem>}
 */
class Navigation
{
    /**
     * @return Collection<int, array{label: ?string, items: array<int, array<string, mixed>>}>
     */
    public static function forUser(NexorUser $user): Collection
    {
        return collect([
            [
                'label' => null,
                'items' => [
                    self::link('Рабочий стол', 'dashboard', 'admin.dashboard'),
                ],
            ],
            //            [
            //                'label' => null,
            //                'items' => [
            //                    self::link('Сайт', 'dashboard', 'home'),
            //                ],
            //            ],
            [
                'label' => 'Контент',
                'items' => self::contentItems($user),
            ],
            [
                'label' => 'Структура',
                'items' => self::filtered([
                    'iblocks.view' => fn () => self::link('Инфоблоки', 'layers', 'admin.iblocks.index', pattern: [
                        'admin.iblocks.index', 'admin.iblocks.create', 'admin.iblocks.edit',
                        'admin.iblocks.show', 'admin.iblocks.properties.*',
                    ]),
                    'iblock_types.view' => fn () => self::link('Типы инфоблоков', 'database', 'admin.iblock-types.index', pattern: 'admin.iblock-types.*'),
                ], $user),
            ],
            [
                'label' => 'Администрирование',
                'items' => self::filtered([
                    'users.view' => fn () => self::link('Пользователи', 'users', 'admin.users.index', pattern: 'admin.users.*'),
                    'roles.view' => fn () => self::link('Роли и права', 'shield', 'admin.roles.index', pattern: 'admin.roles.*'),
                    'settings.view' => fn () => self::link('Настройки сайта', 'settings', 'admin.settings.index', pattern: 'admin.settings.*'),
                    'logs.view' => fn () => self::link('Журнал действий', 'clock', 'admin.logs.index', pattern: 'admin.logs.*'),
                ], $user),
            ],
        ])->reject(fn (array $group) => $group['items'] === [])->values();
    }

    /**
     * @param  array<string, callable(): (array<string, mixed>|null)>  $definitions
     * @return array<int, array<string, mixed>>
     */
    protected static function filtered(array $definitions, NexorUser $user): array
    {
        $items = [];

        foreach ($definitions as $permission => $factory) {
            if (! $user->hasPermission($permission)) {
                continue;
            }

            if ($item = $factory()) {
                $items[] = $item;
            }
        }

        return $items;
    }

    /**
     * @param  array<string, mixed>  $params
     * @param  string|array<int, string>|null  $pattern
     * @return array<string, mixed>
     */
    protected static function link(string $label, string $icon, string $route, array $params = [], string|array|null $pattern = null): array
    {
        return [
            'label' => $label,
            'icon' => $icon,
            'url' => route($route, $params),
            'active' => request()->routeIs(...(array) ($pattern ?? $route)),
        ];
    }

    /**
     * Infoblocks the user may open, grouped under their type.
     *
     * @return array<int, array<string, mixed>>
     */
    protected static function contentItems(NexorUser $user): array
    {
        $iblocks = Iblock::query()
            ->active()
            ->with('type')
            ->ordered()
            ->get()
            ->filter(fn (Iblock $iblock) => $user->hasPermission($iblock->permissionCode('view')));

        if ($iblocks->isEmpty()) {
            return [];
        }

        $currentIblock = self::currentIblockId();

        return $iblocks
            ->groupBy(fn (Iblock $iblock) => $iblock->type?->name ?? 'Прочее')
            ->map(function (Collection $group, string $typeName) use ($currentIblock): array {
                $children = $group->map(fn (Iblock $iblock) => [
                    'label' => $iblock->name,
                    'url' => route('admin.iblocks.elements.index', $iblock),
                    'active' => $currentIblock === $iblock->id
                        && request()->routeIs('admin.iblocks.elements.*', 'admin.iblocks.sections.*'),
                ])->values()->all();

                return [
                    'label' => $typeName,
                    'icon' => 'folder',
                    'active' => collect($children)->contains('active', true),
                    'children' => $children,
                ];
            })
            ->values()
            ->all();
    }

    /**
     * Id of the infoblock the current request is scoped to, if any.
     */
    protected static function currentIblockId(): ?int
    {
        $parameter = request()->route('iblock');

        return match (true) {
            $parameter instanceof Iblock => $parameter->id,
            is_numeric($parameter) => (int) $parameter,
            default => null,
        };
    }
}
