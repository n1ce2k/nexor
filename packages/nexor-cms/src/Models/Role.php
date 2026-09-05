<?php

namespace Nexor\Cms\Models;

use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Factories\Factory;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsToMany;
use Nexor\Cms\Database\Factories\RoleFactory;
use Nexor\Cms\Support\Nexor;

#[Fillable(['code', 'name', 'description', 'is_system', 'sort'])]
class Role extends Model
{
    /** @use HasFactory<RoleFactory> */
    use HasFactory;

    /**
     * Laravel guesses factories from the application namespace, which never
     * matches a package model.
     */
    protected static function newFactory(): Factory
    {
        return RoleFactory::new();
    }

    public const SUPER_ADMIN = 'super-admin';

    protected function casts(): array
    {
        return [
            'is_system' => 'boolean',
            'sort' => 'integer',
        ];
    }

    /**
     * @return BelongsToMany<Permission, $this>
     */
    public function permissions(): BelongsToMany
    {
        return $this->belongsToMany(Permission::class);
    }

    /**
     * @return BelongsToMany<covariant Model, $this>
     */
    public function users(): BelongsToMany
    {
        return $this->belongsToMany(Nexor::userModel());
    }

    public function isSuperAdmin(): bool
    {
        return $this->code === self::SUPER_ADMIN;
    }

    public function hasPermission(string $code): bool
    {
        return $this->isSuperAdmin() || $this->permissions->contains('code', $code);
    }

    /**
     * @param  Builder<$this>  $query
     */
    public function scopeOrdered(Builder $query): void
    {
        $query->orderBy('sort')->orderBy('name');
    }
}
