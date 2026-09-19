<?php

namespace Nexor\Shop\Livewire\Concerns;

use Illuminate\Support\Collection;
use Illuminate\Support\Facades\URL;
use Nexor\Shop\Models\OrderField;
use Nexor\Shop\Support\Cart;
use Nexor\Shop\Support\OrderPlacer;
use Nexor\Shop\Support\Payments\Payments;

/**
 * Форма заказа: поля из админки и отправка.
 */
trait PlacesOrder
{
    /** @var array<string, string|null> Значения полей по коду */
    public array $customer = [];

    /** Номер оформленного заказа — показываем «спасибо» вместо корзины. */
    public ?string $placedNumber = null;

    /** Ссылка на оплату, если способ принимает деньги онлайн. */
    public ?string $paymentUrl = null;

    public function mountPlacesOrder(): void
    {
        foreach ($this->orderFields() as $field) {
            $this->customer[$field->code] ??= '';
        }
    }

    /**
     * @return Collection<int, OrderField>
     */
    protected function orderFields(): Collection
    {
        return OrderField::query()->active()->ordered()->get();
    }

    protected function submitOrder(?int $deliveryId = null, ?int $paymentId = null): void
    {
        $this->resetErrorBag();

        $order = app(OrderPlacer::class)->place(Cart::current(), $this->customer, $deliveryId, $paymentId);

        $this->placedNumber = (string) $order->number;
        $this->paymentUrl = Payments::ready($order->paymentMethod)
            ? URL::signedRoute('shop.payment.pay', ['order' => $order->id])
            : null;
        $this->customer = array_map(fn () => '', $this->customer);

        // Заказ очистил корзину — и в других вкладках тоже.
        $this->dispatch('cart-updated');
        $this->dispatch('cart-changed');

        // Способ может уводить на оплату сам, не показывая кнопку.
        if ($this->paymentUrl !== null && Payments::autoRedirect($order->paymentMethod)) {
            $this->redirect($this->paymentUrl);
        }
    }
}
