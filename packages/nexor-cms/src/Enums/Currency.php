<?php

namespace Nexor\Cms\Enums;

/**
 * Валюта цены — «тип цены» на вкладке «Цена».
 *
 * Хранится кодом ISO рядом с самой ценой: пересчёта между валютами нет, цена
 * просто показывается в той валюте, в которой её назначили.
 */
enum Currency: string
{
    case RUB = 'RUB';
    case UAH = 'UAH';
    case KZT = 'KZT';
    case EUR = 'EUR';
    case USD = 'USD';

    public function label(): string
    {
        return match ($this) {
            self::RUB => 'Рубли',
            self::UAH => 'Гривны',
            self::KZT => 'Тенге',
            self::EUR => 'Евро',
            self::USD => 'Доллары',
        };
    }

    /**
     * Знак, который приписывается к цене на витрине.
     */
    public function symbol(): string
    {
        return match ($this) {
            self::RUB => '₽',
            self::UAH => '₴',
            self::KZT => '₸',
            self::EUR => '€',
            self::USD => '$',
        };
    }

    /**
     * @return array<int, array{value: string, label: string, symbol: string}>
     */
    public static function options(): array
    {
        return array_map(fn (self $case) => [
            'value' => $case->value,
            'label' => $case->label(),
            'symbol' => $case->symbol(),
        ], self::cases());
    }
}
