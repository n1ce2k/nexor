<?php

namespace Nexor\Shop\Enums;

/**
 * На что действует промокод.
 */
enum PromocodeScope: string
{
    case All = 'all';
    case Sections = 'sections';
    case Elements = 'elements';

    public function label(): string
    {
        return match ($this) {
            self::All => 'На весь заказ',
            self::Sections => 'На товары разделов',
            self::Elements => 'На отдельные товары',
        };
    }

    /**
     * @return array<int, array{value: string, label: string}>
     */
    public static function options(): array
    {
        return array_map(fn (self $case) => ['value' => $case->value, 'label' => $case->label()], self::cases());
    }
}
