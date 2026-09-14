<?php

namespace Tests\Feature\Shop;

use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Cookie;
use Livewire\Livewire;
use Nexor\Cms\Enums\Currency;
use Nexor\Cms\Models\CatalogProduct;
use Nexor\Cms\Models\IblockElement;
use Nexor\Cms\Support\Nexor;
use Nexor\Shop\Enums\CartEdition;
use Nexor\Shop\Livewire\AddToCart;
use Nexor\Shop\Livewire\CartPage;
use Nexor\Shop\Support\Cart;
use Nexor\Shop\Support\CartException;
use Nexor\Shop\Support\Shop;
use Tests\Concerns\BuildsShop;
use Tests\TestCase;

/**
 * Корзина: что можно положить, почём и сколько.
 */
class CartTest extends TestCase
{
    use BuildsShop, RefreshDatabase;

    // ----------------------------------------------------------------- Basic

    public function test_a_product_with_a_price_goes_into_the_cart(): void
    {
        $product = $this->product(['price' => 1500]);
        $cart = $this->emptyCart();

        $cart->add($product->id, 2);

        $summary = $cart->summary();

        $this->assertSame(1, $summary->count());
        $this->assertSame(3000.0, $summary->total());
    }

    public function test_the_catalog_discount_is_already_in_the_price(): void
    {
        $product = $this->product(['price' => 1000, 'discount_percent' => 20]);
        $cart = $this->emptyCart();

        $cart->add($product->id);

        $line = $cart->lines()->first();

        $this->assertSame(1000.0, $line->basePrice);
        $this->assertSame(800.0, $line->unitPrice);
    }

    public function test_a_product_without_a_price_is_refused(): void
    {
        $product = $this->product(['price' => null]);

        $this->expectException(CartException::class);

        $this->emptyCart()->add($product->id);
    }

    public function test_an_inactive_product_is_refused(): void
    {
        $product = $this->product([], ['is_active' => false]);

        $this->expectException(CartException::class);

        $this->emptyCart()->add($product->id);
    }

    public function test_basic_ignores_stock(): void
    {
        $product = $this->product(['quantity' => 0, 'quantity_trace' => true]);
        $cart = $this->emptyCart();

        $cart->add($product->id, 5);

        $this->assertSame(5.0, $cart->quantity($product->id));
        $this->assertFalse($cart->summary()->hasProblems());
    }

    public function test_basic_sells_whole_pieces(): void
    {
        $product = $this->product(['ratio' => 0.5]);
        $cart = $this->emptyCart();

        $cart->add($product->id, 1.2);

        $this->assertSame(2.0, $cart->quantity($product->id));
    }

    // -------------------------------------------------------------- Ultimate

    public function test_ultimate_does_not_sell_more_than_there_is(): void
    {
        $this->ultimate();

        $product = $this->product(['quantity' => 3, 'quantity_trace' => true]);
        $cart = $this->emptyCart();

        $cart->add($product->id, 10);

        $this->assertSame(3.0, $cart->quantity($product->id));
    }

    public function test_ultimate_refuses_a_product_that_ran_out(): void
    {
        $this->ultimate();

        $product = $this->product(['quantity' => 0, 'quantity_trace' => true]);

        $this->expectExceptionMessage('Нет в наличии.');

        $this->emptyCart()->add($product->id);
    }

    public function test_ultimate_sells_out_of_stock_items_when_backorders_are_allowed(): void
    {
        $this->ultimate();

        $product = $this->product(['quantity' => 0, 'quantity_trace' => true, 'can_buy_zero' => true]);
        $cart = $this->emptyCart();

        $cart->add($product->id, 4);

        $this->assertSame(4.0, $cart->quantity($product->id));
    }

    public function test_ultimate_sells_in_steps_of_the_ratio(): void
    {
        $this->ultimate();

        $product = $this->product(['ratio' => 0.5, 'measure' => 'кг']);
        $cart = $this->emptyCart();

        $cart->add($product->id, 1.2);

        $this->assertSame(1.5, $cart->quantity($product->id));
    }

    public function test_a_lite_site_works_as_basic_even_if_ultimate_was_chosen(): void
    {
        $this->shopSettings(['edition' => 'ultimate']);

        config(['nexor.license' => 'lite']);

        $this->assertSame(CartEdition::Basic, Shop::edition());
    }

