<?php

namespace Nexor\Shop\Livewire\Concerns;

use Livewire\Attributes\On;
use Nexor\Shop\Support\Cart;
use Nexor\Shop\Support\CartSummary;

/**
 * Общее у корзины в панели и на странице: плюс, минус, удалить, очистить.
 */
trait ManagesCart
{
    /**
     * Корзину поменял другой компонент — перерисоваться.
     */
    #[On('cart-updated')]
    public function refreshCart(): void {}

    public function increase(int $elementId): void
    {
        $this->changeBy($elementId, 1);
    }

    public function decrease(int $elementId): void
    {
        $this->changeBy($elementId, -1);
    }

    public function remove(int $elementId): void
    {
        $cart = Cart::current();
        $cart->remove($elementId);

        $this->commit($cart);
    }

    public function clear(): void
    {
        $cart = Cart::current();
        $cart->clear();

        $this->commit($cart);
    }

    protected function summary(): CartSummary
    {
        return Cart::current()->summary();
    }

    /**
     * На шаг строки: у товара, продающегося по 0.5 кг, «плюс» добавляет 0.5.
     */
    protected function changeBy(int $elementId, int $direction): void
    {
        $cart = Cart::current();
        $line = $cart->lines()->firstWhere('id', $elementId);

        if (! $line) {
            return;
        }

        $next = $line->quantity + $direction * $line->step;

        // «Минус» на последней единице не удаляет товар случайно — для этого есть крестик.
        if ($next < $line->step - 0.0001) {
            return;
        }

        if ($direction > 0 && ! $line->canIncrease()) {
            return;
        }

        $cart->setQuantity($elementId, $next);

        $this->commit($cart);
    }

    protected function commit(Cart $cart): void
    {
        $cart->lines();
        $cart->save();

        $this->announceCartChange();
    }

    /**
     * `cart-updated` — перерисоваться компонентам этой вкладки,
     * `cart-changed` — сказать другим вкладкам, что корзину поменяли здесь.
     */
    protected function announceCartChange(): void
    {
        $this->dispatch('cart-updated');
        $this->dispatch('cart-changed');
    }
}
