<?php

namespace Nexor\Cms\Models;

use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Factories\Factory;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Nexor\Cms\Database\Factories\IblockPropertyFactory;
use Nexor\Cms\Enums\PropertyType;

#[Fillable([
    'iblock_id', 'code', 'name', 'hint', 'type',
    'is_multiple', 'is_required', 'is_filterable', 'is_searchable', 'is_shown_in_list', 'is_active',
    'sort', 'default_value', 'with_description', 'settings',
])]
class IblockProperty extends Model
{
    /** @use HasFactory<IblockPropertyFactory> */
    use HasFactory;

    /**
     * Laravel guesses factories from the application namespace, which never
     * matches a package model.
     */
    protected static function newFactory(): Factory
    {
        return IblockPropertyFactory::new();
    }

    protected function casts(): array
    {
        return [
            'type' => PropertyType::class,
            'is_multiple' => 'boolean',
            'is_required' => 'boolean',
            'is_filterable' => 'boolean',
            'is_searchable' => 'boolean',
            'is_shown_in_list' => 'boolean',
            'with_description' => 'boolean',
            'is_active' => 'boolean',
            'sort' => 'integer',
            'settings' => 'array',
        ];
    }

    /**
     * @return BelongsTo<Iblock, $this>
     */
    public function iblock(): BelongsTo
    {
        return $this->belongsTo(Iblock::class);
    }

    /**
     * @return HasMany<IblockPropertyEnum, $this>
     */
    public function enums(): HasMany
    {
        return $this->hasMany(IblockPropertyEnum::class, 'property_id')->orderBy('sort')->orderBy('id');
    }

    /**
     * @return HasMany<IblockElementValue, $this>
     */
    public function values(): HasMany
    {
        return $this->hasMany(IblockElementValue::class, 'property_id');
    }

    /**
     * Infoblock this property links to, for `element` and `section` types.
     */
    public function linkedIblock(): ?Iblock
    {
        $id = $this->setting('link_iblock_id');

        return $id ? Iblock::find($id) : null;
    }

    public function setting(string $key, mixed $default = null): mixed
    {
        return data_get($this->settings, $key, $default);
    }

    public function storageColumn(): string
    {
        return $this->type->column();
    }

    /**
     * @param  Builder<$this>  $query
     */
    public function scopeOrdered(Builder $query): void
    {
        $query->orderBy('sort')->orderBy('id');
    }

    /**
     * @param  Builder<$this>  $query
     */
    public function scopeActive(Builder $query): void
    {
        $query->where('is_active', true);
    }
}
