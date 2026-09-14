<?php

namespace Nexor\Shop;

use Nexor\Cms\Enums\License;
use Nexor\Cms\Support\Modules\Module;

/**
 * Модуль «Магазин»: корзина, промокоды, оформление и заказы.
 */
class ShopModule extends Module
{
    public const VERSION = '0.1.5';

    public function code(): string
    {
        return 'shop';
    }

    public function name(): string
    {
        return 'Магазин';
    }

    public function description(): string
    {
        return 'Корзина Basic и Ultimate, промокоды, оформление заказа и раздел «Заказы».';
    }

    public function version(): string
    {
        return self::VERSION;
    }

    public function license(): License
    {
        return License::Lite;
    }

    public function features(): array
    {
        return [
            'shop.cart.basic' => ['label' => 'Корзина Basic: цена без остатков, форма заказа в корзине', 'license' => License::Lite],
            'shop.cart.ultimate' => ['label' => 'Корзина Ultimate: учёт остатков', 'license' => License::Standart],
            'shop.promocodes' => ['label' => 'Промокоды', 'license' => License::Standart],
            'shop.checkout' => ['label' => 'Оформление заказа: доставка и оплата', 'license' => License::Standart],
        ];
    }

    public function permissions(): array
    {
        return [
            'shop_orders' => [
                'label' => 'Магазин: заказы',
                'sort' => 800,
                'items' => [
                    'shop.orders.view' => 'Просмотр заказов',
                    'shop.orders.update' => 'Изменение заказов',
                    'shop.orders.delete' => 'Удаление заказов',
                ],
            ],
            'shop_cart' => [
                'label' => 'Магазин: корзина',
                'sort' => 810,
                'items' => [
                    'shop.cart.view' => 'Просмотр настроек корзины',
                    'shop.cart.update' => 'Изменение настроек корзины и полей заказа',
                ],
            ],
            'shop_promocodes' => [
                'label' => 'Магазин: промокоды',
                'sort' => 820,
                'items' => [
                    'shop.promocodes.view' => 'Просмотр промокодов',
                    'shop.promocodes.create' => 'Создание промокодов',
                    'shop.promocodes.update' => 'Изменение промокодов',
                    'shop.promocodes.delete' => 'Удаление промокодов',
                ],
            ],
            'shop_checkout' => [
                'label' => 'Магазин: оформление',
                'sort' => 830,
                'items' => [
                    'shop.checkout.view' => 'Просмотр способов доставки и оплаты',
                    'shop.checkout.update' => 'Изменение способов доставки и оплаты',
                ],
            ],
        ];
    }

    public function panelAssets(): ?array
    {
        $root = dirname(__DIR__);

        return [
            'dist' => $root.'/dist',
            'script' => 'panel.js',
            'style' => 'panel.css',
            'source' => $root.'/resources/js/panel.js',
        ];
    }

    public function apiRoutes(): ?string
    {
        return dirname(__DIR__).'/routes/api.php';
    }

    public function webRoutes(): ?string
    {
        return dirname(__DIR__).'/routes/web.php';
    }

    public function defaultSettings(): array
    {
        return [
            'edition' => 'basic',
            'display' => 'offcanvas',
            // Выезжающая корзина открывается сама при добавлении товара.
            'open_on_add' => true,
            // Если не открывается — как сказать «добавлено»: button или toast.
            'added_feedback' => 'button',
            'currency' => 'RUB',
            // Курсы к валюте магазина: «1 USD = 90 ₽». rates_base — к какой
            // валюте их вводили; сменилась валюта — курсы надо ввести заново.
            'rates' => [],
            'rates_base' => 'RUB',
            'notify_admin' => true,
            'admin_email' => '',
            'notify_customer' => true,
            // Telegram: токен бота хранится зашифрованным.
            'telegram_enabled' => false,
            'telegram_token' => '',
            'telegram_chat_id' => '',
        ];
    }
}
