<?php

namespace Tests\Feature\Shop;

use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Mail;
use Livewire\Livewire;
use Nexor\Cms\Mail\FormMessage;
use Nexor\Shop\Livewire\CartPage;
use Nexor\Shop\Livewire\Checkout;
use Nexor\Shop\Models\DeliveryMethod;
use Nexor\Shop\Models\Order;
use Nexor\Shop\Models\OrderField;
use Nexor\Shop\Models\PaymentMethod;
use Nexor\Shop\Models\Promocode;
use Tests\Concerns\BuildsShop;
use Tests\TestCase;

/**
 * Заказ: из корзины Basic и через оформление Ultimate.
 */
class OrderTest extends TestCase
{
    use BuildsShop, RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();

        Mail::fake();
    }

    // ----------------------------------------------------------------- Basic

    public function test_a_basic_cart_turns_into_an_order(): void
    {
        $this->shopSettings(['admin_email' => 'shop@example.com']);

        $product = $this->product(['price' => 1200], ['name' => 'Кресло']);

        Livewire::withCookies($this->cartCookie([$product->id => 2]))
            ->test(CartPage::class)
            ->set('customer.NAME', 'Иван Петров')
            ->set('customer.PHONE', '+7 900 123-45-67')
            ->set('customer.EMAIL', 'ivan@example.com')
            ->call('placeOrder')
            ->assertHasNoErrors()
            ->assertSet('placedNumber', '000001');

        $order = Order::query()->with('items')->sole();

        $this->assertSame('2400.00', $order->total);
        $this->assertSame('Иван Петров', $order->customerValue('NAME'));
        $this->assertNull($order->delivery_name);
        $this->assertSame('Кресло', $order->items->first()->name);

        Mail::assertSent(FormMessage::class, fn (FormMessage $mail) => $mail->hasTo('shop@example.com'));
        Mail::assertSent(FormMessage::class, fn (FormMessage $mail) => $mail->hasTo('ivan@example.com'));
    }

    public function test_required_fields_are_checked(): void
    {
        $product = $this->product();

        Livewire::withCookies($this->cartCookie([$product->id => 1]))
            ->test(CartPage::class)
            ->call('placeOrder')
            ->assertHasErrors(['customer.NAME', 'customer.PHONE']);

        $this->assertSame(0, Order::query()->count());
    }

    public function test_a_field_added_in_the_panel_joins_the_form(): void
    {
        OrderField::factory()->required()->create(['code' => 'CITY', 'name' => 'Город']);

        $product = $this->product();

        Livewire::withCookies($this->cartCookie([$product->id => 1]))
            ->test(CartPage::class)
            ->assertSee('Город')
            ->set('customer.NAME', 'Иван')
            ->set('customer.PHONE', '+7 900 123-45-67')
            ->call('placeOrder')
            ->assertHasErrors(['customer.CITY']);
    }

    public function test_an_empty_cart_is_not_ordered(): void
    {
        Livewire::test(CartPage::class)
            ->set('customer.NAME', 'Иван')
            ->set('customer.PHONE', '+7 900 123-45-67')
            ->call('placeOrder')
            ->assertHasErrors(['cart']);
    }

    public function test_the_order_keeps_its_price_after_the_catalog_changes(): void
    {
        $product = $this->product(['price' => 1000]);

        Livewire::withCookies($this->cartCookie([$product->id => 1]))
            ->test(CartPage::class)
            ->set('customer.NAME', 'Иван')
            ->set('customer.PHONE', '+7 900 123-45-67')
            ->call('placeOrder');

        $product->catalog->update(['price' => 5000]);

        $this->assertSame('1000.00', Order::query()->sole()->items->first()->price);
    }

    // -------------------------------------------------------------- Ultimate

    public function test_checkout_adds_delivery_payment_and_the_promocode(): void
    {
        $this->ultimate();

        $product = $this->product(['price' => 2000, 'quantity' => 5, 'quantity_trace' => true]);
        $delivery = DeliveryMethod::factory()->create(['name' => 'Курьер', 'price' => 300]);
        $payment = PaymentMethod::factory()->create(['name' => 'Картой курьеру']);
        $promocode = Promocode::factory()->percent(10)->create(['code' => 'SALE10']);

        Livewire::withCookies($this->cartCookie([$product->id => 2], 'SALE10'))
            ->test(Checkout::class)
            ->set('customer.NAME', 'Иван')
            ->set('customer.PHONE', '+7 900 123-45-67')
            ->set('deliveryId', $delivery->id)
            ->set('paymentId', $payment->id)
            ->call('placeOrder')
            ->assertHasNoErrors();

        $order = Order::query()->sole();

        $this->assertSame('4000.00', $order->subtotal);
        $this->assertSame('400.00', $order->discount);
        $this->assertSame('300.00', $order->delivery_price);
        $this->assertSame('3900.00', $order->total);
        $this->assertSame('Курьер', $order->delivery_name);
        $this->assertSame('Картой курьеру', $order->payment_name);
        $this->assertSame('SALE10', $order->promocode_code);

        // Ultimate списывает остаток и считает использование промокода.
        $this->assertSame('3.000', $product->catalog->refresh()->quantity);
        $this->assertSame(1, $promocode->refresh()->used_count);
    }

    public function test_delivery_is_free_from_its_threshold(): void
    {
        $this->ultimate();

        $product = $this->product(['price' => 6000]);
        $delivery = DeliveryMethod::factory()->create(['price' => 300, 'free_from' => 5000]);

        Livewire::withCookies($this->cartCookie([$product->id => 1]))
            ->test(Checkout::class)
            ->set('customer.NAME', 'Иван')
            ->set('customer.PHONE', '+7 900 123-45-67')
            ->set('deliveryId', $delivery->id)
            ->call('placeOrder')
            ->assertHasNoErrors();

        $this->assertSame('0.00', Order::query()->sole()->delivery_price);
    }

    public function test_a_delivery_method_must_be_chosen_when_there_are_any(): void
    {
        $this->ultimate();

        $product = $this->product();
        DeliveryMethod::factory()->create();

        Livewire::withCookies($this->cartCookie([$product->id => 1]))
            ->test(Checkout::class)
            ->set('customer.NAME', 'Иван')
            ->set('customer.PHONE', '+7 900 123-45-67')
            ->set('deliveryId', null)
            ->call('placeOrder')
            ->assertHasErrors(['delivery']);
    }

    public function test_the_last_items_cannot_be_sold_twice(): void
    {
        $this->ultimate();

        $product = $this->product(['quantity' => 1, 'quantity_trace' => true]);

        // Кто-то успел купить последний, пока форма была открыта.
        $cookie = $this->cartCookie([$product->id => 1]);
        $product->catalog->update(['quantity' => 0]);

        Livewire::withCookies($cookie)
            ->test(Checkout::class)
            ->set('customer.NAME', 'Иван')
            ->set('customer.PHONE', '+7 900 123-45-67')
            ->call('placeOrder')
            ->assertHasErrors(['cart']);

        $this->assertSame(0, Order::query()->count());
    }

    public function test_checkout_does_not_exist_for_a_basic_cart(): void
    {
        $this->get('/checkout')->assertNotFound();
    }

    public function test_checkout_does_not_exist_on_lite(): void
    {
        $this->ultimate();

        config(['nexor.license' => 'lite']);

        $this->get('/checkout')->assertNotFound();
    }
}
