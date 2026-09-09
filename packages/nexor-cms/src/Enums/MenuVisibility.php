<?php

namespace Nexor\Cms\Enums;

use Nexor\Cms\Contracts\NexorUser;

/**
 * Кому виден пункт меню.
 *
 * Нужно, чтобы «Войти» и «Личный кабинет» жили в одном меню и не мешали друг
 * другу.
 */
enum MenuVisibility: string
{
    case All = 'all';
    case Guests = 'guests';
    case Users = 'users';

    public function label(): string
    {
        return match ($this) {
            self::All => 'Всем',
            self::Guests => 'Только гостям',
            self::Users => 'Только авторизованным',
        };
    }

    public function allows(?NexorUser $user): bool
    {
        return match ($this) {
            self::All => true,
            self::Guests => $user === null,
            self::Users => $user !== null,
        };
    }

    /**
     * @return array<int, array{value: string, label: string}>
     */
    public static function options(): array
    {
        return array_map(fn (self $case) => [
            'value' => $case->value,
            'label' => $case->label(),
        ], self::cases());
    }
}
