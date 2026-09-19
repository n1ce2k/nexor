<?php

namespace Nexor\Shop\Models;

use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

/**
 * Возврат денег по платежу — целиком или частью.
 */
#[Fillable(['payment_id', 'external_id', 'status', 'amount', 'reason', 'user_id', 'payload'])]
class PaymentRefund extends Model
{
    protected $table = 'shop_payment_refunds';

    protected function casts(): array
    {
        return [
            'amount' => 'decimal:2',
            'payload' => 'array',
        ];
    }

    /**
     * @return BelongsTo<Payment, $this>
     */
    public function payment(): BelongsTo
    {
        return $this->belongsTo(Payment::class, 'payment_id');
    }

    public function isSucceeded(): bool
    {
        return $this->status === 'succeeded';
    }
}
