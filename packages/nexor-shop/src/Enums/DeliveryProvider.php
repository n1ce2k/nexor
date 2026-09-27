<?php

namespace Nexor\Shop\Enums;

/**
 * Кто считает стоимость доставки.
 *
 * `None` — своя цена из настроек способа: фиксированная сумма и порог
 * «бесплатно от». `Cdek` — цену и срок спрашиваем у СДЭК на оформлении.
 */
enum DeliveryProvider: string
{
    case None = 'none';
    case Cdek = 'cdek';

    public function label(): string
    {
        return match ($this) {
            self::None => 'Своя цена',
            self::Cdek => 'СДЭК',
        };
    }

    /**
     * Считает ли цену служба доставки, а не настройки способа.
     */
    public function isCalculated(): bool
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
