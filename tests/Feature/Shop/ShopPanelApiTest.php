<?php

namespace Tests\Feature\Shop;

use Illuminate\Foundation\Testing\RefreshDatabase;
use Nexor\Cms\Enums\Currency;
use Nexor\Cms\Support\Nexor;
use Nexor\Cms\Support\Permissions;
use Nexor\Shop\Enums\OrderStatus;
use Nexor\Shop\Models\DeliveryMethod;
use Nexor\Shop\Models\Order;
use Nexor\Shop\Models\OrderField;
use Nexor\Shop\Models\Promocode;
use Nexor\Shop\Support\Shop;
use Tests\Concerns\BuildsShop;
use Tests\Concerns\CreatesAdminUsers;
use Tests\TestCase;

/**
 * Страницы магазина в панели: корзина, поля заказа, заказы, промокоды, доставка.
 */
class ShopPanelApiTest extends TestCase
{
    use BuildsShop, CreatesAdminUsers, RefreshDatabase;

    // ---------------------------------------------------------------- корзина

    public function test_the_cart_settings_describe_what_the_license_allows(): void
    {
        config(['nexor.license' => 'lite']);

        $response = $this->actingAs($this->adminWith(['shop.cart.view']))
            ->getJson('/admin/api/shop/settings')
            ->assertOk()
            ->assertJsonPath('settings.edition', 'basic');

        $editions = collect($response->json('editions'))->keyBy('value');

        $this->assertTrue($editions['basic']['available']);
        $this->assertFalse($editions['ultimate']['available']);
    }

    public function test_ultimate_cannot_be_saved_on_lite(): void
    {
        config(['nexor.license' => 'lite']);

        $this->actingAs($this->adminWith(['shop.cart.update']))
            ->putJson('/admin/api/shop/settings', [
                'edition' => 'ultimate',
                'display' => 'page',
                'currency' => 'RUB',
            ])->assertStatus(422)->assertJsonValidationErrors('edition');
    }

    public function test_settings_save_the_currency_with_its_rates(): void
    {
        $this->actingAs($this->adminWith(['shop.cart.update']))
            ->putJson('/admin/api/shop/settings', [
                'edition' => 'ultimate',
                'display' => 'page',
                'currency' => 'KZT',
                'rates' => ['RUB' => 5.6, 'USD' => 480, 'KZT' => 1],
                'notify_admin' => true,
                'admin_email' => 'shop@example.com',
                'notify_customer' => false,
            ])->assertOk()->assertJsonPath('effective_edition', 'ultimate');

        $settings = Shop::settings();

        $this->assertSame('KZT', $settings['rates_base']);
        // Курс валюты к самой себе не хранится.
        $this->assertEquals(['RUB' => 5.6, 'USD' => 480], $settings['rates']);
        $this->assertSame(480.0, Shop::rate(Currency::USD));
    }

    public function test_cart_settings_need_their_permission(): void
    {
        $this->actingAs($this->adminWith(['shop.cart.view']))
            ->putJson('/admin/api/shop/settings', ['edition' => 'basic', 'display' => 'page', 'currency' => 'RUB'])
            ->assertForbidden();
    }

    public function test_the_shop_api_disappears_with_the_module(): void
    {
        Nexor::modules()->setEnabled('shop', false);

        $this->actingAs($this->superAdmin())->getJson('/admin/api/shop/settings')->assertNotFound();
    }

    // ------------------------------------------------------------ поля заказа

    public function test_order_fields_can_be_added_and_removed(): void
    {
        $admin = $this->adminWith(['shop.cart.view', 'shop.cart.update']);

        $id = $this->actingAs($admin)->postJson('/admin/api/shop/order-fields', [
            'code' => 'city',
            'name' => 'Город',
            'type' => 'text',
            'is_required' => true,
            'is_active' => true,
        ])->assertCreated()->assertJsonPath('data.code', 'CITY')->json('data.id');

        $this->actingAs($admin)->deleteJson("/admin/api/shop/order-fields/{$id}")->assertOk();

        $this->assertNull(OrderField::query()->find($id));
    }

