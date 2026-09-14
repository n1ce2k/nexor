<?php

namespace Nexor\Shop\Enums;

/**
 * Как открывается корзина по кнопке в шапке.
 */
enum CartDisplay: string
{
    case Offcanvas = 'offcanvas';
    case Page = 'page';

    public function label(): string
    {
        return match ($this) {
            self::Offcanvas => 'Выезжающая панель',
            self::Page => 'Отдельная страница',
        };
    }

    public function hint(): string
    {
        return match ($this) {
            self::Offcanvas => 'Корзина выезжает сбоку поверх страницы.',
            self::Page => 'Кнопка в шапке ведёт на страницу корзины.',
        };
    }
}
