<?php

namespace Nexor\Cms\Models;

use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Factories\Factory;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Nexor\Cms\Database\Factories\IblockTypeFactory;

#[Fillable([
    'code', 'name', 'sections_name', 'elements_name', 'description',
    'has_sections', 'is_active', 'sort',
])]
class IblockType extends Model
{
    /** @use HasFactory<IblockTypeFactory> */
    use HasFactory;

    /**
     * Laravel guesses factories from the application namespace, which never
     * matches a package model.
     */
    protected static function newFactory(): Factory
    {
        return IblockTypeFactory::new();
    }

    protected function casts(): array
    {
        return [
            'has_sections' => 'boolean',
            'is_active' => 'boolean',
            'sort' => 'integer',
        ];
    }

    /**
     * @return HasMany<Iblock, $this>
     */
    public function iblocks(): HasMany
    {
        return $this->hasMany(Iblock::class);
    }

    public function getSectionsNameAttribute(?string $value): string
    {
        return $value ?: 'Разделы';
    }

    public function getElementsNameAttribute(?string $value): string
    {
        return $value ?: 'Элементы';
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
