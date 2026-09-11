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
use Nexor\Cms\Enums\PaginationTemplate;
use Nexor\Cms\Support\Permissions;

#[Fillable([
    'iblock_type_id', 'code', 'name', 'element_name', 'picture', 'description',
    'list_url', 'section_url', 'detail_url',
    'has_sections', 'has_page', 'is_catalog', 'is_active', 'sort', 'settings',
    'pagination_template', 'per_page', 'has_load_more', 'load_more_size',
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

    /**
     * Mirrors the column defaults so a freshly created infoblock already
     * describes its paging before it is read back from the database.
     *
     * @var array<string, mixed>
     */
    protected $attributes = [
        'pagination_template' => PaginationTemplate::Simple->value,
        'per_page' => 20,
        'has_load_more' => false,
        'load_more_size' => 12,
    ];

    protected function casts(): array
    {
        return [
            'has_sections' => 'boolean',
            'has_page' => 'boolean',
            'is_catalog' => 'boolean',
            'pagination_template' => PaginationTemplate::class,
            'per_page' => 'integer',
            'has_load_more' => 'boolean',
            'load_more_size' => 'integer',
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
     * Инфоблок торговых предложений этого каталога.
     *
     * @return BelongsTo<self, $this>
     */
    public function offersIblock(): BelongsTo
    {
        return $this->belongsTo(self::class, 'offers_iblock_id');
    }

    /**
     * Каталог, чьи это предложения.
     *
     * @return BelongsTo<self, $this>
     */
    public function productIblock(): BelongsTo
    {
        return $this->belongsTo(self::class, 'product_iblock_id');
    }

    /**
     * Есть ли у элементов цена и остатки: у каталога и у его предложений.
     */
    public function hasCommerce(): bool
    {
        return $this->is_catalog || $this->product_iblock_id !== null;
    }

    /**
     * Есть ли у элементов торговые предложения.
     */
    public function hasOffers(): bool
    {
        return $this->is_catalog && $this->offers_iblock_id !== null;
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
     * Caption of the «добавить» button, e.g. «Добавить товар».
     *
     * An infoblock that never said what its elements are called just gets
     * «Добавить» — better than a made-up word in the interface.
     */
    public function addElementLabel(): string
    {
        $name = trim((string) $this->element_name);

        return $name === '' ? 'Добавить' : 'Добавить '.$name;
    }

    /**
     * How many elements one page of the public listing holds.
     *
     * Whenever the listing loads on demand — either because the operator turned
     * «Показать ещё» on or because the chosen template is the button itself —
     * the chunk size is what that switch configures.
     */
    public function pageSize(): int
    {
        $loadsOnDemand = $this->has_load_more || (bool) $this->pagination_template?->loadsOnDemand();

        $size = (int) ($loadsOnDemand ? $this->load_more_size : $this->per_page);

        return $size > 0 ? $size : 20;
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
