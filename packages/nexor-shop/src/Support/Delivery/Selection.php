<?php

namespace Nexor\Shop\Support\Delivery;

use Illuminate\Validation\ValidationException;
use Nexor\Shop\Models\DeliveryMethod;
use Nexor\Shop\Support\CartSummary;

/**
 * Выбор покупателя у службы доставки: город, тариф, пункт или адрес.
 *
 * Цену пересчитываем здесь заново, а не берём из формы: между расчётом и
 * нажатием «Подтвердить заказ» человек успевает поправить корзину, а из
 * браузера может прийти что угодно.
 */
class Selection
{
    /**
     * Проверяет выбор и считает цену доставки.
     *
     * @param  array<string, mixed>  $input  Что выбрал покупатель
     * @param  array<string, mixed>  $customer  Поля заказа: оттуда берём адрес,
     *                                          если способ настроен на своё поле
     * @return array{price: float, label: string, data: array<string, mixed>}
     *
     * @throws ValidationException
     */
    public static function resolve(DeliveryMethod $method, CartSummary $summary, array $input, array $customer = []): array
    {
        $settings = Deliveries::settings($method);

        $cityCode = (int) ($input['city_code'] ?? 0);
        $tariffCode = (int) ($input['tariff'] ?? 0);
        $address = self::address($settings, $input, $customer);
        $point = trim((string) ($input['point'] ?? ''));

        if ($cityCode <= 0) {
            self::fail('Выберите город доставки.');
        }

        $tariff = collect($settings['tariffs'])->firstWhere('code', $tariffCode);

        if ($tariff === null) {
            self::fail('Выберите способ доставки СДЭК.');
        }

        if ($tariff['to_door'] && $address === '') {
            self::fail($settings['address']['own']
                ? 'Укажите адрес доставки.'
                : 'Заполните адрес в данных покупателя.');
        }

        if (! $tariff['to_door'] && $point === '') {
            self::fail('Выберите пункт выдачи.');
        }

        $data = [
            'provider' => 'cdek',
            'city_code' => $cityCode,
            'city' => trim((string) ($input['city'] ?? '')),
            'tariff' => $tariffCode,
            'tariff_name' => $tariff['name'],
            'to_door' => $tariff['to_door'],
            'address' => $tariff['to_door'] ? $address : null,
            'point' => $tariff['to_door'] ? null : $point,
            'point_address' => $tariff['to_door'] ? null : (trim((string) ($input['point_address'] ?? '')) ?: null),
        ];

        // Порог «бесплатно от» перебивает расчёт: так решил магазин, а не служба.
        if ($method->isFree($summary->total())) {
            return ['price' => 0.0, 'label' => $method->name.', '.$tariff['name'], 'data' => $data + ['free' => true]];
        }

        $rate = collect(Rates::forCart($method, $summary, $cityCode, $address))
            ->firstWhere('code', $tariffCode);

        if ($rate === null || $rate['price'] === null) {
            // Служба не посчитала: либо оформляем с нулём и пометкой для менеджера,
            // либо не даём оформить — как настроено у способа.
            if ($settings['price']['on_error'] === 'block') {
                self::fail($rate['error'] ?? 'СДЭК не рассчитал доставку по этому тарифу.');
            }

            return [
                'price' => 0.0,
                'label' => $method->name.', '.$tariff['name'].' (рассчитает менеджер)',
                'data' => $data + ['manual' => true, 'error' => $rate['error'] ?? null],
            ];
        }

        return [
            'price' => (float) $rate['price'],
            'label' => $method->name.', '.$tariff['name'],
            'data' => $data + [
                'period_min' => $rate['period_min'],
                'period_max' => $rate['period_max'],
            ],
        ];
    }

    /**
     * Адрес доставки: своё поле способа или поле заказа с заданным кодом.
     *
     * @param  array<string, mixed>  $settings
     * @param  array<string, mixed>  $input
     * @param  array<string, mixed>  $customer
     */
    public static function address(array $settings, array $input, array $customer = []): string
    {
        if ($settings['address']['own']) {
            return trim((string) ($input['address'] ?? ''));
        }

        return trim((string) ($customer[$settings['address']['field']] ?? ''));
    }

    /**
     * @throws ValidationException
     */
    protected static function fail(string $message): never
    {
        throw ValidationException::withMessages(['delivery' => $message]);
    }
}
