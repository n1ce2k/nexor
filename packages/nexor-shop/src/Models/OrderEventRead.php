<?php

namespace Nexor\Shop\Models;

use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Model;

/**
 * До какого события человек уже видел уведомления в панели.
 */
#[Fillable(['user_id', 'last_event_id'])]
class OrderEventRead extends Model
{
    public const CREATED_AT = null;

    public $incrementing = false;

    protected $table = 'shop_order_event_reads';

    protected $primaryKey = 'user_id';

    protected function casts(): array
    {
        return ['last_event_id' => 'integer'];
    }
}
