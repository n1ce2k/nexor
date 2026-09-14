<?php

namespace Nexor\Cms\Models;

use Carbon\CarbonInterface;
use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Factories\Factory;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\BelongsToMany;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\Relations\HasOne;
use Illuminate\Database\Eloquent\SoftDeletes;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\Storage;
use Nexor\Cms\Contracts\NexorUser;
use Nexor\Cms\Database\Factories\IblockElementFactory;
use Nexor\Cms\Enums\ElementUrl;
use Nexor\Cms\Support\Nexor;
use Nexor\Cms\Support\Site;

#[Fillable([
    'iblock_id', 'section_id', 'code', 'name',
    'preview_picture', 'preview_text', 'preview_text_type',
    'detail_picture', 'detail_text', 'detail_text_type',
    'is_active', 'sort', 'active_from', 'active_to',
    'meta_title', 'meta_description', 'meta_keywords',
])]
class IblockElement extends Model
{
    /** @use HasFactory<IblockElementFactory> */
    use HasFactory, SoftDeletes;

    /**
     * Laravel guesses factories from the application namespace, which never
     * matches a package model.
     */
    protected static function newFactory(): Factory
    {
        return IblockElementFactory::new();
    }

    protected static function booted(): void
    {
        // Предложения без товара не имеют смысла: уходят вместе с ним.
        static::deleting(function (self $element): void {
            $offerIds = CatalogProduct::query()
                ->where('parent_element_id', $element->id)
                ->pluck('element_id');

            if ($offerIds->isEmpty()) {
                return;
            }

            self::query()->whereKey($offerIds)->get()->each(
                fn (self $offer) => $element->isForceDeleting() ? $offer->forceDelete() : $offer->delete(),
            );
        });
    }

