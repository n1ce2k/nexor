<?php

namespace Nexor\Shop\Enums;

/**
 * Кто принимает деньги за заказ.
 *
 * `None` — способ без приёма онлайн: наличными курьеру, счёт юрлицу.
 */
enum PaymentProvider: string
{
    case None = 'none';
    case YooKassa = 'yookassa';

    public function label(): string
    {
        return match ($this) {
            self::None => 'Без приёма онлайн',
            self::YooKassa => 'ЮKassa',
        };
    }

    public function isOnline(): bool
    {
        return $this !== self::None;
    }

    /**
     * @return array<int, array{value: string, label: string}>
     */
    public static function options(): array
    {
        return array_map(
            fn (self $case) => ['value' => $case->value, 'label' => $case->label()],
            self::cases(),
        );
    }
}
