<?php

namespace Nexor\Shop\Enums;

enum OrderStatus: string
{
    case New = 'new';
    case Processing = 'processing';
    case Completed = 'completed';
    case Cancelled = 'cancelled';

    public function label(): string
    {
        return match ($this) {
            self::New => 'Новый',
            self::Processing => 'В работе',
            self::Completed => 'Выполнен',
            self::Cancelled => 'Отменён',
        };
    }

    /**
     * Цвет бейджа в панели.
     */
    public function color(): string
    {
        return match ($this) {
            self::New => 'blue',
            self::Processing => 'amber',
            self::Completed => 'green',
            self::Cancelled => 'gray',
        };
    }

    /**
     * @return array<int, array{value: string, label: string, color: string}>
     */
    public static function options(): array
    {
        return array_map(fn (self $case) => [
            'value' => $case->value,
            'label' => $case->label(),
            'color' => $case->color(),
        ], self::cases());
    }
}
