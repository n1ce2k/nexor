<?php

namespace Nexor\Shop\Support\Delivery;

use Nexor\Cms\Support\Secrets;
use Nexor\Shop\Enums\DeliveryProvider;
use Nexor\Shop\Models\DeliveryMethod;

/**
 * Настройки способа доставки со службой: чтение, маскирование, сохранение.
 *
 * Устроено как `Payments` у оплаты: в базе лежит один json, секретный пароль
 * внутри зашифрован, в панель он уходит маской. Значения по умолчанию собраны
 * здесь, поэтому новый способ работает сразу после ввода ключей.
 */
class Deliveries
{
    /** Округление посчитанной цены. */
    public const ROUNDING = ['none', 'ruble', 'ten', 'hundred'];

    /** Что делать, если служба не посчитала. */
    public const ON_ERROR = ['manager', 'block'];

    /**
     * Настройки способа поверх умолчаний.
     *
     * @return array<string, mixed>
     */
    public static function settings(DeliveryMethod $method): array
    {
        $stored = $method->settings ?? [];

        return [
            // Доступ
            'account' => (string) ($stored['account'] ?? ''),
            'secret' => $stored['secret'] ?? null,
            // Тестовый контур СДЭК: другой адрес API и свои ключи.
            'test' => (bool) ($stored['test'] ?? false),

            // Отправитель
            'sender' => [
                'name' => (string) ($stored['sender']['name'] ?? ''),
                'phone' => (string) ($stored['sender']['phone'] ?? ''),
            ],
            'from_city' => (string) ($stored['from_city'] ?? ''),
            'from_code' => isset($stored['from_code']) && $stored['from_code'] !== '' ? (int) $stored['from_code'] : null,
            'from_address' => (string) ($stored['from_address'] ?? ''),
            'shipment_point' => (string) ($stored['shipment_point'] ?? ''),

            // Тарифы, из которых покупатель выбирает
            'tariffs' => self::tariffs($stored['tariffs'] ?? null),

            // Адрес для тарифов «до двери»: своё поле в блоке доставки или
            // поле заказа с этим кодом — тогда покупатель вводит адрес один раз.
            'address' => [
                'own' => (bool) ($stored['address']['own'] ?? true),
                'field' => self::code($stored['address']['field'] ?? '', 'ADDRESS'),
            ],

            // Вес и габариты: коды свойств товара и значения по умолчанию
            // Артикул позиции: СДЭК требует его у каждого товара в накладной.
            'article' => self::code($stored['article'] ?? '', 'ARTICLE'),

            'weight' => [
                'property' => (string) ($stored['weight']['property'] ?? 'WEIGHT'),
                'unit' => ($stored['weight']['unit'] ?? 'kg') === 'g' ? 'g' : 'kg',
                'default' => (float) ($stored['weight']['default'] ?? 1),
            ],
            'dimensions' => [
                'length' => self::dimension($stored['dimensions']['length'] ?? null, 'LENGTH'),
                'width' => self::dimension($stored['dimensions']['width'] ?? null, 'WIDTH'),
                'height' => self::dimension($stored['dimensions']['height'] ?? null, 'HEIGHT'),
            ],

            // Правила цены
            'price' => [
                'markup_percent' => (float) ($stored['price']['markup_percent'] ?? 0),
                'markup_fixed' => (float) ($stored['price']['markup_fixed'] ?? 0),
                'round' => self::one($stored['price']['round'] ?? null, self::ROUNDING, 'none'),
                'show_period' => (bool) ($stored['price']['show_period'] ?? true),
                // Служба не ответила: «рассчитает менеджер» или запрет оформления.
                'on_error' => self::one($stored['price']['on_error'] ?? null, self::ON_ERROR, 'manager'),
            ],

            // Выбор пункта выдачи
            'map' => [
                'enabled' => (bool) ($stored['map']['enabled'] ?? false),
                // Ключ уходит в браузер вместе с картой, поэтому не шифруется.
                'yandex_key' => (string) ($stored['map']['yandex_key'] ?? ''),
            ],
        ];
    }

    /**
     * Настройки для панели: секретный пароль — только маской.
     *
     * @return array<string, mixed>
     */
    public static function forPanel(DeliveryMethod $method): array
    {
        $settings = self::settings($method);

        return [...$settings, 'secret' => Secrets::mask($settings['secret'])];
    }

