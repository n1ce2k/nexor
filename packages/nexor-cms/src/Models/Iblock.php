<?php

namespace Nexor\Cms\Models;

use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Factories\Factory;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\SoftDeletes;
use Nexor\Cms\Database\Factories\IblockFactory;
use Nexor\Cms\Support\Permissions;

#[Fillable([
    'iblock_type_id', 'code', 'name', 'picture', 'description',
    'list_url', 'section_url', 'detail_url',
    'has_sections', 'has_page', 'is_active', 'sort', 'settings',
])]
class Iblock extends Model
{
    /** @use HasFactory<IblockFactory> */
    use HasFactory, SoftDeletes;

    /**
     * Laravel guesses factories from the application namespace, which never
     * matches a package model.
     */
    protected static function newFactory(): Factory
    {
        return IblockFactory::new();
    }

    protected function casts(): array
    {
        return [
            'has_sections' => 'boolean',
            'has_page' => 'boolean',
            'is_active' => 'boolean',
            'sort' => 'integer',
            'settings' => 'array',
        ];
    }

    protected static function booted(): void
    {
        // Every infoblock owns four content permissions; keep them in step with it.
        static::saved(function (self $iblock): void {
            if ($iblock->wasRecentlyCreated || $iblock->wasChanged('name')) {
                Permissions::syncIblock($iblock);
            }
        });

        static::forceDeleted(fn (self $iblock) => Permissions::forgetIblock($iblock));
    }

    /**
     * @return BelongsTo<IblockType, $this>
     */
    public function type(): BelongsTo
    {
        return $this->belongsTo(IblockType::class, 'iblock_type_id');
    }

    /**
     * @return HasMany<IblockSection, $this>
     */
    public function sections(): HasMany
    {
        return $this->hasMany(IblockSection::class);
    }

    /**
     * @return HasMany<IblockProperty, $this>
     */
    public function properties(): HasMany
    {
        return $this->hasMany(IblockProperty::class)->orderBy('sort')->orderBy('id');
    }

    /**
     * @return HasMany<IblockElement, $this>
     */
    public function elements(): HasMany
    {
        return $this->hasMany(IblockElement::class);
    }

    /**
     * Permission code for one ability on this infoblock, e.g. `iblock.7.update`.
     *
     * Keyed by id rather than symbolic code so renaming an infoblock never
     * detaches the permissions already granted to roles.
     */
    public function permissionCode(string $ability): string
    {
        return "iblock.{$this->id}.{$ability}";
    }

    public function permissionGroup(): string
    {
        return 'iblock:'.$this->id;
    }

    /**
     * @return array<int, string>
     */
    public static function abilities(): array
    {
        return ['view', 'create', 'update', 'delete'];
    }

    /**
     * @param  Builder<$this>  $query
     */
    public function scopeOrdered(Builder $query): void
    {
        $query->orderBy('sort')->orderBy('name');
    }

    /**
     * @param  Builder<$this>  $query
     */
    public function scopeActive(Builder $query): void
    {
        $query->where('is_active', true);
    }
}
