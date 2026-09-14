<?php

namespace Nexor\Cms\Enums;

/**
 * Уровень лицензии сайта.
 *
 * Уровни упорядочены: старший включает всё, что есть у младшего. Функции и
 * модули объявляют минимальный уровень, а не список уровней, поэтому новый
 * уровень добавляется одной строкой в `rank()`.
 */
enum License: string
{
    case Lite = 'lite';
    case Standart = 'standart';
    case Pro = 'pro';

    public function label(): string
    {
        return match ($this) {
            self::Lite => 'Lite',
            self::Standart => 'Standart',
            self::Pro => 'Pro',
        };
    }

    /**
     * Место в линейке: чем больше, тем больше доступно.
     */
    public function rank(): int
    {
        return match ($this) {
            self::Lite => 10,
            self::Standart => 20,
            self::Pro => 30,
        };
    }

    /**
     * Покрывает ли этот уровень функцию, которой нужен уровень `$required`.
     */
    public function allows(self $required): bool
    {
        return $this->rank() >= $required->rank();
    }

    /**
     * @return array<int, array{value: string, label: string, rank: int}>
     */
    public static function options(): array
    {
        $cases = self::cases();

        usort($cases, fn (self $a, self $b) => $a->rank() <=> $b->rank());

        return array_map(fn (self $case) => [
            'value' => $case->value,
            'label' => $case->label(),
            'rank' => $case->rank(),
        ], $cases);
    }
}
