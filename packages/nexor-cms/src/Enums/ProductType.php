<?php

namespace Nexor\Cms\Enums;

/**
 * Тип товара — как в Битриксе: простой товар или товар с предложениями.
 *
 * У простого товара своя цена и свой остаток. У товара с предложениями их нет
 * вовсе: цена и наличие приходят из его торговых предложений, поэтому форма
 * прячет «Цену» и «Остатки» и показывает вкладку «Предложения».
 */
enum ProductType: string
{
    case Simple = 'simple';
    case WithOffers = 'with_offers';

    public function label(): string
    {
        return match ($this) {
            self::Simple => 'Простой товар',
            self::WithOffers => 'Товар с торговыми предложениями',
        };
    }

    public function hint(): string
    {
        return match ($this) {
            self::Simple => 'Своя цена и свой остаток.',
            self::WithOffers => 'Цена и остаток — у предложений: размеров, цветов, фасовок.',
        };
    }

    /**
     * Берутся ли цена и наличие из предложений, а не у самого товара.
     */
    public function usesOffers(): bool
    {
        return $this === self::WithOffers;
    }

    /**
     * @return array<int, array{value: string, label: string, hint: string}>
     */
    public static function options(): array
    {
        return array_map(fn (self $case) => [
            'value' => $case->value,
            'label' => $case->label(),
            'hint' => $case->hint(),
        ], self::cases());
    }
}
