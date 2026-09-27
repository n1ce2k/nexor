<?php

namespace Nexor\Shop\Models;

use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Factories\Factory;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Nexor\Shop\Database\Factories\DeliveryMethodFactory;
use Nexor\Shop\Enums\DeliveryProvider;

/**
 * Способ доставки. Цена — в валюте магазина.
 *
 * Своя цена задаётся здесь же (`price` и «бесплатно от»), а способ с провайдером
 * спрашивает её у службы доставки на оформлении — см. Support\Delivery.
 */
#[Fillable(['name', 'description', 'provider', 'settings', 'price', 'free_from', 'is_active', 'sort'])]
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
            'provider' => DeliveryProvider::class,
            'settings' => 'array',
            'price' => 'decimal:2',
            'free_from' => 'decimal:2',
            'is_active' => 'boolean',
            'sort' => 'integer',
        ];
    }

    /**
     * Стоимость доставки для заказа на эту сумму.
     *
     * У способа с провайдером своей цены нет: её считает служба доставки по
     * адресу покупателя, а здесь остаётся только порог «бесплатно от».
     */
    public function priceFor(float $orderSum): float
    {
        if ($this->isFree($orderSum)) {
            return 0.0;
        }

        return (float) $this->price;
    }

    /**
     * Бесплатна ли доставка при такой сумме заказа.
     */
    public function isFree(float $orderSum): bool
    {
        return $this->free_from !== null && $orderSum >= (float) $this->free_from;
    }

    /**
     * Цену считает служба доставки, а не настройки способа.
     */
    public function isCalculated(): bool
    {
        return ($this->provider ?? DeliveryProvider::None)->isCalculated();
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
