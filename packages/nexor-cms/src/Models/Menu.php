<?php

namespace Nexor\Cms\Models;

use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Factories\Factory;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Nexor\Cms\Database\Factories\MenuFactory;
use Nexor\Cms\Support\MenuResolver;

/**
 * Меню сайта: шапка, подвал, боковая колонка.
 *
 * Шаблон зовёт меню по символьному коду, поэтому вёрстка не зависит от того,
 * что редактор наменял внутри.
 */
#[Fillable(['code', 'name', 'description', 'is_active', 'sort'])]
class Menu extends Model
{
    /** @use HasFactory<MenuFactory> */
    use HasFactory;

    /**
     * Laravel guesses factories from the application namespace, which never
     * matches a package model.
     */
    protected static function newFactory(): Factory
    {
        return MenuFactory::new();
    }

    protected static function booted(): void
    {
        // Меню рисуется на каждой странице, поэтому дерево кешируется — и сбрасывается
        // при любой правке, иначе редактор не видит результата своих действий.
        static::saved(fn () => MenuResolver::forget());
        static::deleted(fn () => MenuResolver::forget());
    }

    protected function casts(): array
    {
        return [
            'is_active' => 'boolean',
            'sort' => 'integer',
        ];
    }

    /**
     * @return HasMany<MenuItem, $this>
     */
    public function items(): HasMany
    {
        return $this->hasMany(MenuItem::class)->orderBy('sort')->orderBy('id');
    }

    /**
     * Пункты верхнего уровня.
     *
     * @return HasMany<MenuItem, $this>
     */
    public function rootItems(): HasMany
    {
        return $this->items()->whereNull('parent_id');
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
