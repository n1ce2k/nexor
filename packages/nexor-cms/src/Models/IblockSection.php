<?php

namespace Nexor\Cms\Models;

use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Collection;
use Illuminate\Database\Eloquent\Factories\Factory;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\BelongsToMany;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Nexor\Cms\Database\Factories\IblockSectionFactory;

#[Fillable([
    'iblock_id', 'parent_id', 'code', 'name', 'picture', 'description',
    'is_active', 'sort', 'meta_title', 'meta_description', 'meta_keywords',
])]
class IblockSection extends Model
{
    /** @use HasFactory<IblockSectionFactory> */
    use HasFactory;

    /**
     * Laravel guesses factories from the application namespace, which never
     * matches a package model.
     */
    protected static function newFactory(): Factory
    {
        return IblockSectionFactory::new();
    }

    protected function casts(): array
    {
        return [
            'is_active' => 'boolean',
            'sort' => 'integer',
            'depth' => 'integer',
        ];
    }

    protected static function booted(): void
    {
        static::saving(function (self $section): void {
            $section->refreshTreeAttributes();
        });

        static::saved(function (self $section): void {
            if ($section->wasChanged(['parent_id', 'path', 'depth'])) {
                $section->refreshDescendantsTree();
            }
        });
    }

    /**
     * @return BelongsTo<Iblock, $this>
     */
    public function iblock(): BelongsTo
    {
        return $this->belongsTo(Iblock::class);
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
        return $this->hasMany(self::class, 'parent_id')->orderBy('sort')->orderBy('name');
    }

    /**
     * @return HasMany<IblockElement, $this>
     */
    public function elements(): HasMany
    {
        return $this->hasMany(IblockElement::class, 'section_id');
    }

    /**
     * @return BelongsToMany<IblockElement, $this>
     */
    public function allElements(): BelongsToMany
    {
        return $this->belongsToMany(IblockElement::class, 'iblock_element_section', 'section_id', 'element_id');
    }

    /**
     * Materialised path of ancestor ids, e.g. `/1/7/`, kept in sync on save.
     */
    protected function refreshTreeAttributes(): void
    {
        $parent = $this->parent_id ? self::find($this->parent_id) : null;

        $this->depth = $parent ? $parent->depth + 1 : 0;
        $this->path = $parent ? $parent->path.$parent->id.'/' : '/';
    }

    protected function refreshDescendantsTree(): void
    {
        self::where('path', 'like', $this->path.$this->id.'/%')
            ->orWhere('parent_id', $this->id)
            ->get()
            ->each(fn (self $child) => $child->save());
    }

    /**
     * @return Collection<int, self>
     */
    public function descendants(): Collection
    {
        return self::where('iblock_id', $this->iblock_id)
            ->where('path', 'like', $this->path.$this->id.'/%')
            ->orderBy('path')
            ->orderBy('sort')
            ->get();
    }

    /**
     * @return Collection<int, self>
     */
    public function ancestors(): Collection
    {
        $ids = array_filter(explode('/', (string) $this->path));

        if ($ids === []) {
            return new Collection;
        }

        return self::whereIn('id', $ids)->orderBy('depth')->get();
    }

    /**
     * Name prefixed with non-breaking indentation, for use in flat select lists.
     */
    public function getIndentedNameAttribute(): string
    {
        return str_repeat('— ', $this->depth).$this->name;
    }

    /**
     * @param  Builder<$this>  $query
     */
    public function scopeOrdered(Builder $query): void
    {
        $query->orderBy('path')->orderBy('sort')->orderBy('name');
    }

    /**
     * @param  Builder<$this>  $query
     */
    public function scopeActive(Builder $query): void
    {
        $query->where('is_active', true);
    }
}
