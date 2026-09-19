<?php

namespace Nexor\Shop\Models;

use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Nexor\Cms\Enums\Currency;
use Nexor\Shop\Enums\PaymentProvider;
use Nexor\Shop\Enums\PaymentStatus;

/**
 * Платёж по заказу у внешнего провайдера.
 */
#[Fillable([
    'order_id', 'provider', 'external_id', 'status', 'amount', 'refunded', 'currency',
    'confirmation_url', 'idempotence_key', 'paid_at', 'canceled_at', 'cancellation_reason', 'payload',
])]
class Payment extends Model
{
    protected $table = 'shop_payments';

    protected function casts(): array
    {
        return [
            'provider' => PaymentProvider::class,
            'status' => PaymentStatus::class,
            'currency' => Currency::class,
            'amount' => 'decimal:2',
            'refunded' => 'decimal:2',
            'paid_at' => 'datetime',
            'canceled_at' => 'datetime',
            'payload' => 'array',
        ];
    }

    /**
     * @return BelongsTo<Order, $this>
     */
    public function order(): BelongsTo
    {
        return $this->belongsTo(Order::class, 'order_id');
    }

    /**
     * @return HasMany<PaymentRefund, $this>
     */
    public function refunds(): HasMany
    {
        return $this->hasMany(PaymentRefund::class, 'payment_id')->latest('id');
    }

    /**
     * Сколько ещё можно вернуть.
     */
    public function refundable(): float
    {
        return $this->status === PaymentStatus::Succeeded
            ? round((float) $this->amount - (float) $this->refunded, 2)
            : 0.0;
    }

    /**
     * Ссылка на платёж в личном кабинете провайдера.
     */
    public function externalUrl(): ?string
    {
        return $this->provider === PaymentProvider::YooKassa && $this->external_id
            ? 'https://yookassa.ru/my/payments/'.$this->external_id
            : null;
    }
}
