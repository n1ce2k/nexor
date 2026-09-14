<?php

namespace Nexor\Shop\Enums;

/**
 * Уровень корзины.
 *
 * Basic — добавить товар и сразу оформить: только цена, без остатков, форма
 * заказа прямо в корзине. Ultimate — остатки, промокоды и отдельное
 * оформление с доставкой и оплатой.
 */
enum CartEdition: string
{
    case Basic = 'basic';
    case Ultimate = 'ultimate';

    public function label(): string
    {
        return match ($this) {
            self::Basic => 'Basic',
            self::Ultimate => 'Ultimate',
        };
    }

    public function hint(): string
    {
        return match ($this) {
            self::Basic => 'Цена без учёта остатков, форма заказа прямо в корзине.',
            self::Ultimate => 'Учёт остатков, промокоды и оформление с доставкой и оплатой.',
        };
    }

    /**
     * Функция лицензии, без которой уровень недоступен.
     */
    public function feature(): string
    {
        return match ($this) {
            self::Basic => 'shop.cart.basic',
            self::Ultimate => 'shop.cart.ultimate',
        };
    }
}
