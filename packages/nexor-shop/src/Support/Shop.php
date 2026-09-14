<?php

namespace Nexor\Shop\Support;

use Nexor\Cms\Enums\Currency;
use Nexor\Cms\Models\CatalogProduct;
use Nexor\Cms\Support\Nexor;
use Nexor\Shop\Enums\AddedFeedback;
use Nexor\Shop\Enums\CartDisplay;
use Nexor\Shop\Enums\CartEdition;

/**
 * Настройки магазина и то, что из них следует.
 *
 * Сохранённое значение — это желание администратора; итоговый уровень корзины
 * ещё проверяется лицензией. Выбранный когда-то Ultimate на Lite-сайте
 * работает как Basic, а не ломает корзину.
 */
class Shop
{
    public const MODULE = 'shop';

    /**
     * @return array<string, mixed>
     */
    public static function settings(): array
    {
        return Nexor::modules()->settings(self::MODULE);
    }

    public static function edition(): CartEdition
    {
        $wanted = CartEdition::tryFrom((string) (self::settings()['edition'] ?? '')) ?? CartEdition::Basic;

        return $wanted === CartEdition::Ultimate && Nexor::feature(CartEdition::Ultimate->feature())
            ? CartEdition::Ultimate
            : CartEdition::Basic;
    }

    public static function isUltimate(): bool
    {
        return self::edition() === CartEdition::Ultimate;
    }

    public static function display(): CartDisplay
    {
        return CartDisplay::tryFrom((string) (self::settings()['display'] ?? '')) ?? CartDisplay::Offcanvas;
    }

    /**
     * Выезжает ли корзина сама, когда товар добавили.
     */
    public static function opensOnAdd(): bool
    {
        return self::display() === CartDisplay::Offcanvas && (bool) (self::settings()['open_on_add'] ?? true);
    }

    public static function addedFeedback(): AddedFeedback
    {
        return AddedFeedback::tryFrom((string) (self::settings()['added_feedback'] ?? '')) ?? AddedFeedback::Button;
    }

    /**
     * Остатки учитывает только Ultimate.
     */
    public static function tracksStock(): bool
    {
        return self::isUltimate();
    }

    public static function promocodesEnabled(): bool
    {
        return self::isUltimate() && Nexor::feature('shop.promocodes');
    }

    public static function checkoutEnabled(): bool
    {
        return self::isUltimate() && Nexor::feature('shop.checkout');
    }

    /**
     * Валюта, в которой корзина считает всё.
     */
    public static function currency(): Currency
    {
        return Currency::tryFrom((string) (self::settings()['currency'] ?? '')) ?? Currency::RUB;
    }

    /**
     * Сколько единиц валюты магазина стоит одна единица `$from`.
     *
     * Курсы вводятся относительно валюты магазина. Сменили валюту — старые
     * курсы больше не про неё, и цена в чужой валюте честно не считается,
     * пока курс не введут заново.
     */
    public static function rate(Currency $from): ?float
    {
        $settings = self::settings();
        $currency = self::currency();

        if ($from === $currency) {
            return 1.0;
        }

        if (($settings['rates_base'] ?? null) !== $currency->value) {
            return null;
        }

        $rate = (float) ($settings['rates'][$from->value] ?? 0);

        return $rate > 0 ? $rate : null;
    }

    /**
     * Сумма в валюте магазина или null, если курс не задан.
     */
    public static function convert(float $amount, Currency $from): ?float
    {
        $rate = self::rate($from);

        return $rate === null ? null : round($amount * $rate, 2);
    }

    /**
     * «1 800 ₽» в валюте магазина.
     */
    public static function format(float $amount): string
    {
        return CatalogProduct::formatPrice(round($amount, 2)).' '.self::currency()->symbol();
    }

    public static function cartUrl(): string
    {
        return url('/'.trim((string) config('nexor-shop.routes.cart', 'cart'), '/'));
    }

    public static function checkoutUrl(): string
    {
        return url('/'.trim((string) config('nexor-shop.routes.checkout', 'checkout'), '/'));
    }
}
