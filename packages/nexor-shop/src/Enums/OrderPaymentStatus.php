<?php

namespace Nexor\Shop\Enums;

/**
 * Оплачен ли заказ — то, что видно в списке заказов.
 *
 * Считается по платежам заказа, а не вводится руками: денормализация нужна,
 * чтобы список заказов не собирал платежи каждой строки.
 */
enum OrderPaymentStatus: string
{
    case Unpaid = 'unpaid';
    case Pending = 'pending';
    case Paid = 'paid';
    case PartiallyRefunded = 'partially_refunded';
    case Refunded = 'refunded';

    public function label(): string
    {
        return match ($this) {
            self::Unpaid => 'Не оплачен',
            self::Pending => 'Ожидает оплаты',
            self::Paid => 'Оплачен',
            self::PartiallyRefunded => 'Частичный возврат',
            self::Refunded => 'Возвращён',
        };
    }

    public function color(): string
    {
        return match ($this) {
            self::Unpaid => 'gray',
            self::Pending => 'amber',
            self::Paid => 'green',
            self::PartiallyRefunded => 'blue',
            self::Refunded => 'red',
        };
    }

    /**
     * @return array<int, array{value: string, label: string, color: string}>
     */
    public static function options(): array
    {
        return array_map(
            fn (self $case) => ['value' => $case->value, 'label' => $case->label(), 'color' => $case->color()],
            self::cases(),
        );
    }
}
