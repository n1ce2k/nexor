<?php

namespace Nexor\Cms\Models\Concerns;

use Illuminate\Database\Eloquent\Relations\BelongsToMany;
use Illuminate\Support\Collection;
use Nexor\Cms\Models\Role;

trait HasRoles
{
    /** @var Collection<int, string>|null */
    private ?Collection $cachedPermissionCodes = null;

    /**
     * @return BelongsToMany<Role, $this>
     */
    public function roles(): BelongsToMany
    {
        return $this->belongsToMany(Role::class);
    }

    public function hasRole(string $code): bool
    {
        return $this->roles->contains('code', $code);
    }

    /**
     * @param  array<int, string>  $codes
     */
    public function hasAnyRole(array $codes): bool
    {
        return $this->roles->whereIn('code', $codes)->isNotEmpty();
    }

    public function isSuperAdmin(): bool
    {
        return (bool) $this->is_super_admin || $this->hasRole(Role::SUPER_ADMIN);
    }

    public function hasPermission(string $code): bool
    {
        if ($this->isSuperAdmin()) {
            return true;
        }

        return $this->permissionCodes()->contains($code);
    }

    /**
     * @param  array<int, string>  $codes
     */
    public function hasAnyPermission(array $codes): bool
    {
        if ($this->isSuperAdmin()) {
            return true;
        }

        return $this->permissionCodes()->intersect($codes)->isNotEmpty();
    }

    /**
     * All permission codes granted through this user's roles.
     *
     * @return Collection<int, string>
     */
    public function permissionCodes(): Collection
    {
        return $this->cachedPermissionCodes ??= $this->roles
            ->loadMissing('permissions')
            ->flatMap(fn (Role $role) => $role->permissions->pluck('code'))
            ->unique()
            ->values();
    }

    public function forgetCachedPermissions(): void
    {
        $this->cachedPermissionCodes = null;
        $this->unsetRelation('roles');
    }
}
