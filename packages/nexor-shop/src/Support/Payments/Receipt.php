<?php

namespace Nexor\Shop\Support\Payments;

use Nexor\Shop\Models\Order;
use Nexor\Shop\Models\OrderItem;
use Nexor\Shop\Support\Shop;

/**
 * Чек по 54-ФЗ для платежа: состав заказа, ставки НДС и контакт покупателя.
 *
 * Ставки и система налогообложения берутся из настроек магазина: у одного
 * продавца они одни и те же, а менять их в каждом заказе — верный способ
 * получить расхождение с кассой.
 */
class Receipt
{
    /** Системы налогообложения ЮKassa. */
    public const TAX_SYSTEMS = [
        1 => 'Общая (ОСН)',
        2 => 'Упрощённая, доходы (УСН доходы)',
        3 => 'Упрощённая, доходы минус расходы (УСН доходы − расходы)',
        4 => 'Единый налог на вменённый доход (ЕНВД)',
        5 => 'Единый сельскохозяйственный налог (ЕСН)',
        6 => 'Патентная система (ПСН)',
    ];

    /** Ставки НДС ЮKassa. */
    public const VAT_CODES = [
        1 => 'Без НДС',
        2 => '0%',
        3 => '10%',
        4 => '20%',
        5 => '10/110',
        6 => '20/120',
    ];

    /** Предмет расчёта: что продаём. */
    public const SUBJECTS = [
        'commodity' => 'Товар',
        'service' => 'Услуга',
        'work' => 'Работа',
        'payment' => 'Платёж',
    ];

    /** Способ расчёта: когда платят. */
    public const MODES = [
        'full_payment' => 'Полная оплата',
        'full_prepayment' => 'Полная предоплата',
        'partial_prepayment' => 'Частичная предоплата',
        'advance' => 'Аванс',
        'credit' => 'В кредит',
    ];

    /**
     * Нужно ли слать чек вместе с платежом.
     */
    public static function enabled(): bool
    {
        return (bool) (Shop::settings()['receipts_enabled'] ?? false);
    }

    /**
     * Чек для заказа; null — чеки выключены или некому его отправить.
     *
     * @return array<string, mixed>|null
     */
    public static function forOrder(Order $order): ?array
    {
        if (! self::enabled()) {
            return null;
        }

        $customer = self::customer($order);

        if ($customer === []) {
            return null;
        }

        $settings = Shop::settings();
        $items = [];

        foreach ($order->items as $item) {
            $items[] = self::item($item, $order, (int) ($settings['vat_code'] ?? 1), (string) ($settings['payment_subject'] ?? 'commodity'));
        }

        if ((float) $order->delivery_price > 0) {
            $items[] = [
                'description' => self::description($order->delivery_name ?: 'Доставка'),
                'quantity' => '1.000',
                'amount' => self::money((float) $order->delivery_price, $order),
                'vat_code' => (int) ($settings['delivery_vat_code'] ?? $settings['vat_code'] ?? 1),
                'payment_subject' => 'service',
                'payment_mode' => (string) ($settings['payment_mode'] ?? 'full_payment'),
            ];
        }

        return [
            'customer' => $customer,
            'tax_system_code' => (int) ($settings['tax_system_code'] ?? 1),
            'items' => $items,
        ];
    }

    /**
     * Чек возврата: те же позиции, но на возвращаемую сумму.
     *
     * Частичный возврат оформляется одной строкой: разбирать, за какие именно
     * товары вернули деньги, менеджер в панели не может, а выдумывать состав
     * чека за него нельзя.
     *
     * @return array<string, mixed>|null
     */
    public static function forRefund(Order $order, float $amount): ?array
    {
        $receipt = self::forOrder($order);

        if ($receipt === null) {
            return null;
        }

        if (round($amount, 2) === round((float) $order->total, 2)) {
            return $receipt;
        }

        $settings = Shop::settings();

        $receipt['items'] = [[
            'description' => self::description('Возврат по заказу №'.$order->number),
            'quantity' => '1.000',
            'amount' => self::money($amount, $order),
            'vat_code' => (int) ($settings['vat_code'] ?? 1),
            'payment_subject' => (string) ($settings['payment_subject'] ?? 'commodity'),
            'payment_mode' => (string) ($settings['payment_mode'] ?? 'full_payment'),
        ]];

        return $receipt;
    }

    /**
     * Контакт покупателя: без него чек отправить некуда.
     *
     * @return array<string, string>
     */
    protected static function customer(Order $order): array
    {
        $email = trim((string) $order->customerValue('EMAIL'));
        $phone = preg_replace('/[^0-9+]/', '', (string) $order->customerValue('PHONE'));
        $customer = [];

        if ($email !== '' && filter_var($email, FILTER_VALIDATE_EMAIL)) {
            $customer['email'] = $email;
        }

        if ($phone !== null && strlen($phone) >= 11) {
            $customer['phone'] = str_starts_with($phone, '+') ? $phone : '+'.ltrim($phone, '8');
        }

        return $customer;
    }

    /**
     * @return array<string, mixed>
     */
    protected static function item(OrderItem $item, Order $order, int $vatCode, string $subject): array
    {
        $settings = Shop::settings();

        return [
            'description' => self::description($item->name),
            'quantity' => number_format((float) $item->quantity, 3, '.', ''),
            'amount' => self::money((float) $item->price, $order),
            'vat_code' => $vatCode,
            'payment_subject' => $subject,
            'payment_mode' => (string) ($settings['payment_mode'] ?? 'full_payment'),
            'measure' => self::measure((string) $item->measure),
        ];
    }

    /**
     * @return array{value: string, currency: string}
     */
    protected static function money(float $amount, Order $order): array
    {
        return [
            'value' => number_format($amount, 2, '.', ''),
            'currency' => $order->currency?->value ?? Shop::currency()->value,
        ];
    }

    /**
     * Название позиции в чеке — не длиннее 128 символов.
     */
    protected static function description(string $name): string
    {
        return mb_substr(trim($name) ?: 'Товар', 0, 128);
    }

    /**
     * Единица измерения в словаре ЮKassa; незнакомая — «piece».
     */
    protected static function measure(string $measure): string
    {
        return match (mb_strtolower(trim($measure))) {
            'кг', 'kg' => 'kilogram',
            'г', 'g' => 'gram',
            'л', 'l' => 'litre',
            'м', 'm' => 'metre',
            'м2' => 'square_metre',
            'м3' => 'cubic_metre',
            default => 'piece',
        };
    }
}
