<?php

namespace Nexor\Shop\Support\Delivery;

use Illuminate\Support\Facades\Cache;
use Nexor\Cms\Models\IblockElement;
use Nexor\Shop\Models\DeliveryMethod;
use Nexor\Shop\Support\CartSummary;

/**
 * Расчёт доставки СДЭК для корзины.
 *
 * Здесь собирается всё, что служба знает о посылке: города, места и их вес с
 * габаритами. Сам разговор с API — в `Cdek`, правила наценки — в `Deliveries`.
 *
 * Вес и габариты берутся из свойств товара по кодам из настроек способа.
 * Незаполненное свойство заменяется значением по умолчанию — иначе первый же
 * товар без веса ломал бы оформление всей корзины.
 */
class Rates
{
    /** Ответ СДЭК живёт недолго: тарифы меняются, а оформление — процесс на минуты. */
    public const CACHE_MINUTES = 15;

    /**
     * Тарифы способа с ценой и сроком для этой корзины.
     *
     * Ошибка по одному тарифу не отменяет остальные: её видно в самом тарифе.
     *
     * @return array<int, array{code: int, name: string, to_door: bool, price: float|null, period_min: int|null, period_max: int|null, error: string|null}>
     */
    public static function forCart(
        DeliveryMethod $method,
        CartSummary $summary,
        int $cityCode,
        ?string $address = null,
    ): array {
        $settings = Deliveries::settings($method);
        $packages = self::packages($summary, $settings);

        return array_map(function (array $tariff) use ($method, $settings, $packages, $cityCode, $address): array {
            $row = $tariff + ['price' => null, 'period_min' => null, 'period_max' => null, 'error' => null];

            // До двери нужен адрес: без него СДЭК посчитает не то, что привезут.
            if ($tariff['to_door'] && blank($address)) {
                return [...$row, 'error' => 'Укажите адрес доставки.'];
            }

            try {
                $result = self::calculate($method, $settings, $tariff, $packages, $cityCode, $address);
            } catch (DeliveryException $exception) {
                return [...$row, 'error' => $exception->getMessage()];
            }

            return [
                ...$row,
                'price' => Deliveries::applyRules($result['price'], $settings['price']),
                'period_min' => $result['period_min'],
                'period_max' => $result['period_max'],
            ];
        }, $settings['tariffs']);
    }

    /**
     * Места посылки: одно на строку корзины.
     *
     * Вес места — вес единицы на количество, габариты — габариты единицы. Для
     * расчёта этого достаточно: СДЭК считает по весу и объёму, а как именно
     * упакуют, решает склад.
     *
     * @param  array<string, mixed>  $settings
     * @return array<int, array{weight: int, length: int, width: int, height: int}>
     */
    public static function packages(CartSummary $summary, array $settings): array
    {
        $packages = [];

        foreach ($summary->lines as $line) {
            $unit = self::unit($line->element, $settings, $line->product);
            $quantity = max((float) $line->quantity, 1.0);

            $packages[] = [
                // СДЭК принимает вес в граммах и не любит ноль.
                'weight' => max((int) round($unit['weight'] * $quantity), 1),
                'length' => $unit['length'],
                'width' => $unit['width'],
                'height' => $unit['height'],
            ];
        }

        return $packages;
    }

    /**
     * Вес (в граммах) и габариты (в сантиметрах) одной единицы товара.
     *
     * Читаются из свойств по кодам из настроек; незаполненное заменяется
     * значением по умолчанию. У предложения свойство может быть не заполнено —
     * тогда спрашиваем товар, к которому оно относится.
     *
     * @param  array<string, mixed>  $settings
     * @return array{weight: float, length: int, width: int, height: int}
     */
    public static function unit(?IblockElement $element, array $settings, ?IblockElement $parent = null): array
    {
        $weight = self::property($element, $settings['weight']['property'])
            ?? self::property($parent, $settings['weight']['property'])
            ?? $settings['weight']['default'];

        $weight = max((float) $weight, 0.0);

        return [
            'weight' => $settings['weight']['unit'] === 'g' ? $weight : $weight * 1000,
            'length' => self::dimension($element, $settings['dimensions']['length']),
            'width' => self::dimension($element, $settings['dimensions']['width']),
            'height' => self::dimension($element, $settings['dimensions']['height']),
        ];
    }

    /**
     * Габарит в сантиметрах, целым числом: дробные сантиметры СДЭК не принимает.
     *
     * @param  array{property: string, default: float}  $rule
     */
    protected static function dimension(?IblockElement $element, array $rule): int
    {
        $value = self::property($element, $rule['property']) ?? $rule['default'];

        return max((int) ceil((float) $value), 1);
    }

    /**
     * Числовое значение свойства элемента или null, если его нет.
     */
    protected static function property(?IblockElement $element, string $code): ?float
    {
        if ($element === null || $code === '') {
            return null;
        }

        $value = $element->property($code);

        if (is_iterable($value)) {
            $value = collect($value)->first();
        }

        return is_numeric($value) && (float) $value > 0 ? (float) $value : null;
    }

    /**
     * Один тариф с кэшем: пока покупатель правит поля, одинаковые запросы
     * не должны улетать в СДЭК по десять раз.
     *
     * @param  array<string, mixed>  $settings
     * @param  array{code: int, name: string, to_door: bool}  $tariff
     * @param  array<int, array<string, int>>  $packages
     * @return array{price: float, period_min: int|null, period_max: int|null}
     *
     * @throws DeliveryException
     */
    protected static function calculate(
        DeliveryMethod $method,
        array $settings,
        array $tariff,
        array $packages,
        int $cityCode,
        ?string $address,
    ): array {
        $payload = [
            'type' => 1, // интернет-магазин
            'tariff_code' => $tariff['code'],
            'from_location' => array_filter([
                'code' => $settings['from_code'],
                'address' => $settings['from_address'] ?: null,
            ]),
            'to_location' => array_filter([
                'code' => $cityCode,
                'address' => $tariff['to_door'] ? $address : null,
            ]),
            'packages' => $packages,
        ];

        $key = 'nexor-shop:cdek:rate:'.$method->id.':'.md5(json_encode($payload));

        $cached = Cache::get($key);

        if (is_array($cached)) {
            return $cached;
        }

        $result = Deliveries::client($method)->calculate($payload);

        Cache::put($key, $result, now()->addMinutes(self::CACHE_MINUTES));

        return $result;
    }
}
