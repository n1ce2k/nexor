<?php

namespace Nexor\Shop\Models;

use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Nexor\Cms\Models\IblockElement;

#[Fillable([
    'order_id', 'element_id', 'name', 'url', 'quantity', 'measure',
    'base_price', 'price', 'original_price', 'original_currency', 'is_converted',
    'discount', 'sum',
])]
class OrderItem extends Model
{
    protected $table = 'shop_order_items';

    protected function casts(): array
    {
        return [
            'quantity' => 'decimal:3',
            'base_price' => 'decimal:2',
            'price' => 'decimal:2',
            'original_price' => 'decimal:2',
            'is_converted' => 'boolean',
            'discount' => 'decimal:2',
            'sum' => 'decimal:2',
        ];
    }

    /**
     * @return BelongsTo<Order, $this>
     */
    public function order(): BelongsTo
    {
        return $this->belongsTo(Order::class);
    }

    /**
     * @return BelongsTo<IblockElement, $this>
     */
    public function element(): BelongsTo
    {
        return $this->belongsTo(IblockElement::class, 'element_id');
    }
}
