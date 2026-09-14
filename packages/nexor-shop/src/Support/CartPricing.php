<?php

namespace Nexor\Shop\Support;

use Nexor\Cms\Models\IblockElement;
use Nexor\Cms\Support\Nexor;

/**
 * Считает строку корзины для элемента: можно ли его купить, почём и сколько.
 *
 * Здесь вся разница между Basic и Ultimate по товару: Basic смотрит только на
 * цену и продаёт штуками, Ultimate учитывает коэффициент и остаток.
 */
class CartPricing
{
    /**
     * Причина, по которой элемент нельзя купить вовсе, или null.
     *
     * Отличается от проблем строки: строка может временно не продаваться (кончился
     * остаток), а это — «такого товара в корзине быть не может».
     */
    public static function refusal(IblockElement $element): ?string
    {
        $element->loadMissing(['iblock', 'catalog']);

        if (! $element->iblock?->is_active || ! self::isActiveNow($element) || ! $element->iblock->hasCommerce()) {
            return 'Этот товар нельзя купить.';
        }

        if ($element->iblock->product_iblock_id && ! Nexor::feature('catalog.offers')) {
            return 'Этот товар нельзя купить.';
        }

        if ($element->catalog?->usesOffers() && $element->iblock->hasOffers()) {
            return 'Выберите вариант товара.';
        }

        if (! $element->catalog?->hasPrice()) {
            return 'У товара не указана цена.';
        }

        return null;
    }

    public static function line(IblockElement $element, float $quantity): CartLine
    {
        $element->loadMissing(['iblock', 'catalog', 'section']);

        $catalog = $element->catalog;
        $product = self::productOf($element);
        $ultimate = Shop::tracksStock();

        $problem = self::refusal($element);

        $step = $ultimate ? max((float) ($catalog?->ratio ?? 1), 0.001) : 1.0;
        $max = null;

        if ($ultimate && $catalog && $catalog->quantity_trace && ! $catalog->can_buy_zero) {
            $max = floor(round((float) $catalog->quantity / $step, 6)) * $step;
            $problem ??= $max <= 0 ? 'Нет в наличии.' : null;
        }

        $quantity = self::normalizeQuantity($quantity, $step, $max);

        $currency = $catalog?->currency;
        $shopCurrency = Shop::currency();
        $converted = $currency !== null && $currency !== $shopCurrency;

        $base = $catalog?->hasPrice() ? Shop::convert((float) $catalog->price, $currency) : null;
        $unit = $catalog?->hasPrice() ? Shop::convert((float) $catalog->finalPrice(), $currency) : null;

        if ($catalog?->hasPrice() && $unit === null) {
            $problem ??= 'Цена указана в '.$currency->value.', а курс к '.$shopCurrency->value.' не задан.';
        }

        return new CartLine(
            id: $element->id,
            element: $element,
            product: $product,
            name: $product ? $product->name.' — '.$element->name : $element->name,
            url: ($product ?? $element)->url(),
            image: $element->preview_picture_url ?? $element->detail_picture_url
                ?? $product?->preview_picture_url ?? $product?->detail_picture_url,
            quantity: $quantity,
            step: $step,
            max: $max,
            measure: $catalog?->measure ?? 'шт',
            basePrice: $base ?? 0.0,
            unitPrice: $unit ?? 0.0,
            originalPrice: $converted ? (float) $catalog->finalPrice() : null,
            originalCurrency: $converted ? $currency : null,
            converted: $converted,
            problem: $problem,
        );
    }

    /**
     * Кратно шагу, не меньше шага и не больше остатка.
     */
    public static function normalizeQuantity(float $quantity, float $step, ?float $max): float
    {
        $quantity = max($quantity, $step);
        $quantity = ceil(round($quantity / $step, 6)) * $step;

        if ($max !== null && $max > 0) {
            $quantity = min($quantity, $max);
        }

        return round($quantity, 3);
    }

    protected static function productOf(IblockElement $element): ?IblockElement
    {
        $parentId = $element->catalog?->parent_element_id;

        if (! $parentId || ! $element->iblock?->product_iblock_id) {
            return null;
        }

        return IblockElement::query()->with(['iblock', 'section'])->find($parentId);
    }

    protected static function isActiveNow(IblockElement $element): bool
    {
        return $element->is_active
            && ($element->active_from === null || $element->active_from->isPast())
            && ($element->active_to === null || $element->active_to->isFuture());
    }
}
