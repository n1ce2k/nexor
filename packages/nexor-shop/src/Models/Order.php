<?php

namespace Nexor\Shop\Models;

use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Factories\Factory;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\SoftDeletes;
use Nexor\Cms\Enums\Currency;
use Nexor\Shop\Database\Factories\OrderFactory;
use Nexor\Shop\Enums\CartEdition;
use Nexor\Shop\Enums\OrderStatus;

#[Fillable([
    'status', 'edition', 'user_id', 'customer', 'currency',
    'subtotal', 'discount', 'delivery_price', 'total',
    'promocode_id', 'promocode_code',
    'delivery_method_id', 'delivery_name', 'payment_method_id', 'payment_name',
    'manager_comment', 'ip',
])]
class Order extends Model
{
    /** @use HasFactory<OrderFactory> */
    use HasFactory, SoftDeletes;

    protected $table = 'shop_orders';

    protected static function newFactory(): Factory
    {
        return OrderFactory::new();
    }

    protected function casts(): array
    {
        return [
            'status' => OrderStatus::class,
            'edition' => CartEdition::class,
            'currency' => Currency::class,
            'customer' => 'array',
            'subtotal' => 'decimal:2',
            'discount' => 'decimal:2',
            'delivery_price' => 'decimal:2',
            'total' => 'decimal:2',
        ];
    }

    protected static function booted(): void
    {
        // Номер по id: короткий, растёт по порядку и не выдаёт лишнего.
        static::created(function (self $order): void {
            $order->forceFill(['number' => str_pad((string) $order->id, 6, '0', STR_PAD_LEFT)])->saveQuietly();
        });
    }

    /**
     * @return HasMany<OrderItem, $this>
     */
    public function items(): HasMany
    {
        return $this->hasMany(OrderItem::class)->orderBy('id');
    }

    /**
     * @return BelongsTo<Promocode, $this>
     */
    public function promocode(): BelongsTo
    {
        return $this->belongsTo(Promocode::class);
    }

    /**
     * Значение поля формы по коду: `customerValue('EMAIL')`.
     */
    public function customerValue(string $code): ?string
    {
        foreach ($this->customer ?? [] as $field) {
            if (($field['code'] ?? null) === $code) {
                return filled($field['value'] ?? null) ? (string) $field['value'] : null;
            }
        }

        return null;
    }

    /**
     * Как зовут покупателя — для списка заказов.
     */
    public function customerName(): string
    {
        return $this->customerValue('NAME')
            ?? collect($this->customer)->pluck('value')->filter()->first()
            ?? 'Без имени';
    }
}
