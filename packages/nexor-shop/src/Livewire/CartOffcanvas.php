<?php

namespace Nexor\Shop\Livewire;

use Illuminate\Contracts\View\View;
use Livewire\Component;
use Nexor\Shop\Enums\CartDisplay;
use Nexor\Shop\Livewire\Concerns\ManagesCart;
use Nexor\Shop\Livewire\Concerns\PlacesOrder;
use Nexor\Shop\Support\Shop;

/**
 * Выезжающая корзина. Ставится в макет один раз, открывается событием `cart-open`.
 *
 * <livewire:nexor-shop::cart-offcanvas />
 *
 * У Basic форма заказа прямо здесь. У Ultimate — переход в корзину, где
 * промокоды, и к оформлению.
 */
class CartOffcanvas extends Component
{
    use ManagesCart, PlacesOrder;

    public function placeOrder(): void
    {
        abort_if(Shop::isUltimate(), 404);

        $this->submitOrder();
    }

    public function render(): View
    {
        return view('nexor-shop::livewire.cart-offcanvas', [
            'summary' => $this->summary(),
            'fields' => $this->orderFields(),
            'ultimate' => Shop::isUltimate(),
            'checkout' => Shop::checkoutEnabled(),
            'enabled' => Shop::display() === CartDisplay::Offcanvas,
            'cartUrl' => Shop::cartUrl(),
            'checkoutUrl' => Shop::checkoutUrl(),
        ]);
    }
}
