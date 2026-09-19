<?php

namespace Nexor\Cms\Models;

use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Factories\Factory;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Nexor\Cms\Database\Factories\MenuItemFactory;
use Nexor\Cms\Enums\MenuItemType;
use Nexor\Cms\Enums\MenuVisibility;
use Nexor\Cms\Support\MenuResolver;

/**
 * Пункт меню.
 *
 * Одна таблица на все типы: у ссылки заполнен `url`, у страницы и раздела —
 * привязка к сущности, у динамического пункта — инфоблок и глубина. Так дерево
 * остаётся одним запросом, а форма показывает только поля своего типа.
 */
#[Fillable([
    'menu_id', 'parent_id', 'type', 'title', 'url',
    'iblock_id', 'element_id', 'section_id', 'max_depth', 'with_elements', 'with_title',
    'target', 'css_class', 'icon', 'visibility',
    'highlight_children', 'is_active', 'sort',
])]
class MenuItem extends Model
{
    /** @use HasFactory<MenuItemFactory> */
    use HasFactory;

    /**
     * Laravel guesses factories from the application namespace, which never
     * matches a package model.
     */
    protected static function newFactory(): Factory
    {
        return MenuItemFactory::new();
    }

    protected static function booted(): void
    {
        // Меню рисуется на каждой странице, поэтому дерево кешируется — и сбрасывается
        // при любой правке, иначе редактор не видит результата своих действий.
        static::saved(fn () => MenuResolver::forget());
        static::deleted(fn () => MenuResolver::forget());
    }

    /**
     * Повторяет умолчания колонок: база подставляет их при вставке, но
     * только что созданная модель про них не знает и падает на первом же обращении.
     *
     * @var array<string, mixed>
     */
    protected $attributes = [
        'type' => 'link',
        'visibility' => 'all',
        'max_depth' => 2,
        'with_elements' => false,
        'with_title' => false,
        'highlight_children' => true,
        'is_active' => true,
        'sort' => 500,
    ];

    protected function casts(): array
    {
        return [
            'type' => MenuItemType::class,
            'visibility' => MenuVisibility::class,
            'max_depth' => 'integer',
            'with_elements' => 'boolean',
            'with_title' => 'boolean',
            'highlight_children' => 'boolean',
            'is_active' => 'boolean',
            'sort' => 'integer',
        ];
    }

    /**
     * @return BelongsTo<Menu, $this>
     */
    public function menu(): BelongsTo
    {
        return $this->belongsTo(Menu::class);
    }

    /**
     * @return BelongsTo<self, $this>
     */
    public function parent(): BelongsTo
    {
        return $this->belongsTo(self::class, 'parent_id');
    }

    /**
     * @return HasMany<self, $this>
     */
    public function children(): HasMany
    {
        return $this->hasMany(self::class, 'parent_id')->orderBy('sort')->orderBy('id');
    }

    /**
     * @return BelongsTo<Iblock, $this>
     */
    public function iblock(): BelongsTo
    {
        return $this->belongsTo(Iblock::class);
    }

    /**
     * @return BelongsTo<IblockElement, $this>
     */
    public function element(): BelongsTo
    {
        return $this->belongsTo(IblockElement::class, 'element_id');
    }

    /**
     * @return BelongsTo<IblockSection, $this>
     */
    public function section(): BelongsTo
    {
        return $this->belongsTo(IblockSection::class, 'section_id');
    }

    /**
     * @param  Builder<$this>  $query
     */
    public function scopeActive(Builder $query): void
    {
        $query->where('is_active', true);
    }
}