    // ---------------------------------------------------------------- валюта

    public function test_a_price_in_another_currency_is_converted_and_marked(): void
    {
        $this->shopSettings(['currency' => 'RUB', 'rates' => ['USD' => 90], 'rates_base' => 'RUB']);

        $product = $this->product(['price' => 10, 'currency' => 'USD']);
        $cart = $this->emptyCart();

        $cart->add($product->id);

        $line = $cart->lines()->first();

        $this->assertTrue($line->converted);
        $this->assertSame(900.0, $line->unitPrice);
        $this->assertSame(10.0, $line->originalPrice);
        $this->assertTrue($cart->summary()->hasConverted());
    }

    public function test_a_price_without_a_rate_cannot_be_bought(): void
    {
        $this->shopSettings(['currency' => 'RUB', 'rates' => []]);

        $product = $this->product(['price' => 10, 'currency' => 'EUR']);

        $this->expectExceptionMessage('курс');

        $this->emptyCart()->add($product->id);
    }

    public function test_rates_entered_for_another_shop_currency_are_not_trusted(): void
    {
        // Курс «1 USD = 90» вводили к рублю, а магазин теперь в тенге.
        $this->shopSettings(['currency' => 'KZT', 'rates' => ['USD' => 90], 'rates_base' => 'RUB']);

        $this->assertNull(Shop::rate(Currency::USD));
    }

    // ------------------------------------------------------ предложения и cookie

    public function test_a_product_with_offers_needs_an_offer(): void
    {
        $this->offersIblock();

        $product = $this->product(['type' => 'with_offers']);

        $this->expectExceptionMessage('Выберите вариант товара.');

        $this->emptyCart()->add($product->id);
    }

    public function test_an_offer_is_bought_under_its_product_name(): void
    {
        $offers = $this->offersIblock();

        $product = $this->product(['type' => 'with_offers', 'price' => null], ['name' => 'Футболка']);
        $offer = IblockElement::factory()->create(['iblock_id' => $offers->id, 'name' => 'Размер M', 'is_active' => true]);
        CatalogProduct::factory()->create(['element_id' => $offer->id, 'parent_element_id' => $product->id, 'price' => 1990]);

        $cart = $this->emptyCart();
        $cart->add($offer->id);

        $this->assertSame('Футболка — Размер M', $cart->lines()->first()->name);
    }

    public function test_the_cart_is_read_back_from_its_cookie(): void
    {
        $product = $this->product(['price' => 500]);

        $request = Request::create('/', cookies: $this->cartCookie([$product->id => 3]));
        $cart = new Cart($request);

        $this->assertSame(1500.0, $cart->summary()->total());
    }

    public function test_a_forged_cookie_cannot_change_the_price(): void
    {
        $product = $this->product(['price' => 500]);

        $request = Request::create('/', cookies: [
            Cart::cookieName() => json_encode(['items' => [$product->id => 1], 'price' => 1]),
        ]);

        $this->assertSame(500.0, (new Cart($request))->summary()->total());
    }

    public function test_the_add_button_stores_the_cart_in_a_cookie(): void
    {
        $product = $this->product();

        Livewire::test(AddToCart::class, ['elementId' => $product->id])
            ->call('add')
            ->assertSet('error', null)
            ->assertDispatched('cart-updated');

        $this->assertTrue(Cookie::hasQueued(Cart::cookieName()));
    }

    public function test_the_add_button_explains_a_refusal(): void
    {
        $product = $this->product(['price' => null]);

        Livewire::test(AddToCart::class, ['elementId' => $product->id])
            ->call('add')
            ->assertSet('error', 'У товара не указана цена.');
    }

    public function test_the_cart_page_lists_what_is_in_the_cookie(): void
    {
        $product = $this->product(['price' => 700], ['name' => 'Табурет']);

        Livewire::withCookies($this->cartCookie([$product->id => 2]))
            ->test(CartPage::class)
            ->assertSee('Табурет')
            ->assertSee('1 400 ₽');
    }

    public function test_the_cart_page_is_off_with_the_module(): void
    {
        Nexor::modules()->setEnabled('shop', false);

        $this->get('/cart')->assertNotFound();
    }
}
