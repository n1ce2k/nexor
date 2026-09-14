<?php

namespace Nexor\Shop\Enums;

/**
 * Как сказать покупателю «добавлено», если корзина при этом не открывается.
 */
enum AddedFeedback: string
{
    case Button = 'button';
    case Toast = 'toast';

    public function label(): string
    {
        return match ($this) {
            self::Button => 'Текст в кнопке',
            self::Toast => 'Всплывающее сообщение',
        };
    }

    public function hint(): string
    {
        return match ($this) {
            self::Button => 'Кнопка на пару секунд меняется на «Добавлено».',
            self::Toast => 'В углу экрана появляется «Товар добавлен» со ссылкой в корзину.',
        };
    }
}
