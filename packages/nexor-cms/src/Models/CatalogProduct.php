<?php

namespace Nexor\Cms\Models;

use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Factories\Factory;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Nexor\Cms\Database\Factories\CatalogProductFactory;
use Nexor\Cms\Enums\Currency;
use Nexor\Cms\Enums\ProductType;

/**
 * Торговые данные элемента: цена, скидка, остатки.
 *
 * Живут отдельно от свойств, как `b_catalog_product` в Битриксе: по цене и
 * наличию сортируют и фильтруют, и держать это в EAV было бы дорого.
 */
#[Fillable([
    'element_id', 'parent_element_id', 'type', 'offers_by_properties',
    'price', 'currency', 'discount_percent',
    'quantity', 'measure', 'ratio',
    'quantity_trace', 'can_buy_zero',
])]
class CatalogProduct extends Model
{
    /** @use HasFactory<CatalogProductFactory> */
    use HasFactory;

    /** Единицы измерения, из которых выбирают на вкладке «Остатки». */
    public const MEASURES = ['шт', 'кг', 'г', 'л', 'м', 'м²', 'м³', 'упак', 'компл'];

    /**
     * Дублирует умолчания колонок: свежесозданная запись должна знать свою
     * валюту и тип ещё до того, как её перечитают из базы.
     *
     * @var array<string, mixed>
     */
    protected $attributes = [
        'currency' => Currency::RUB->value,
        'type' => ProductType::Simple->value,
        'offers_by_properties' => true,
    ];

    /**
     * Laravel guesses factories from the application namespace, which never
     * matches a package model.
     */
    protected static function newFactory(): Factory
    {
        return CatalogProductFactory::new();
    }

    protected function casts(): array
    {
        return [
            'currency' => Currency::class,
            'type' => ProductType::class,
            'price' => 'decimal:2',
            'discount_percent' => 'decimal:2',
            'quantity' => 'decimal:3',
            'ratio' => 'decimal:3',
            'quantity_trace' => 'boolean',
            'can_buy_zero' => 'boolean',
            'offers_by_properties' => 'boolean',
        ];
    }

    /**
     * @return BelongsTo<IblockElement, $this>
     */
    public function element(): BelongsTo
    {
        return $this->belongsTo(IblockElement::class, 'element_id');
    }

    /**
     * Товар, к которому относится это торговое предложение.
     *
     * @return BelongsTo<IblockElement, $this>
     */
    public function parent(): BelongsTo
    {
        return $this->belongsTo(IblockElement::class, 'parent_element_id');
    }

    public function hasPrice(): bool
    {
        return $this->price !== null;
    }

    /**
     * Цена и наличие приходят из торговых предложений, а не отсюда.
     */
    public function usesOffers(): bool
    {
        return $this->type === ProductType::WithOffers;
    }

    /**
     * Предложения выводятся списком под товаром, а не переключателем по свойствам.
     *
     * Страница у товара тогда одна: адрес предложения открывает сам товар.
     */
    public function listsOffers(): bool
    {
        return $this->usesOffers() && ! $this->offers_by_properties;
    }

    /**
     * Цена со знаком её валюты: «1 800 ₽».
     */
    public function withCurrency(?float $value): string
    {
        if ($value === null) {
            return '';
        }

        return self::formatPrice($value).' '.($this->currency ?? Currency::RUB)->symbol();
    }

    public function hasDiscount(): bool
    {
        return $this->hasPrice() && (float) $this->discount_percent > 0;
    }

    /**
     * Цена со скидкой.
     *
     * Округление до копеек: цена показывается, а не складывается в бухгалтерию,
     * поэтому обычной арифметики здесь достаточно.
     */
    public function finalPrice(): ?float
    {
        if (! $this->hasPrice()) {
            return null;
        }

        return round((float) $this->price * (100 - (float) $this->discount_percent) / 100, 2);
    }

    /**
     * Цена для вывода: копейки только если они есть.
     */
    public static function formatPrice(?float $value): string
    {
        if ($value === null) {
            return '';
        }

        return number_format($value, fmod($value, 1.0) === 0.0 ? 0 : 2, ',', ' ');
    }

    public function discountAmount(): ?float
    {
        return $this->hasPrice() ? round((float) $this->price - $this->finalPrice(), 2) : null;
    }

    /**
     * Можно ли купить.
     *
     * Без количественного учёта остаток не проверяется вовсе. С ним — нужен
     * остаток больше нуля, либо разрешение покупать при его отсутствии.
     */
    public function isAvailable(): bool
    {
        if (! $this->quantity_trace) {
            return true;
        }

        return (float) $this->quantity > 0 || $this->can_buy_zero;
    }
}
