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
use Nexor\Shop\Enums\DeliveryState;
use Nexor\Shop\Enums\OrderPaymentStatus;
use Nexor\Shop\Enums\OrderStatus;

#[Fillable([
    'status', 'edition', 'user_id', 'customer', 'currency',
    'subtotal', 'discount', 'delivery_price', 'total',
    'promocode_id', 'promocode_code',
    'delivery_method_id', 'delivery_name', 'delivery_data', 'payment_method_id', 'payment_name',
    'delivery_state', 'delivery_request_id', 'delivery_track', 'delivery_status', 'delivery_error', 'delivery_synced_at',
    'manager_comment', 'ip', 'payment_status', 'paid_at',
])]
class Order extends Model
{
    /** @use HasFactory<OrderFactory> */
    use HasFactory, SoftDeletes;

    protected $table = 'shop_orders';

    /**
     * Дублирует умолчание колонки: свежесозданный заказ должен знать, что
     * службе он ещё не передан, — до того, как его перечитают из базы.
     *
     * @var array<string, mixed>
     */
    protected $attributes = [
        'delivery_state' => DeliveryState::None->value,
    ];

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
            'delivery_data' => 'array',
            'delivery_state' => DeliveryState::class,
            'delivery_synced_at' => 'datetime',
            'subtotal' => 'decimal:2',
            'discount' => 'decimal:2',
            'delivery_price' => 'decimal:2',
            'total' => 'decimal:2',
            'payment_status' => OrderPaymentStatus::class,
            'paid_at' => 'datetime',
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
     * @return HasMany<Payment, $this>
     */
    public function payments(): HasMany
    {
        return $this->hasMany(Payment::class, 'order_id')->latest('id');
    }

    /**
     * @return BelongsTo<PaymentMethod, $this>
     */
    public function paymentMethod(): BelongsTo
    {
        return $this->belongsTo(PaymentMethod::class, 'payment_method_id');
    }

    /**
     * Способ доставки. Его могли удалить — в заказе остаётся название.
     *
     * @return BelongsTo<DeliveryMethod, $this>
     */
    public function deliveryMethod(): BelongsTo
    {
        return $this->belongsTo(DeliveryMethod::class, 'delivery_method_id');
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
     * Выбор у службы доставки строками — как его показать менеджеру.
     *
     * Читаем снимок из заказа, а не настройки способа: способ могли уже
     * переименовать или удалить, а заказ остаётся таким, каким его оформили.
     *
     * @return array<int, array{label: string, value: string}>
     */
    public function deliveryDetails(): array
    {
        $data = $this->delivery_data ?? [];

        if ($data === []) {
            return [];
        }

        $rows = [
            ['label' => 'Город', 'value' => (string) ($data['city'] ?? '')],
            ['label' => 'Тариф', 'value' => trim(($data['tariff_name'] ?? '').' '.(isset($data['tariff']) ? '· код '.$data['tariff'] : ''))],
            ['label' => 'Адрес', 'value' => (string) ($data['address'] ?? '')],
            ['label' => 'Пункт выдачи', 'value' => trim((string) ($data['point'] ?? '').' '.(string) ($data['point_address'] ?? ''))],
        ];

        if (isset($data['period_min'])) {
            $period = $data['period_min'].(isset($data['period_max']) && $data['period_max'] !== $data['period_min']
                ? '–'.$data['period_max']
                : '');

            $rows[] = ['label' => 'Срок', 'value' => $period.' дн.'];
        }

        if ($data['manual'] ?? false) {
            $rows[] = ['label' => 'Стоимость', 'value' => 'служба не рассчитала: '.($data['error'] ?? 'считает менеджер')];
        }

        if ($data['free'] ?? false) {
            $rows[] = ['label' => 'Стоимость', 'value' => 'бесплатно по порогу заказа'];
        }

        return array_values(array_filter($rows, fn (array $row) => $row['value'] !== ''));
    }

    /**
     * Ссылка на отслеживание посылки, если трек-номер уже есть.
     */
    public function deliveryTrackUrl(): ?string
    {
        return $this->delivery_track
            ? 'https://www.cdek.ru/ru/tracking?order_id='.urlencode($this->delivery_track)
            : null;
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
