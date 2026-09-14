<?php

namespace Nexor\Shop\Livewire;

use Illuminate\Contracts\View\View;
use Livewire\Component;
use Nexor\Shop\Livewire\Concerns\ManagesCart;
use Nexor\Shop\Livewire\Concerns\PlacesOrder;
use Nexor\Shop\Support\Cart;
use Nexor\Shop\Support\Shop;

/**
 * Корзина отдельной страницей.
 *
 * <livewire:nexor-shop::cart-page />
 *
 * Basic: товары и форма заказа. Ultimate: товары, промокод и «Перейти к оформлению».
 */
class CartPage extends Component
{
    use ManagesCart, PlacesOrder;

    public string $promocodeInput = '';

    public ?string $promocodeMessage = null;

    public function placeOrder(): void
    {
        abort_if(Shop::isUltimate(), 404);

        $this->submitOrder();
    }

    public function applyPromocode(): void
    {
        abort_unless(Shop::promocodesEnabled(), 404);

        $this->resetErrorBag('promocode');

        $code = trim($this->promocodeInput);

        if ($code === '') {
            $this->addError('promocode', 'Введите промокод.');

            return;
        }

        $cart = Cart::current();
        $cart->setPromocode($code);

        $summary = $cart->summary();

        // Неподходящий код в cookie не оставляем: иначе ошибка висела бы всегда.
        if ($summary->promocodeError) {
            $cart->setPromocode(null);
            $cart->save();

            $this->addError('promocode', $summary->promocodeError);

            return;
        }

        $cart->save();

        $this->promocodeInput = '';
        $this->promocodeMessage = 'Промокод применён: скидка '.Shop::format($summary->discount).'.';

        $this->announceCartChange();
    }

    public function removePromocode(): void
    {
        $cart = Cart::current();
        $cart->setPromocode(null);
        $cart->save();

        $this->promocodeMessage = null;

        $this->announceCartChange();
    }

    public function render(): View
    {
        return view('nexor-shop::livewire.cart-page', [
            'summary' => $this->summary(),
            'fields' => $this->orderFields(),
            'ultimate' => Shop::isUltimate(),
            'promocodes' => Shop::promocodesEnabled(),
            'checkout' => Shop::checkoutEnabled(),
            'checkoutUrl' => Shop::checkoutUrl(),
        ]);
    }
}
