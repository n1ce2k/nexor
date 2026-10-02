<?php

namespace Nexor\Shop\Models;

use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

/**
 * Изменение заказа, случившееся без менеджера: например, СДЭК сменил статус
 * посылки, пока в панели никого не было.
 */
#[Fillable(['order_id', 'type', 'previous', 'current'])]
class OrderEvent extends Model
{
    /** Статус доставки от службы. */
    public const DELIVERY = 'delivery';

    public const UPDATED_AT = null;

    protected $table = 'shop_order_events';

    /**
     * @return BelongsTo<Order, $this>
     */
    public function order(): BelongsTo
    {
        return $this->belongsTo(Order::class)->withTrashed();
    }
}
