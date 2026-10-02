<?php

namespace Tests\Feature\Shop;

use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Http;
use Nexor\Shop\Enums\DeliveryState;
use Nexor\Shop\Models\Order;
use Nexor\Shop\Models\OrderEvent;
use Nexor\Shop\Support\Delivery\Tracking;
use Tests\Concerns\BuildsCdekMethods;
use Tests\Concerns\BuildsShop;
use Tests\Concerns\CreatesAdminUsers;
use Tests\TestCase;

/**
 * Слежение за посылками по расписанию и попапы о них в панели.
 *
 * Раньше статус обновлялся, только когда заказ открывали, и только пока не
 * было трек-номера. Теперь его проверяет команда, а смену статуса панель
 * показывает тому, кто зайдёт, — один раз.
 */
class DeliveryTrackingTest extends TestCase
{
    use BuildsCdekMethods, BuildsShop, CreatesAdminUsers, RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();

        $this->ultimate();
    }

    /**
     * Заказ, уже переданный в СДЭК, с последним известным статусом.
     *
     * @param  array<string, mixed>  $attributes
     */
    protected function shipped(array $attributes = []): Order
    {
        $order = Order::factory()->create(['delivery_method_id' => $this->cdekMethod()->id]);

        $order->forceFill($attributes + [
            'delivery_state' => DeliveryState::Registered,
            'delivery_request_id' => 'uuid-1',
            'delivery_track' => '1105223344',
            'delivery_status' => 'Принят на склад отправителя',
        ])->save();

        return $order->refresh();
    }

    /**
     * СДЭК отвечает, что последний статус посылки — этот.
     */
    protected function cdekSays(string $code, string $name): void
    {
        Http::fake([
            '*/oauth/token' => Http::response(['access_token' => 'token', 'expires_in' => 3600]),
            '*/v2/orders/uuid-1' => Http::response(['entity' => [
                'uuid' => 'uuid-1',
                'cdek_number' => '1105223344',
                'statuses' => [
                    ['code' => 'RECEIVED_AT_SHIPMENT_WAREHOUSE', 'name' => 'Принят на склад отправителя'],
                    ['code' => $code, 'name' => $name],
                ],
            ]]),
        ]);
    }

    // -------------------------------------------------------------- расписание

    public function test_a_new_status_is_noticed_by_the_schedule(): void
    {
        $order = $this->shipped();
        $this->cdekSays('DELIVERED', 'Вручен');

        $this->artisan('shop:shipments:sync')->assertSuccessful();

        $event = OrderEvent::query()->sole();

        $this->assertSame($order->id, $event->order_id);
        $this->assertSame('Принят на склад отправителя', $event->previous);
        $this->assertSame('Вручен', $event->current);
        $this->assertSame('DELIVERED', $order->refresh()->delivery_status_code);
    }

    public function test_the_same_status_makes_no_noise(): void
    {
        $this->shipped();
        $this->cdekSays('RECEIVED_AT_SHIPMENT_WAREHOUSE', 'Принят на склад отправителя');

        $this->artisan('shop:shipments:sync')->assertSuccessful();

        $this->assertSame(0, OrderEvent::query()->count());
    }

    public function test_a_delivered_parcel_is_left_alone(): void
    {
        $this->shipped(['delivery_status_code' => 'DELIVERED', 'delivery_status' => 'Вручен']);

        // Дальше статус не поменяется — спрашивать незачем.
        $this->assertCount(0, Tracking::due());
    }

    public function test_an_old_order_is_not_followed_forever(): void
    {
        $order = $this->shipped();
        $order->forceFill(['created_at' => now()->subDays(Tracking::DAYS + 1)])->save();

        $this->assertCount(0, Tracking::due());
    }

    public function test_a_parcel_on_its_way_back_is_still_followed(): void
    {
        // Не вручен — значит, начнётся возврат со своими статусами.
        $this->shipped(['delivery_status_code' => 'NOT_DELIVERED']);

        $this->assertCount(1, Tracking::due());
    }

    public function test_the_command_is_on_the_schedule(): void
    {
        $this->artisan('schedule:list')->expectsOutputToContain('shop:shipments:sync')->assertSuccessful();
    }

    // ------------------------------------------------------------------ попапы

    public function test_the_panel_shows_each_change_once(): void
    {
        $order = $this->shipped();
        OrderEvent::query()->create(['order_id' => $order->id, 'type' => OrderEvent::DELIVERY, 'previous' => 'В пути', 'current' => 'Вручен']);

        $admin = $this->adminWith(['shop.orders.view']);

        $data = $this->actingAs($admin)->getJson('/admin/api/shop/notices')
            ->assertOk()
            ->assertJsonPath('data.0.number', $order->number)
            ->assertJsonPath('data.0.current', 'Вручен')
            ->assertJsonPath('more', 0)
            ->json();

        $this->actingAs($admin)->postJson('/admin/api/shop/notices/seen', ['id' => $data['last_id']])->assertOk();

        // Увиденное больше не всплывает — и после перезагрузки тоже.
        $this->actingAs($admin)->getJson('/admin/api/shop/notices')->assertOk()->assertJsonCount(0, 'data');
    }

    public function test_one_person_seeing_does_not_hide_it_from_another(): void
    {
        $order = $this->shipped();
        $event = OrderEvent::query()->create(['order_id' => $order->id, 'type' => OrderEvent::DELIVERY, 'current' => 'Вручен']);

        $first = $this->adminWith(['shop.orders.view']);
        $second = $this->adminWith(['shop.orders.view']);

        $this->actingAs($first)->postJson('/admin/api/shop/notices/seen', ['id' => $event->id])->assertOk();

        $this->actingAs($second)->getJson('/admin/api/shop/notices')->assertOk()->assertJsonCount(1, 'data');
    }

    public function test_a_newcomer_does_not_get_the_whole_history(): void
    {
        $order = $this->shipped();
        $old = OrderEvent::query()->create(['order_id' => $order->id, 'type' => OrderEvent::DELIVERY, 'current' => 'В пути']);
        $old->forceFill(['created_at' => now()->subDays(10)])->save();

        $this->actingAs($this->adminWith(['shop.orders.view']))
            ->getJson('/admin/api/shop/notices')
            ->assertOk()
            ->assertJsonCount(0, 'data');
    }

    public function test_many_changes_end_in_and_more(): void
    {
        $order = $this->shipped();

        foreach (range(1, 7) as $step) {
            OrderEvent::query()->create(['order_id' => $order->id, 'type' => OrderEvent::DELIVERY, 'current' => "Статус {$step}"]);
        }

        $this->actingAs($this->adminWith(['shop.orders.view']))
            ->getJson('/admin/api/shop/notices')
            ->assertOk()
            ->assertJsonCount(5, 'data')
            // Сначала самое свежее.
            ->assertJsonPath('data.0.current', 'Статус 7')
            ->assertJsonPath('more', 2);
    }

    public function test_notices_need_the_orders_permission(): void
    {
        $this->actingAs($this->adminWith([]))->getJson('/admin/api/shop/notices')->assertForbidden();
    }
}
