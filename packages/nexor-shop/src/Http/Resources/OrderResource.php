<?php

namespace Nexor\Shop\Http\Resources;

use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;
use Nexor\Cms\Support\Nexor;
use Nexor\Shop\Enums\OrderPaymentStatus;
use Nexor\Shop\Models\Order;
use Nexor\Shop\Models\OrderItem;
use Nexor\Shop\Models\Payment;
use Nexor\Shop\Models\PaymentRefund;

/**
 * @mixin Order
 */
class OrderResource extends JsonResource
{
    /**
     * @return array<string, mixed>
     */
    public function toArray(Request $request): array
    {
        // На Lite доставки и оплаты нет — и в заказе их не показываем.
        $checkout = Nexor::feature('shop.checkout');
        // Заказы, оформленные до появления онлайн-оплаты, статуса не имеют.
        $paymentStatus = $this->payment_status ?? OrderPaymentStatus::Unpaid;

        return [
            'id' => $this->id,
            'number' => $this->number,
            'status' => $this->status->value,
            'status_label' => $this->status->label(),
            'status_color' => $this->status->color(),
            'edition' => $this->edition->value,
            'customer' => $this->customer,
            'customer_name' => $this->resource->customerName(),
            'currency' => $this->currency->value,
            'currency_symbol' => $this->currency->symbol(),
            'subtotal' => $this->subtotal,
            'discount' => $this->discount,
            'total' => $this->total,
            'promocode' => $this->promocode_code,
            'delivery' => $this->when($checkout, fn () => $this->delivery_name ? [
                'name' => $this->delivery_name,
                'price' => $this->delivery_price,
            ] : null),
            'payment' => $this->when($checkout, fn () => $this->payment_name),
            'payment_status' => $paymentStatus->value,
            'payment_status_label' => $paymentStatus->label(),
            'payment_status_color' => $paymentStatus->color(),
            'paid_at' => $this->paid_at?->toIso8601String(),
            'payments' => $this->whenLoaded('payments', fn () => $this->payments->map(fn (Payment $payment) => [
                'id' => $payment->id,
                'provider' => $payment->provider->value,
                'provider_label' => $payment->provider->label(),
                'external_id' => $payment->external_id,
                'external_url' => $payment->externalUrl(),
                'status' => $payment->status->value,
                'status_label' => $payment->status->label(),
                'status_color' => $payment->status->color(),
                'amount' => $payment->amount,
                'refunded' => $payment->refunded,
                'refundable' => $payment->refundable(),
                'paid_at' => $payment->paid_at?->toIso8601String(),
                'canceled_at' => $payment->canceled_at?->toIso8601String(),
                'cancellation_reason' => $payment->cancellation_reason,
                'created_at' => $payment->created_at?->toIso8601String(),
                'refunds' => $payment->relationLoaded('refunds')
                    ? $payment->refunds->map(fn (PaymentRefund $refund) => [
                        'id' => $refund->id,
                        'amount' => $refund->amount,
                        'status' => $refund->status,
                        'is_succeeded' => $refund->isSucceeded(),
                        'reason' => $refund->reason,
                        'created_at' => $refund->created_at?->toIso8601String(),
                    ])
                    : [],
            ])),
            'manager_comment' => $this->manager_comment,
            'items_count' => $this->whenCounted('items'),
            'items' => $this->whenLoaded('items', fn () => $this->items->map(fn (OrderItem $item) => [
                'id' => $item->id,
                'element_id' => $item->element_id,
                'name' => $item->name,
                'url' => $item->url,
                'quantity' => $item->quantity,
                'measure' => $item->measure,
                'base_price' => $item->base_price,
                'price' => $item->price,
                'original_price' => $item->original_price,
                'original_currency' => $item->original_currency,
                'is_converted' => $item->is_converted,
                'discount' => $item->discount,
                'sum' => $item->sum,
            ])),
            'ip' => $this->ip,
            'created_at' => $this->created_at?->toIso8601String(),
            'updated_at' => $this->updated_at?->toIso8601String(),
        ];
    }
}