    protected function casts(): array
    {
        return [
            'is_active' => 'boolean',
            'sort' => 'integer',
            'views' => 'integer',
            'active_from' => 'datetime',
            'active_to' => 'datetime',
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
     * @return BelongsTo<IblockSection, $this>
     */
    public function section(): BelongsTo
    {
        return $this->belongsTo(IblockSection::class, 'section_id');
    }

    /**
     * @return BelongsToMany<IblockSection, $this>
     */
    public function sections(): BelongsToMany
    {
        return $this->belongsToMany(IblockSection::class, 'iblock_element_section', 'element_id', 'section_id');
    }

    /**
     * @return HasMany<IblockElementValue, $this>
     */
    public function values(): HasMany
    {
        return $this->hasMany(IblockElementValue::class, 'element_id')->orderBy('sort')->orderBy('id');
    }

    /**
     * Цена, скидка и остатки — у элементов торгового каталога.
     *
     * @return HasOne<CatalogProduct, $this>
     */
    public function catalog(): HasOne
    {
        return $this->hasOne(CatalogProduct::class, 'element_id');
    }

    /**
     * Активные торговые предложения этого товара, по порядку.
     *
     * @return Collection<int, self>
     */
    public function offerElements(): Collection
    {
        if (! Nexor::feature('catalog.offers')) {
            return collect();
        }

        $offerIds = CatalogProduct::query()
            ->where('parent_element_id', $this->id)
            ->pluck('element_id');

        if ($offerIds->isEmpty()) {
            return collect();
        }

        return self::query()->whereKey($offerIds)->active()->with('catalog')->ordered()->get();
    }

    /**
     * @return BelongsTo<covariant Model, $this>
     */
    public function creator(): BelongsTo
    {
        return $this->belongsTo(Nexor::userModel(), 'created_by');
    }

    /**
     * @return BelongsTo<covariant Model, $this>
     */
    public function editor(): BelongsTo
    {
        return $this->belongsTo(Nexor::userModel(), 'updated_by');
    }

    /**
     * Property values keyed by property code.
     *
     * Multiple properties return a collection of values, single ones a scalar.
     *
     * @return Collection<string, mixed>
     */
    public function propertyValues(): Collection
    {
        $this->loadMissing(['values.property', 'values.enum', 'iblock.properties']);

        return $this->iblock->properties->mapWithKeys(function (IblockProperty $property) {
            $values = $this->values
                ->where('property_id', $property->id)
                ->map(fn (IblockElementValue $value) => $value->resolved())
                ->values();

            return [$property->code => $property->is_multiple ? $values : $values->first()];
        });
    }

    public function property(string $code): mixed
    {
        return $this->propertyValues()->get($code);
    }

    /**
     * Short, printable rendering of one property's value, for list columns.
     */
    public function displayValue(IblockProperty $property): string
    {
        $values = $this->values->where('property_id', $property->id);

        if ($values->isEmpty()) {
            return '—';
        }

        return $values->map(function (IblockElementValue $value) use ($property): string {
            $resolved = $value->resolved();

            return match (true) {
                $resolved instanceof self => $resolved->name,
                $resolved instanceof IblockSection => $resolved->name,
                $resolved instanceof NexorUser => $resolved->name,
                $resolved instanceof CarbonInterface => $resolved->format('d.m.Y'),
                is_bool($resolved) => $resolved ? 'да' : 'нет',
                is_array($resolved) => json_encode($resolved, JSON_UNESCAPED_UNICODE),
                $property->type->isFile() => basename((string) $resolved),
                default => (string) $resolved,
            };
        })->join(', ');
    }

    /**
     * Public address of the element.
     *
     * Elements of the "Страницы" infoblock are the site's own static pages and
     * sit at the root; everything else lives under its infoblock's folder.
     */
    public function url(): string
    {
        $this->loadMissing(['iblock', 'section']);

        $code = $this->code ?: (string) $this->id;

        if ($this->iblock?->code === Site::PAGES) {
            return url('/'.$code);
        }

        // Предложение открывается на странице своего товара: /katalog/…/futbolka/razmer-m.
        if ($this->iblock?->product_iblock_id && ($product = $this->parentProduct())) {
            return rtrim($product->url(), '/').'/'.$code;
        }

        // С разделами элемент лежит внутри их пути, как файл в папке; без — сразу под инфоблоком.
        $section = $this->iblock?->element_url === ElementUrl::Flat ? null : $this->section?->url_path;

        return url('/'.$this->iblock?->code.'/'.($section ? $section.'/' : '').$code);
    }

    /**
     * Товар, к которому относится это торговое предложение.
     */
    public function parentProduct(): ?self
    {
        $parentId = $this->loadMissing('catalog')->catalog?->parent_element_id;

        return $parentId ? self::query()->with(['iblock', 'section'])->find($parentId) : null;
    }

    /**
     * Активное предложение этого товара по коду или id — как оно стоит в адресе.
     */
    public function findOffer(string $codeOrId): ?self
    {
        $offersIblock = $this->loadMissing('iblock')->iblock;

        if (! $offersIblock?->hasOffers()) {
            return null;
        }

        return self::query()
            ->where('iblock_id', $offersIblock->offers_iblock_id)
            ->where(fn (Builder $query) => ctype_digit($codeOrId)
                ? $query->where('code', $codeOrId)->orWhere('id', (int) $codeOrId)
                : $query->where('code', $codeOrId))
            ->whereHas('catalog', fn (Builder $query) => $query->where('parent_element_id', $this->id))
            ->active()
            ->with(['catalog', 'values.property', 'values.enum'])
            ->first();
    }

    public function getPreviewPictureUrlAttribute(): ?string
    {
        return $this->preview_picture ? Storage::disk('public')->url($this->preview_picture) : null;
    }

    public function getDetailPictureUrlAttribute(): ?string
    {
        return $this->detail_picture ? Storage::disk('public')->url($this->detail_picture) : null;
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
        $query->where('is_active', true)
            ->where(fn (Builder $q) => $q->whereNull('active_from')->orWhere('active_from', '<=', now()))
            ->where(fn (Builder $q) => $q->whereNull('active_to')->orWhere('active_to', '>=', now()));
    }
}
