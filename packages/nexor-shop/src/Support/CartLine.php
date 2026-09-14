<?php

namespace Nexor\Shop\Support;

use Nexor\Cms\Enums\Currency;
use Nexor\Cms\Models\IblockElement;

/**
 * Одна строка корзины, уже посчитанная: цена в валюте магазина, шаг и предел
 * количества, и что мешает купить, если что-то мешает.
 */
class CartLine
{
    /** Доля скидки промокода, приходящаяся на строку. */
    public float $discount = 0.0;

    public function __construct(
        public readonly int $id,
        public readonly IblockElement $element,
        public readonly ?IblockElement $product,
        public readonly string $name,
        public readonly ?string $url,
        public readonly ?string $image,
        public readonly float $quantity,
        public readonly float $step,
        public readonly ?float $max,
        public readonly string $measure,
        public readonly float $basePrice,
        public readonly float $unitPrice,
        public readonly ?float $originalPrice,
        public readonly ?Currency $originalCurrency,
        public readonly bool $converted,
        public readonly ?string $problem = null,
    ) {}

    public function sum(): float
    {
        return $this->problem ? 0.0 : round($this->unitPrice * $this->quantity, 2);
    }

    public function hasProblem(): bool
    {
        return $this->problem !== null;
    }

    public function hasDiscount(): bool
    {
        return $this->basePrice > $this->unitPrice;
    }

    public function canIncrease(): bool
    {
        return $this->max === null || $this->quantity + $this->step <= $this->max + 0.0001;
    }

    /**
     * Товар, к которому относится строка: сам элемент или товар предложения.
     */
    public function catalogItem(): IblockElement
    {
        return $this->product ?? $this->element;
    }
}
