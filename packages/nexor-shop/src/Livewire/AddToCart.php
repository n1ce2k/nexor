<?php

namespace Nexor\Shop\Livewire;

use Illuminate\Contracts\View\View;
use Livewire\Attributes\Locked;
use Livewire\Attributes\On;
use Livewire\Component;
use Nexor\Shop\Support\Cart;
use Nexor\Shop\Support\CartException;
use Nexor\Shop\Support\Shop;

/**
 * Кнопка «В корзину».
 *
 * <livewire:nexor-shop::add-to-cart :element-id="$element->id" />
 */
class AddToCart extends Component
{
    #[Locked]
    public int $elementId;

    public string $label = 'В корзину';

    public ?string $error = null;

    public function add(): void
    {
        $cart = Cart::current();

        try {
            $line = $cart->add($this->elementId);
        } catch (CartException $exception) {
            $this->error = $exception->getMessage();

            return;
        }

        $cart->save();
        $this->error = null;

        $this->dispatch('cart-updated');
        $this->dispatch('cart-changed');

        if (Shop::opensOnAdd()) {
            $this->dispatch('cart-open');

            return;
        }

        // Корзина не выезжает — кнопка или всплывашка скажут «добавлено» сами.
        $this->dispatch('cart-added', elementId: $this->elementId, name: $line->name);
    }

    #[On('cart-updated')]
    public function refreshCart(): void {}

    public function render(): View
    {
        return view('nexor-shop::livewire.add-to-cart', [
            'inCart' => Cart::current()->quantity($this->elementId),
            'cartUrl' => Shop::cartUrl(),
            'feedback' => Shop::addedFeedback()->value,
        ]);
    }
}
