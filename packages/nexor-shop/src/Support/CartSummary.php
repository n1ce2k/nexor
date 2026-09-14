<?php

namespace Nexor\Shop\Support;

use Illuminate\Support\Collection;
use Nexor\Cms\Enums\Currency;
use Nexor\Shop\Models\Promocode;

/**
 * Итог корзины: строки, суммы, промокод.
 */
class CartSummary
{
    /**
     * @param  Collection<int, CartLine>  $lines
     */
    public function __construct(
        public readonly Collection $lines,
        public readonly Currency $currency,
        public readonly float $subtotal,
        public readonly float $discount,
        public readonly ?Promocode $promocode,
        public readonly ?string $promocodeCode,
        public readonly ?string $promocodeError,
    ) {}

    public function total(): float
    {
        return round(max($this->subtotal - $this->discount, 0), 2);
    }

    public function count(): int
    {
        return $this->lines->count();
    }

    public function isEmpty(): bool
    {
        return $this->lines->isEmpty();
    }

    public function hasProblems(): bool
    {
        return $this->lines->contains(fn (CartLine $line) => $line->hasProblem());
    }

    /**
     * Можно ли оформлять: корзина не пуста и в ней нет того, что купить нельзя.
     */
    public function canOrder(): bool
    {
        return ! $this->isEmpty() && ! $this->hasProblems();
    }

    public function hasConverted(): bool
    {
        return $this->lines->contains(fn (CartLine $line) => $line->converted);
    }
}