    /**
     * Что сохранить из панели.
     *
     * @param  array<string, mixed>  $input
     * @param  array<string, mixed>|null  $stored
     * @return array<string, mixed>
     */
    public static function fromInput(array $input, ?array $stored): array
    {
        return [
            'account' => trim((string) ($input['account'] ?? '')),
            'secret' => Secrets::fromInput($input['secret'] ?? null, $stored['secret'] ?? null),
            'test' => filter_var($input['test'] ?? false, FILTER_VALIDATE_BOOL),

            'sender' => [
                'name' => trim((string) ($input['sender']['name'] ?? '')),
                'phone' => trim((string) ($input['sender']['phone'] ?? '')),
            ],
            'from_city' => trim((string) ($input['from_city'] ?? '')),
            'from_code' => filled($input['from_code'] ?? null) ? (int) $input['from_code'] : null,
            'from_address' => trim((string) ($input['from_address'] ?? '')),
            'shipment_point' => trim((string) ($input['shipment_point'] ?? '')),

            'tariffs' => self::tariffs($input['tariffs'] ?? null),

            'address' => [
                'own' => filter_var($input['address']['own'] ?? true, FILTER_VALIDATE_BOOL),
                'field' => self::code($input['address']['field'] ?? '', 'ADDRESS'),
            ],

            'article' => self::code($input['article'] ?? '', 'ARTICLE'),

            'weight' => [
                'property' => self::code($input['weight']['property'] ?? '', 'WEIGHT'),
                'unit' => ($input['weight']['unit'] ?? 'kg') === 'g' ? 'g' : 'kg',
                'default' => max((float) ($input['weight']['default'] ?? 1), 0.0),
            ],
            'dimensions' => [
                'length' => self::dimension($input['dimensions']['length'] ?? null, 'LENGTH'),
                'width' => self::dimension($input['dimensions']['width'] ?? null, 'WIDTH'),
                'height' => self::dimension($input['dimensions']['height'] ?? null, 'HEIGHT'),
            ],

            'price' => [
                'markup_percent' => max((float) ($input['price']['markup_percent'] ?? 0), 0.0),
                'markup_fixed' => max((float) ($input['price']['markup_fixed'] ?? 0), 0.0),
                'round' => self::one($input['price']['round'] ?? null, self::ROUNDING, 'none'),
                'show_period' => filter_var($input['price']['show_period'] ?? true, FILTER_VALIDATE_BOOL),
                'on_error' => self::one($input['price']['on_error'] ?? null, self::ON_ERROR, 'manager'),
            ],

            'map' => [
                'enabled' => filter_var($input['map']['enabled'] ?? false, FILTER_VALIDATE_BOOL),
                'yandex_key' => trim((string) ($input['map']['yandex_key'] ?? '')),
            ],
        ];
    }

    /**
     * Готов ли способ считать: есть ключи, город отправления и хотя бы один тариф.
     */
    public static function ready(?DeliveryMethod $method): bool
    {
        if ($method === null || ($method->provider ?? DeliveryProvider::None) !== DeliveryProvider::Cdek) {
            return false;
        }

        $settings = self::settings($method);

        return $settings['account'] !== ''
            && Secrets::decrypt($settings['secret']) !== null
            && $settings['from_code'] !== null
            && $settings['tariffs'] !== [];
    }

    /**
     * Клиент СДЭК для способа доставки.
     *
     * @throws DeliveryException
     */
    public static function client(DeliveryMethod $method): Cdek
    {
        $settings = self::settings($method);
        $secret = Secrets::decrypt($settings['secret']);

        if ($settings['account'] === '' || $secret === null) {
            throw new DeliveryException('У способа доставки «'.$method->name.'» не заданы ключи СДЭК.');
        }

        return new Cdek($settings['account'], $secret, $settings['test'], $method->id);
    }

    /**
     * Цена службы с наценкой и округлением из настроек.
     *
     * @param  array<string, mixed>  $rules  Ключ `price` настроек
     */
    public static function applyRules(float $price, array $rules): float
    {
        $price = $price * (1 + (float) ($rules['markup_percent'] ?? 0) / 100) + (float) ($rules['markup_fixed'] ?? 0);

        $step = match ($rules['round'] ?? 'none') {
            'ruble' => 1,
            'ten' => 10,
            'hundred' => 100,
            default => 0,
        };

        return $step > 0 ? (float) (ceil($price / $step) * $step) : round($price, 2);
    }

    /**
     * Тарифы из настроек: код, подпись и куда везут.
     *
     * @return array<int, array{code: int, name: string, to_door: bool}>
     */
    protected static function tariffs(mixed $rows): array
    {
        $tariffs = [];

        foreach ((array) $rows as $row) {
            $code = (int) ($row['code'] ?? 0);

            if ($code <= 0) {
                continue;
            }

            $tariffs[$code] = [
                'code' => $code,
                'name' => trim((string) ($row['name'] ?? '')) ?: 'Тариф '.$code,
                // Тариф «до двери» требует адрес, остальные — пункт выдачи.
                'to_door' => filter_var($row['to_door'] ?? false, FILTER_VALIDATE_BOOL),
            ];
        }

        return array_values($tariffs);
    }

    /**
     * @return array{property: string, default: float}
     */
    protected static function dimension(mixed $row, string $fallback): array
    {
        return [
            'property' => self::code($row['property'] ?? '', $fallback),
            'default' => max((float) ($row['default'] ?? 20), 0.0),
        ];
    }

    /**
     * Значение из списка допустимых, иначе — значение по умолчанию.
     *
     * @param  array<int, string>  $allowed
     */
    protected static function one(mixed $value, array $allowed, string $fallback): string
    {
        return in_array($value, $allowed, true) ? (string) $value : $fallback;
    }

    /**
     * Символьный код свойства: как в инфоблоке — латиница в верхнем регистре.
     */
    protected static function code(mixed $value, string $fallback): string
    {
        $code = strtoupper(trim((string) $value));

        return preg_match('/^[A-Z0-9_]+$/', $code) === 1 ? $code : $fallback;
    }
}
