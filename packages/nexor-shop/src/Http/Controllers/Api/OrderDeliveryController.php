<?php

namespace Nexor\Shop\Http\Controllers\Api;

use Illuminate\Http\JsonResponse;
use Nexor\Cms\Http\Controllers\Api\ApiController;
use Nexor\Cms\Support\ActivityLogger;
use Nexor\Shop\Http\Resources\OrderResource;
use Nexor\Shop\Models\Order;
use Nexor\Shop\Support\Delivery\DeliveryException;
use Nexor\Shop\Support\Delivery\Shipments;

/**
 * Передача заказа службе доставки и трек-номер.
 */
class OrderDeliveryController extends ApiController
{
    /**
     * Отправляет заказ в службу и сразу пробует забрать номер.
     */
    public function register(Order $order): JsonResponse
    {
        try {
            Shipments::register($order);
        } catch (DeliveryException $exception) {
            return $this->refuse($exception->getMessage());
        }

        ActivityLogger::updated($order, 'Заказ №'.$order->number.' передан в службу доставки');

        return $this->payload($order, $order->delivery_track
            ? 'Заказ передан, трек-номер: '.$order->delivery_track.'.'
            : 'Заказ передан — служба ещё не присвоила номер, обновите через минуту.');
    }

    /**
     * Перечитывает состояние заявки: номер появляется не сразу.
     */
    public function sync(Order $order): JsonResponse
    {
        Shipments::sync($order);

        return $this->payload($order, $order->delivery_track
            ? 'Трек-номер: '.$order->delivery_track.'.'
            : ($order->delivery_error ?? 'Служба пока не присвоила номер.'));
    }

    protected function payload(Order $order, string $message): JsonResponse
    {
        return OrderResource::make($order->fresh()->load(['items', 'payments.refunds']))
            ->additional(['message' => $message])
            ->response();
    }
}
