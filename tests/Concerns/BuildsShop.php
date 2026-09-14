<?php

namespace Tests\Concerns;

use Illuminate\Http\Request;
use Nexor\Cms\Models\CatalogProduct;
use Nexor\Cms\Models\Iblock;
use Nexor\Cms\Models\IblockElement;
use Nexor\Cms\Support\CatalogManager;
use Nexor\Cms\Support\Nexor;
use Nexor\Shop\Support\Cart;
use Nexor\Shop\Support\Shop;

/**
 * Каталог с ценами и корзина для тестов магазина.
 */
trait BuildsShop
{
    protected ?Iblock $shopCatalog = null;

    protected function catalogIblock(): Iblock
    {
        return $this->shopCatalog ??= Iblock::factory()->create([
            'code' => 'katalog',
            'is_catalog' => true,
            'is_active' => true,
        ]);
    }

    /**
     * Инфоблок предложений каталога — тем же механизмом, что и панель.
     */
    protected function offersIblock(): Iblock
    {
        return CatalogManager::sync($this->catalogIblock()->refresh());
    }

    /**
     * Товар с ценой: `product(['price' => 1000, 'currency' => 'USD'])`.
     *
     * @param  array<string, mixed>  $catalog
     * @param  array<string, mixed>  $element
     */
    protected function product(array $catalog = [], array $element = []): IblockElement
    {
        $product = IblockElement::factory()->for($this->catalogIblock())->create($element + [
            'name' => 'Стул',
            'is_active' => true,
        ]);

        CatalogProduct::factory()->create($catalog + [
            'element_id' => $product->id,
            'price' => 1000,
            'discount_percent' => 0,
            'quantity' => 10,
            'quantity_trace' => false,
        ]);

        return $product->fresh(['iblock', 'catalog']);
    }

    /**
     * @param  array<string, mixed>  $settings
     */
    protected function shopSettings(array $settings): void
    {
        Nexor::modules()->updateSettings(Shop::MODULE, $settings);
    }

    protected function ultimate(): void
    {
        config(['nexor.license' => 'pro']);

        $this->shopSettings(['edition' => 'ultimate']);
    }

    protected function emptyCart(): Cart
    {
        return new Cart(Request::create('/'));
    }

    /**
     * Содержимое cookie корзины — для Livewire::withCookies().
     *
     * @param  array<int, float>  $items
     * @return array<string, string>
     */
    protected function cartCookie(array $items, ?string $promocode = null): array
    {
        return [Cart::cookieName() => json_encode(['items' => $items, 'promocode' => $promocode])];
    }
}
