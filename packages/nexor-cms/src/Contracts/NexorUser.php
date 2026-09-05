<?php

namespace Nexor\Cms\Contracts;

use Illuminate\Database\Eloquent\Relations\BelongsToMany;
use Illuminate\Support\Collection;
use Nexor\Cms\Models\Role;

/**
 * Contract the host application's user model must satisfy.
 *
 * Satisfied in full by `Nexor\Cms\Models\Concerns\HasRoles`; the application's
 * model only has to use that trait and declare that it implements this.
 *
 * @property bool $is_active
 * @property bool $is_super_admin
 */
interface NexorUser
{
    /**
     * @return BelongsToMany<Role, covariant static>
     */
    public function roles(): BelongsToMany;

    public function hasRole(string $code): bool;

    /**
     * @param  array<int, string>  $codes
     */
    public function hasAnyRole(array $codes): bool;

    public function isSuperAdmin(): bool;

    public function hasPermission(string $code): bool;

    /**
     * @param  array<int, string>  $codes
     */
    public function hasAnyPermission(array $codes): bool;

    /**
     * @return Collection<int, string>
     */
    public function permissionCodes(): Collection;

    public function forgetCachedPermissions(): void;
}
