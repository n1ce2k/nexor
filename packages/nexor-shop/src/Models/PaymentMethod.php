<?php

namespace Nexor\Shop\Models;

use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Factories\Factory;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Nexor\Shop\Database\Factories\PaymentMethodFactory;
use Nexor\Shop\Enums\PaymentProvider;

/**
 * Способ оплаты: подпись в заказе и, если задан провайдер, приём денег онлайн.
 */
#[Fillable(['name', 'description', 'provider', 'settings', 'is_active', 'sort'])]
class PaymentMethod extends Model
{
    /** @use HasFactory<PaymentMethodFactory> */
    use HasFactory;

    protected $table = 'shop_payment_methods';

    protected static function newFactory(): Factory
    {
        return PaymentMethodFactory::new();
    }

    protected function casts(): array
    {
        return [
            'provider' => PaymentProvider::class,
            // Секреты внутри зашифрованы отдельно — см. Support\Payments\Payments.
            'settings' => 'array',
            'is_active' => 'boolean',
            'sort' => 'integer',
        ];
    }

    /**
     * @param  Builder<$this>  $query
     */
    public function scopeActive(Builder $query): void
    {
        $query->where('is_active', true);
    }

    /**
     * @param  Builder<$this>  $query
     */
    public function scopeOrdered(Builder $query): void
    {
        $query->orderBy('sort')->orderBy('name');
    }
}
