<?php

namespace Nexor\Shop\Support\Delivery;

use Nexor\Cms\Models\IblockElement;
use Nexor\Shop\Enums\DeliveryState;
use Nexor\Shop\Models\DeliveryMethod;
use Nexor\Shop\Models\Order;
use Nexor\Shop\Models\OrderItem;

/**
 * Передача заказа в службу доставки и трек-номер.
 *
 * СДЭК отвечает на регистрацию только номером заявки, а трек-номер присваивает
 * через несколько секунд. Поэтому состояние заказа живёт отдельно: `pending` —
 * заявка ушла, номера ещё нет; `registered` — номер получен; `failed` — служба
 * отказала, причина лежит в заказе и видна менеджеру.
 */
class Shipments
{
    /**
     * Можно ли передавать этот заказ службе.
     */
    public static function possible(Order $order): bool
    {
        $method = $order->deliveryMethod;

        return $method !== null
            && Deliveries::ready($method)
            && ($order->delivery_data['tariff'] ?? null) !== null
            && $order->delivery_state !== DeliveryState::Registered;
    }

    /**
     * Регистрирует заказ и сразу пробует забрать номер.
     *
     * @throws DeliveryException
     */
    public static function register(Order $order): Order
    {
        $method = $order->deliveryMethod;

        if ($method === null || ! Deliveries::ready($method)) {
            throw new DeliveryException('У заказа нет настроенного способа доставки СДЭК.');
        }

        if ($order->delivery_state === DeliveryState::Registered) {
            throw new DeliveryException('Заказ уже передан, трек-номер: '.$order->delivery_track.'.');
        }

        try {
            $uuid = Deliveries::client($method)->createOrder(self::payload($order, $method));
        } catch (DeliveryException $exception) {
            $order->forceFill([
                'delivery_state' => DeliveryState::Failed,
                'delivery_error' => $exception->getMessage(),
                'delivery_synced_at' => now(),
            ])->save();

            throw $exception;
        }

        $order->forceFill([
            'delivery_state' => DeliveryState::Pending,
            'delivery_request_id' => $uuid,
            'delivery_error' => null,
            'delivery_synced_at' => now(),
        ])->save();

        return self::sync($order);
    }

    /**
     * Спрашивает у службы трек-номер и статус по уже отправленной заявке.
     */
    public static function sync(Order $order): Order
    {
        $method = $order->deliveryMethod;

        if ($order->delivery_request_id === null || $method === null || ! Deliveries::ready($method)) {
            return $order;
        }

        try {
            $shipment = Deliveries::client($method)->shipment($order->delivery_request_id);
        } catch (DeliveryException $exception) {
            $order->forceFill([
                'delivery_error' => $exception->getMessage(),
                'delivery_synced_at' => now(),
            ])->save();

            return $order;
        }

        $order->forceFill([
            // Номера может ещё не быть — тогда заявка так и висит в ожидании.
            'delivery_state' => match (true) {
                $shipment['track'] !== null => DeliveryState::Registered,
                $shipment['error'] !== null || $shipment['state'] === 'INVALID' => DeliveryState::Failed,
                default => DeliveryState::Pending,
            },
            'delivery_track' => $shipment['track'] ?? $order->delivery_track,
            'delivery_status' => $shipment['status'] ?? self::stateLabel($shipment['state']) ?? $order->delivery_status,
            'delivery_status_code' => $shipment['code'] ?? $order->delivery_status_code,
            'delivery_error' => $shipment['error'],
            'delivery_synced_at' => now(),
        ])->save();

        return $order;
    }

    /**
     * Стоит ли сходить за номером самому: заявка ушла, а номера всё нет.
     *
     * Так менеджеру не приходится жать «Обновить» — открыл заказ, и номер
     * подтянулся, если СДЭК его уже присвоил.
     */
    public static function stale(Order $order, int $seconds = 30): bool
    {
        return $order->delivery_state === DeliveryState::Pending
            && $order->delivery_request_id !== null
            && ($order->delivery_synced_at === null || $order->delivery_synced_at->addSeconds($seconds)->isPast());
    }

