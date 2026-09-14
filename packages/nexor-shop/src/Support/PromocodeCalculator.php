<?php

namespace Nexor\Shop\Support;

use Illuminate\Support\Collection;
use Nexor\Cms\Models\IblockSection;
use Nexor\Shop\Enums\PromocodeScope;
use Nexor\Shop\Enums\PromocodeType;
use Nexor\Shop\Models\Promocode;

/**
 * Проверяет промокод на корзине и считает скидку.
 */
class PromocodeCalculator
{
    /**
     * Скидка по промокоду или причина, почему он не подходит.
     *
     * Скидка раскладывается по строкам, на которые промокод действует, — эти
     * доли потом попадают в строки заказа.
     *
     * @param  Collection<int, CartLine>  $lines
     * @return array{discount: float, error: string|null}
     */
    public static function apply(Promocode $promocode, Collection $lines, float $subtotal): array
    {
        if ($error = self::refusal($promocode, $subtotal)) {
            return ['discount' => 0.0, 'error' => $error];
        }

        $eligible = $lines->filter(fn (CartLine $line) => ! $line->hasProblem() && self::covers($promocode, $line));
        $eligibleSum = round($eligible->sum(fn (CartLine $line) => $line->sum()), 2);

        if ($eligibleSum <= 0) {
            return ['discount' => 0.0, 'error' => 'Промокод не действует на товары в корзине.'];
        }

        $discount = $promocode->type === PromocodeType::Percent
            ? round($eligibleSum * min((float) $promocode->value, 100) / 100, 2)
            : round(min((float) $promocode->value, $eligibleSum), 2);

        self::distribute($eligible->values(), $discount, $eligibleSum);

        return ['discount' => $discount, 'error' => null];
    }

    public static function refusal(Promocode $promocode, float $subtotal): ?string
    {
        if (! $promocode->is_active) {
            return 'Промокод не действует.';
        }

        if ($promocode->starts_at?->isFuture()) {
            return 'Промокод ещё не начал действовать.';
        }

        if ($promocode->ends_at?->isPast()) {
            return 'Срок действия промокода истёк.';
        }

        if ($promocode->isExhausted()) {
            return 'Промокод больше не действует.';
        }

        if ($promocode->min_sum !== null && $subtotal < (float) $promocode->min_sum) {
            return 'Промокод действует на заказ от '.Shop::format((float) $promocode->min_sum).'.';
        }

        return null;
    }

    protected static function covers(Promocode $promocode, CartLine $line): bool
    {
        $item = $line->catalogItem();

        return match ($promocode->scope) {
            PromocodeScope::All => true,
            PromocodeScope::Elements => in_array($line->id, self::ids($promocode->element_ids), true)
                || in_array($item->id, self::ids($promocode->element_ids), true),
            PromocodeScope::Sections => self::inSections($item->section, self::ids($promocode->section_ids)),
        };
    }

    /**
     * Товар в разделе или в любом его подразделе.
     *
     * @param  array<int, int>  $sectionIds
     */
    protected static function inSections(?IblockSection $section, array $sectionIds): bool
    {
        if (! $section || $sectionIds === []) {
            return false;
        }

        // Путь из id предков: `/1/7/` — раздел 12 внутри 7 внутри 1.
        $chain = array_map('intval', array_filter(explode('/', (string) $section->path)));
        $chain[] = $section->id;

        return array_intersect($chain, $sectionIds) !== [];
    }

    /**
     * @param  Collection<int, CartLine>  $lines
     */
    protected static function distribute(Collection $lines, float $discount, float $eligibleSum): void
    {
        $left = $discount;

        foreach ($lines as $index => $line) {
            $share = $index === $lines->count() - 1
                ? $left
                : round($discount * $line->sum() / $eligibleSum, 2);

            $line->discount = $share;
            $left = round($left - $share, 2);
        }
    }

    /**
     * @return array<int, int>
     */
    protected static function ids(?array $ids): array
    {
        return array_values(array_map('intval', $ids ?? []));
    }
}
