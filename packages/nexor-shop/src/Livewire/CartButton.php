<?php

namespace Nexor\Shop\Livewire;

use Illuminate\Contracts\View\View;
use Livewire\Attributes\On;
use Livewire\Component;
use Nexor\Shop\Enums\CartDisplay;
use Nexor\Shop\Support\Cart;
use Nexor\Shop\Support\Shop;

/**
 * Иконка корзины в шапке со счётчиком.
 *
 * <livewire:nexor-shop::cart-button />
 */
class CartButton extends Component
{
    #[On('cart-updated')]
    public function refreshCart(): void {}

    public function render(): View
    {
        return view('nexor-shop::livewire.cart-button', [
            'count' => count(Cart::current()->items()),
            'offcanvas' => Shop::display() === CartDisplay::Offcanvas,
            'cartUrl' => Shop::cartUrl(),
        ]);
    }
}
