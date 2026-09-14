<?php

namespace Nexor\Shop\Support;

use Illuminate\Http\Request;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\Cookie;
use Nexor\Cms\Models\IblockElement;
use Nexor\Shop\Models\Promocode;

/**
 * Корзина гостя в cookie.
 *
 * В cookie лежат только id элементов, количества и введённый промокод: цены,
 * названия и остатки каждый раз берутся из базы, поэтому подделать сумму,
 * переписав cookie, нельзя. Cookie шифруется middleware `web`.
 */
class Cart
{
    /** @var array<int, float> id элемента → количество */
    protected array $items = [];

    protected ?string $promocode = null;

    /** @var Collection<int, CartLine>|null */
    protected ?Collection $lines = null;

    public function __construct(Request $request)
    {
        $payload = json_decode((string) $request->cookie(self::cookieName()), true);

        if (! is_array($payload)) {
            return;
        }

        foreach ((array) ($payload['items'] ?? []) as $id => $quantity) {
            if ((int) $id > 0 && is_numeric($quantity) && $quantity > 0) {
                $this->items[(int) $id] = (float) $quantity;
            }
        }

        $this->items = array_slice($this->items, 0, self::maxLines(), true);
        $this->promocode = is_string($payload['promocode'] ?? null) ? $payload['promocode'] : null;
    }

    public static function current(): self
    {
        return app(self::class);
    }

    public static function cookieName(): string
    {
        return (string) config('nexor-shop.cookie.name', 'nexor_cart');
    }

    protected static function maxLines(): int
    {
        return (int) config('nexor-shop.max_lines', 50);
    }

    /**
     * @return array<int, float>
     */
    public function items(): array
    {
        return $this->items;
    }

    public function has(int $elementId): bool
    {
        return isset($this->items[$elementId]);
    }

    public function quantity(int $elementId): float
    {
        return $this->items[$elementId] ?? 0.0;
    }

    /**
     * Кладёт товар: к тому, что уже лежит, добавляется количество.
     *
     * @throws CartException
     */
    public function add(int $elementId, float $quantity = 1): CartLine
    {
        $element = IblockElement::query()->with(['iblock', 'catalog', 'section'])->find($elementId);

        if (! $element) {
            throw new CartException('Товар не найден.');
        }

        if ($refusal = CartPricing::refusal($element)) {
            throw new CartException($refusal);
        }

        if (! $this->has($elementId) && count($this->items) >= self::maxLines()) {
            throw new CartException('В корзине слишком много товаров.');
        }

        $line = CartPricing::line($element, $this->quantity($elementId) + max($quantity, 0));

        if ($line->hasProblem()) {
            throw new CartException($line->problem);
        }

        if ($line->max !== null && $this->quantity($elementId) >= $line->max) {
            throw new CartException('Больше этого товара нет в наличии.');
        }

        $this->items[$elementId] = $line->quantity;
        $this->lines = null;

        return $line;
    }

    /**
     * Ставит количество; ноль и меньше убирают строку.
     */
    public function setQuantity(int $elementId, float $quantity): void
    {
        if (! $this->has($elementId)) {
            return;
        }

        if ($quantity <= 0) {
            $this->remove($elementId);

            return;
        }

        $this->items[$elementId] = $quantity;
        $this->lines = null;
    }

    public function remove(int $elementId): void
    {
        unset($this->items[$elementId]);
        $this->lines = null;
    }

    public function clear(): void
    {
        $this->items = [];
        $this->promocode = null;
        $this->lines = null;
    }

    public function promocode(): ?string
    {
        return $this->promocode;
    }

    public function setPromocode(?string $code): void
    {
        $this->promocode = filled($code) ? Promocode::normalize($code) : null;
    }

    /**
     * Строки с актуальными ценами. Количество заодно приводится к шагу и
     * остатку — чтобы cookie не держала больше, чем можно купить.
     *
     * @return Collection<int, CartLine>
     */
    public function lines(): Collection
    {
        if ($this->lines !== null) {
            return $this->lines;
        }

        $elements = IblockElement::query()
            ->with(['iblock', 'catalog', 'section'])
            ->whereKey(array_keys($this->items))
            ->get()
            ->keyBy('id');

        $lines = collect();

        foreach ($this->items as $id => $quantity) {
            $element = $elements->get($id);

            // Товар удалили из каталога — из корзины он уходит молча.
            if (! $element) {
                unset($this->items[$id]);

                continue;
            }

            $line = CartPricing::line($element, $quantity);
            $this->items[$id] = $line->quantity;
            $lines->push($line);
        }

        return $this->lines = $lines;
    }

    public function summary(): CartSummary
    {
        $lines = $this->lines();
        $lines->each(fn (CartLine $line) => $line->discount = 0.0);

        $subtotal = round($lines->sum(fn (CartLine $line) => $line->sum()), 2);

        $promocode = null;
        $discount = 0.0;
        $error = null;

        if ($this->promocode && Shop::promocodesEnabled()) {
            $promocode = Promocode::findByCode($this->promocode);

            if (! $promocode) {
                $error = 'Такого промокода нет.';
            } else {
                ['discount' => $discount, 'error' => $error] = PromocodeCalculator::apply($promocode, $lines, $subtotal);

                if ($error) {
                    $promocode = null;
                }
            }
        }

        return new CartSummary(
            lines: $lines,
            currency: Shop::currency(),
            subtotal: $subtotal,
            discount: $discount,
            promocode: $promocode,
            promocodeCode: Shop::promocodesEnabled() ? $this->promocode : null,
            promocodeError: $error,
        );
    }

    /**
     * Ставит cookie в ответ текущего запроса — обычного или запроса Livewire.
     */
    public function save(): void
    {
        if ($this->items === [] && $this->promocode === null) {
            Cookie::queue(Cookie::forget(self::cookieName()));

            return;
        }

        Cookie::queue(
            self::cookieName(),
            json_encode(['items' => $this->items, 'promocode' => $this->promocode]),
            (int) config('nexor-shop.cookie.minutes', 43200),
        );
    }
}
