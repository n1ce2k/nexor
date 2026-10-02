<?php

namespace Nexor\Shop\Support\Delivery;

use Illuminate\Database\Eloquent\Collection;
use Nexor\Shop\Enums\DeliveryState;
use Nexor\Shop\Models\Order;
use Nexor\Shop\Models\OrderEvent;

/**
 * Слежение за посылками без менеджера.
 *
 * Раньше статус обновлялся, только когда заказ открывали в панели, и только
 * пока не было трек-номера: «В пути» и «Вручен» без кнопки «Обновить» так и
 * не появлялись. Теперь команда по расписанию обходит отправленные заказы, а
 * каждую смену статуса записывает событием — его панель и покажет попапом.
 */
class Tracking
{
    /**
     * Коды СДЭК, после которых статус больше не меняется.
     *
     * NOT_DELIVERED сюда не входит: за ним начинается возврат со своими
     * статусами, и его тоже надо видеть.
     */
    public const FINAL = ['DELIVERED', 'INVALID', 'REMOVED'];

    /** Сколько дней после оформления вообще следить за посылкой. */
    public const DAYS = 60;

    /**
     * Заказы, которые пора проверить: давно не проверявшиеся — первыми.
     *
     * @return Collection<int, Order>
     */
    public static function due(int $limit = 50): Collection
    {
        return Order::query()
            ->whereNotNull('delivery_request_id')
            ->where('delivery_state', '!=', DeliveryState::Failed->value)
            ->where(fn ($query) => $query
                ->whereNull('delivery_status_code')
                ->orWhereNotIn('delivery_status_code', self::FINAL))
            ->where('created_at', '>=', now()->subDays(self::DAYS))
            // Ни разу не проверенные — вперёд: NULL при сортировке по
            // возрастанию в MySQL и SQLite идёт первым.
            ->orderBy('delivery_synced_at')
            ->with('deliveryMethod')
            ->limit($limit)
            ->get();
    }

    /**
     * Спрашивает службу о заказе. Сменился статус — возвращает событие.
     */
    public static function refresh(Order $order): ?OrderEvent
    {
        $before = $order->delivery_status;

        Shipments::sync($order);

        $after = $order->delivery_status;

        if ($after === null || $after === $before) {
            return null;
        }

        return OrderEvent::query()->create([
            'order_id' => $order->id,
            'type' => OrderEvent::DELIVERY,
            'previous' => $before,
            'current' => $after,
        ]);
    }
}
