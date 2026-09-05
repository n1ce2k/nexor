<?php

namespace Tests\Concerns;

use App\Models\User;
use Nexor\Cms\Models\Iblock;
use Nexor\Cms\Models\Permission;
use Nexor\Cms\Models\Role;
use Nexor\Cms\Support\Permissions;

trait CreatesAdminUsers
{
    /**
     * A user who can reach the panel and holds exactly the given permissions.
     *
     * @param  array<int, string>  $permissions
     */
    protected function adminWith(array $permissions = []): User
    {
        $user = User::factory()->create(['is_active' => true]);

        $role = Role::factory()
            ->withPermissions([Permissions::ACCESS_ADMIN, ...$permissions])
            ->create();

        $user->roles()->attach($role);

        return $user->fresh();
    }

    /**
     * A super admin: passes every permission check without explicit grants.
     */
    protected function superAdmin(): User
    {
        return User::factory()->create(['is_active' => true, 'is_super_admin' => true]);
    }

    /**
     * Ensure the four content permissions of an infoblock exist, then grant them.
     *
     * @param  array<int, string>  $abilities
     */
    protected function grantIblock(User $user, Iblock $iblock, array $abilities = ['view']): User
    {
        Permissions::syncIblock($iblock);

        $ids = Permission::query()
            ->whereIn('code', array_map($iblock->permissionCode(...), $abilities))
            ->pluck('id');

        $user->roles->first()?->permissions()->syncWithoutDetaching($ids);

        return $user->fresh();
    }
}