    /**
     * Состояние обработки заявки по-русски — пока нет статуса посылки.
     */
    protected static function stateLabel(?string $state): ?string
    {
        return match ($state) {
            'ACCEPTED' => 'Заявка принята, СДЭК присваивает номер',
            'WAITING' => 'Заявка в очереди СДЭК',
            'SUCCESSFUL' => 'Заявка обработана',
            'INVALID' => 'Заявка отклонена',
            default => null,
        };
    }

    /**
     * Заявка для СДЭК: откуда, куда, кому и что внутри.
     *
     * @return array<string, mixed>
     */
    public static function payload(Order $order, DeliveryMethod $method): array
    {
        $settings = Deliveries::settings($method);
        $data = $order->delivery_data ?? [];
        $toDoor = (bool) ($data['to_door'] ?? false);

        $payload = [
            // 1 — интернет-магазин: к заявке прикладывается состав заказа.
            'type' => 1,
            'number' => (string) $order->number,
            'tariff_code' => (int) ($data['tariff'] ?? 0),
            'recipient' => self::recipient($order),
            'packages' => self::packages($order, $settings),
        ];

        if (filled($settings['sender']['name']) || filled($settings['sender']['phone'])) {
            $payload['sender'] = array_filter([
                'name' => $settings['sender']['name'] ?: null,
                'phones' => $settings['sender']['phone'] ? [['number' => $settings['sender']['phone']]] : null,
            ]);
        }

        // Откуда: свой пункт сдачи или адрес склада.
        if (filled($settings['shipment_point'])) {
            $payload['shipment_point'] = $settings['shipment_point'];
        } else {
            $payload['from_location'] = array_filter([
                'code' => $settings['from_code'],
                'address' => $settings['from_address'] ?: null,
            ]);
        }

        // Куда: пункт выдачи или адрес покупателя.
        if ($toDoor) {
            $payload['to_location'] = array_filter([
                'code' => $data['city_code'] ?? null,
                'address' => $data['address'] ?? null,
            ]);
        } else {
            $payload['delivery_point'] = $data['point'] ?? null;
        }

        if (filled($order->manager_comment)) {
            $payload['comment'] = mb_substr((string) $order->manager_comment, 0, 255);
        }

        return $payload;
    }

    /**
     * Получатель: имя и телефон из полей заказа.
     *
     * @return array<string, mixed>
     */
    protected static function recipient(Order $order): array
    {
        $phone = $order->customerValue('PHONE') ?? '';

        return array_filter([
            'name' => $order->customerName(),
            'phones' => $phone !== '' ? [['number' => $phone]] : [],
            'email' => $order->customerValue('EMAIL'),
        ]);
    }

    /**
     * Места с вложениями: по месту на строку заказа.
     *
     * Наложенного платежа нет — заказ либо оплачен онлайн, либо менеджер
     * получает деньги сам. Объявленная стоимость равна цене товара.
     *
     * @param  array<string, mixed>  $settings
     * @return array<int, array<string, mixed>>
     */
    protected static function packages(Order $order, array $settings): array
    {
        $packages = [];

        foreach ($order->items as $index => $item) {
            $element = $item->element_id ? IblockElement::query()->find($item->element_id) : null;
            $unit = Rates::unit($element, $settings, $element?->parentProduct());
            $quantity = max((int) ceil((float) $item->quantity), 1);

            $packages[] = [
                'number' => (string) ($index + 1),
                'weight' => max((int) round($unit['weight'] * $quantity), 1),
                'length' => $unit['length'],
                'width' => $unit['width'],
                'height' => $unit['height'],
                'items' => [[
                    'name' => mb_substr($item->name, 0, 255),
                    'ware_key' => self::article($item, $element, $settings),
                    // Наложенный платёж не используем.
                    'payment' => ['value' => 0],
                    'cost' => round((float) $item->price, 2),
                    'weight' => max((int) round($unit['weight']), 1),
                    'amount' => $quantity,
                ]],
            ];
        }

        return $packages;
    }

    /**
     * Артикул позиции: свойство из настроек, иначе код элемента, иначе id.
     *
     * @param  array<string, mixed>  $settings
     */
    protected static function article(OrderItem $item, ?IblockElement $element, array $settings): string
    {
        $value = $element?->property($settings['article']);

        if (is_iterable($value)) {
            $value = collect($value)->first();
        }

        $article = trim((string) ($value ?? '')) ?: (string) ($element?->code ?? '');

        return mb_substr($article ?: 'item-'.$item->id, 0, 20);
    }
}
