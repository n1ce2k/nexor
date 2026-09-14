<?php

namespace Nexor\Shop\Livewire;

use Illuminate\Contracts\View\View;
use Livewire\Component;
use Nexor\Shop\Livewire\Concerns\PlacesOrder;
use Nexor\Shop\Models\DeliveryMethod;
use Nexor\Shop\Models\PaymentMethod;
use Nexor\Shop\Support\Cart;
use Nexor\Shop\Support\Shop;

/**
 * Оформление заказа корзины Ultimate: контакты, доставка, оплата, итог.
 *
 * <livewire:nexor-shop::checkout />
 */
class Checkout extends Component
{
    use PlacesOrder;

    public ?int $deliveryId = null;

    public ?int $paymentId = null;

    public function mount(): void
    {
        abort_unless(Shop::checkoutEnabled(), 404);

        $this->deliveryId = DeliveryMethod::query()->active()->ordered()->value('id');
        $this->paymentId = PaymentMethod::query()->active()->ordered()->value('id');
    }

    public function placeOrder(): void
    {
        abort_unless(Shop::checkoutEnabled(), 404);

        $this->submitOrder($this->deliveryId, $this->paymentId);
    }

    public function render(): View
    {
        $summary = Cart::current()->summary();
        $deliveries = DeliveryMethod::query()->active()->ordered()->get();
        $delivery = $deliveries->firstWhere('id', $this->deliveryId);
        $deliveryPrice = $delivery?->priceFor($summary->total()) ?? 0.0;

        return view('nexor-shop::livewire.checkout', [
            'summary' => $summary,
            'fields' => $this->orderFields(),
            'deliveries' => $deliveries,
            'payments' => PaymentMethod::query()->active()->ordered()->get(),
            'deliveryPrice' => $deliveryPrice,
            'grandTotal' => round($summary->total() + $deliveryPrice, 2),
            'cartUrl' => Shop::cartUrl(),
        ]);
    }
}
