<?php

namespace Nexor\Shop\Models;

use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Factories\Factory;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Nexor\Shop\Database\Factories\DeliveryMethodFactory;

/**
 * Способ доставки. Цена — в валюте магазина.
 */
#[Fillable(['name', 'description', 'price', 'free_from', 'is_active', 'sort'])]
class DeliveryMethod extends Model
{
    /** @use HasFactory<DeliveryMethodFactory> */
    use HasFactory;

    protected $table = 'shop_delivery_methods';

    protected static function newFactory(): Factory
    {
        return DeliveryMethodFactory::new();
    }

    protected function casts(): array
    {
        return [
            'price' => 'decimal:2',
            'free_from' => 'decimal:2',
            'is_active' => 'boolean',
            'sort' => 'integer',
        ];
    }

    /**
     * Стоимость доставки для заказа на эту сумму.
     */
    public function priceFor(float $orderSum): float
    {
        if ($this->free_from !== null && $orderSum >= (float) $this->free_from) {
            return 0.0;
        }

        return (float) $this->price;
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
        $query->orderBy('sort')->orderBy('name');
    }
}
