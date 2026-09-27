<?php

namespace Nexor\Cms\Models;

use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Model;

/**
 * Код счётчика, который подключается только с согласия посетителя.
 */
#[Fillable(['name', 'category', 'placement', 'code', 'is_active', 'sort'])]
class CookieCounter extends Model
{
    /** Категории согласия, к которым можно привязать счётчик. */
    public const CATEGORIES = ['analytics' => 'Аналитика', 'marketing' => 'Маркетинг'];

    /** Куда вставлять код. */
    public const PLACEMENTS = ['head' => 'В <head>', 'body' => 'Перед </body>'];

    protected $table = 'cookie_counters';

    protected function casts(): array
    {
        return [
            'is_active' => 'boolean',
            'sort' => 'integer',
        ];
    }

    /**
     * @param  Builder<$this>  $query
     */
    public function scopeActive(Builder $query): void
    {
        $query->where('is_active', true);
    }

    /**
     * @param  Builder<$this>  $query
     */
    public function scopeOrdered(Builder $query): void
    {
        $query->orderBy('sort')->orderBy('id');
    }
}