    public function test_order_fields_can_be_reordered(): void
    {
        $admin = $this->adminWith(['shop.cart.update']);
        $ids = OrderField::query()->ordered()->pluck('id')->reverse()->values()->all();

        $this->actingAs($admin)->putJson('/admin/api/shop/order-fields/sort', ['ids' => $ids])->assertOk();

        $this->assertSame($ids, OrderField::query()->ordered()->pluck('id')->all());
    }

    // ------------------------------------------------------------------ заказы

    public function test_orders_are_listed_and_their_status_changes(): void
    {
        $order = Order::factory()->create();
        $admin = $this->adminWith(['shop.orders.view', 'shop.orders.update']);

        $this->actingAs($admin)->getJson('/admin/api/shop/orders')
            ->assertOk()
            ->assertJsonPath('data.0.number', $order->fresh()->number);

        $this->actingAs($admin)->putJson("/admin/api/shop/orders/{$order->id}", [
            'status' => 'completed',
            'manager_comment' => 'Отгружено',
        ])->assertOk();

        $this->assertSame(OrderStatus::Completed, $order->refresh()->status);
    }

    public function test_a_lite_order_has_no_delivery_or_payment(): void
    {
        config(['nexor.license' => 'lite']);

        $order = Order::factory()->create(['delivery_name' => 'Курьер', 'payment_name' => 'Картой']);

        $data = $this->actingAs($this->adminWith(['shop.orders.view']))
            ->getJson("/admin/api/shop/orders/{$order->id}")
            ->assertOk()
            ->json('data');

        $this->assertArrayNotHasKey('delivery', $data);
        $this->assertArrayNotHasKey('payment', $data);
    }

    // --------------------------------------------------------------- промокоды

    public function test_a_promocode_is_created_in_upper_case(): void
    {
        $this->actingAs($this->adminWith(['shop.promocodes.create']))
            ->postJson('/admin/api/shop/promocodes', [
                'code' => 'summer-24',
                'type' => 'percent',
                'value' => 15,
                'scope' => 'all',
                'is_active' => true,
            ])->assertCreated()->assertJsonPath('data.code', 'SUMMER-24');
    }

    public function test_a_percent_above_one_hundred_is_refused(): void
    {
        $this->actingAs($this->adminWith(['shop.promocodes.create']))
            ->postJson('/admin/api/shop/promocodes', [
                'code' => 'TOO',
                'type' => 'percent',
                'value' => 150,
                'scope' => 'all',
            ])->assertStatus(422)->assertJsonValidationErrors('value');
    }

    public function test_a_section_promocode_needs_sections(): void
    {
        $this->actingAs($this->adminWith(['shop.promocodes.create']))
            ->postJson('/admin/api/shop/promocodes', [
                'code' => 'MEBEL',
                'type' => 'percent',
                'value' => 10,
                'scope' => 'sections',
            ])->assertStatus(422)->assertJsonValidationErrors('section_ids');
    }

    public function test_promocodes_are_closed_on_lite(): void
    {
        config(['nexor.license' => 'lite']);

        Promocode::factory()->create();

        $this->actingAs($this->superAdmin())->getJson('/admin/api/shop/promocodes')->assertNotFound();
    }

    // ------------------------------------------------------- доставка и оплата

    public function test_delivery_methods_are_managed_from_the_panel(): void
    {
        $admin = $this->adminWith(['shop.checkout.view', 'shop.checkout.update']);

        $id = $this->actingAs($admin)->postJson('/admin/api/shop/delivery-methods', [
            'name' => 'Самовывоз',
            'price' => 0,
            'is_active' => true,
        ])->assertCreated()->json('data.id');

        $this->actingAs($admin)->putJson("/admin/api/shop/delivery-methods/{$id}", [
            'name' => 'Самовывоз со склада',
            'price' => 0,
            'free_from' => null,
        ])->assertOk();

        $this->assertSame('Самовывоз со склада', DeliveryMethod::query()->find($id)->name);
    }

    public function test_delivery_methods_are_closed_on_lite(): void
    {
        config(['nexor.license' => 'lite']);

        $this->actingAs($this->superAdmin())->getJson('/admin/api/shop/delivery-methods')->assertNotFound();
    }

    public function test_the_shop_adds_its_permissions_to_the_catalogue(): void
    {
        $this->assertArrayHasKey('shop_orders', Permissions::definitions());
    }
}
