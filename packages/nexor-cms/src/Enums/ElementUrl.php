<?php

namespace Nexor\Cms\Enums;

/**
 * Канонический адрес элемента инфоблока.
 *
 * Элемент открывается только по нему: остальные варианты адреса отвечают
 * 301-редиректом, чтобы у страницы был один адрес — для поисковиков и ссылок.
 */
enum ElementUrl: string
{
    case Nested = 'nested';
    case Flat = 'flat';

    public function label(): string
    {
        return match ($this) {
            self::Nested => 'С разделами',
            self::Flat => 'Без разделов',
        };
    }

    /**
     * Пример адреса для инфоблока с кодом `$code`.
     */
    public function example(string $code): string
    {
        return match ($this) {
            self::Nested => "/{$code}/razdel/podrazdel/element",
            self::Flat => "/{$code}/element",
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
