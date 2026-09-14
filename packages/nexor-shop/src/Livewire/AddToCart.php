<?php

namespace Nexor\Shop\Livewire;

use Illuminate\Contracts\View\View;
use Livewire\Attributes\Locked;
use Livewire\Attributes\On;
use Livewire\Component;
use Nexor\Cms\Models\CatalogProduct;
use Nexor\Shop\Support\Cart;
use Nexor\Shop\Support\CartException;
use Nexor\Shop\Support\CartPricing;
use Nexor\Shop\Support\Shop;

/**
 * Кнопка «В корзину» с выбором количества.
 *
 * <livewire:nexor-shop::add-to-cart :element-id="$element->id" />
 * <livewire:nexor-shop::add-to-cart :element-id="$element->id" mode="counter" />
 *
 * Два режима:
 *  - `button` (по умолчанию): счётчик «− 1 +» рядом с кнопкой, количество
 *    выбирается в браузере и уходит в корзину одним нажатием — `add()`;
 *  - `counter`: до добавления — кнопка, после — счётчик, который правит
 *    количество прямо в корзине, а на нуле убирает товар — `change()`.
 *
 * Шаг и предел счётчика считает сервер (`CartPricing::limits()`), так что
 * браузер и корзина не расходятся: у Ultimate шаг — коэффициент, предел — остаток.
 */
class AddToCart extends Component
{
    public const MODE_BUTTON = 'button';

    public const MODE_COUNTER = 'counter';

    #[Locked]
    public int $elementId;

    #[Locked]
    public string $mode = self::MODE_BUTTON;

    public string $label = 'В корзину';

    public ?string $error = null;

    /** Шаг счётчика. */
    #[Locked]
    public float $step = 1.0;

    /** Сколько ещё можно добавить сверх лежащего в корзине; null — без предела. */
    #[Locked]
    public ?float $available = null;

    /** Сколько уже в корзине. */
    #[Locked]
    public float $inCart = 0.0;

    public function mount(): void
    {
        if (! in_array($this->mode, [self::MODE_BUTTON, self::MODE_COUNTER], true)) {
            $this->mode = self::MODE_BUTTON;
        }
    }

    /**
     * Кладёт товар. Количество корзина всё равно приведёт к шагу и остатку.
     */
    public function add(float $quantity = 1): void
    {
        if (! is_finite($quantity) || $quantity <= 0) {
            $quantity = 1;
        }

        $cart = Cart::current();

        try {
            $line = $cart->add($this->elementId, $quantity);
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

    /**
     * Режим `counter`: плюс или минус шаг к тому, что лежит в корзине.
     *
     * В отличие от счётчика в самой корзине, минус на последнем шаге убирает
     * товар: кнопка «В корзину» вернётся на место счётчика.
     */
    public function change(int $direction): void
    {
        $cart = Cart::current();
        $line = $cart->lines()->firstWhere('id', $this->elementId);

        if (! $line) {
            if ($direction > 0) {
                $this->add();
            }

            return;
        }

        if ($direction > 0 && ! $line->canIncrease()) {
            return;
        }

        $next = round($line->quantity + ($direction <=> 0) * $line->step, 3);

        $next < $line->step - 0.0001
            ? $cart->remove($this->elementId)
            : $cart->setQuantity($this->elementId, $next);

        $cart->lines();
        $cart->save();
        $this->error = null;

        $this->dispatch('cart-updated');
        $this->dispatch('cart-changed');
    }

    #[On('cart-updated')]
    public function refreshCart(): void {}

    public function render(): View
    {
        $this->inCart = Cart::current()->quantity($this->elementId);

        ['step' => $this->step, 'max' => $max] = CartPricing::limits(
            CatalogProduct::query()->where('element_id', $this->elementId)->first(),
        );

        $this->available = $max === null ? null : max(round($max - $this->inCart, 3), 0.0);

        return view('nexor-shop::livewire.add-to-cart', [
            'cartUrl' => Shop::cartUrl(),
            'feedback' => Shop::addedFeedback()->value,
        ]);
    }
}
