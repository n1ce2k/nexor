<?php

namespace Nexor\Shop\Http\Resources;

use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;
use Nexor\Cms\Support\Nexor;
use Nexor\Shop\Models\Order;
use Nexor\Shop\Models\OrderItem;

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
