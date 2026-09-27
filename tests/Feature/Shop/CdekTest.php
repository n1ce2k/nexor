<?php

namespace Tests\Feature\Shop;

use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Http\Client\Request;
use Illuminate\Support\Facades\File;
use Illuminate\Support\Facades\Http;
use Illuminate\Validation\ValidationException;
use Livewire\Livewire;
use Nexor\Cms\Models\IblockProperty;
use Nexor\Cms\Support\Secrets;
use Nexor\Shop\Enums\DeliveryProvider;
use Nexor\Shop\Enums\DeliveryState;
use Nexor\Shop\Livewire\Checkout;
use Nexor\Shop\Models\DeliveryMethod;
use Nexor\Shop\Models\Order;
use Nexor\Shop\Models\OrderField;
use Nexor\Shop\Models\PaymentMethod;
use Nexor\Shop\Support\Delivery\Cdek;
use Nexor\Shop\Support\Delivery\Deliveries;
use Nexor\Shop\Support\Delivery\DeliveryException;
use Nexor\Shop\Support\Delivery\Rates;
use Nexor\Shop\Support\Delivery\Shipments;
use Nexor\Shop\Support\OrderPlacer;
use Tests\Concerns\BuildsShop;
use Tests\Concerns\CreatesAdminUsers;
use Tests\Concerns\PreservesPublishedViews;
use Tests\TestCase;

/**
 * Расчёт доставки СДЭК: что уходит в службу и что видит покупатель.
 *
 * Наружу тесты не ходят: ответы СДЭК подменяются, иначе проверки зависели бы
 * от чужого сервиса и договора.
 */
class CdekTest extends TestCase
{
    use BuildsShop, CreatesAdminUsers, PreservesPublishedViews, RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();

        // Проверяем шаблоны пакета, а не копии этого сайта: их кладём в сторону.
        File::deleteDirectory($this->preserveViews('vendor/nexor-shop'));
    }

    protected function tearDown(): void
    {
        $this->restorePreservedViews();

        parent::tearDown();
    }

    /**
     * Способ доставки со СДЭК и тестовыми ключами.
     *
     * @param  array<string, mixed>  $settings
     */
    protected function cdekMethod(array $settings = []): DeliveryMethod
    {
        $method = DeliveryMethod::factory()->create(['name' => 'СДЭК', 'price' => 0]);

        // Списки заменяются целиком: рекурсивное слияние оставило бы старые тарифы.
        $lists = array_intersect_key($settings, ['tariffs' => null, 'address' => null, 'sender' => null]);

        $method->forceFill([
            'provider' => DeliveryProvider::Cdek,
            'settings' => Deliveries::fromInput(array_replace(array_replace_recursive([
                'account' => 'account',
                'secret' => 'secret',
                'test' => true,
                'from_code' => 44,
                'tariffs' => [['code' => 136, 'name' => 'Посылка склад-склад', 'to_door' => false]],
                'weight' => ['property' => 'WEIGHT', 'unit' => 'kg', 'default' => 2],
                'dimensions' => [
                    'length' => ['property' => 'LENGTH', 'default' => 30],
                    'width' => ['property' => 'WIDTH', 'default' => 20],
                    'height' => ['property' => 'HEIGHT', 'default' => 10],
                ],
            ], $settings), $lists), null),
        ])->save();

        return $method->refresh();
    }

    /**
     * @param  array<string, mixed>  $tariff
     */
    protected function fakeCdek(array $tariff = ['total_sum' => 1000, 'period_min' => 3, 'period_max' => 5]): void
    {
        Http::fake([
            '*/oauth/token' => Http::response(['access_token' => 'token', 'expires_in' => 3600]),
            '*/calculator/tariff' => Http::response($tariff),
        ]);
    }

    public function test_the_settings_keep_the_password_encrypted(): void
    {
        $method = $this->cdekMethod();
        $settings = Deliveries::settings($method);

        $this->assertNotSame('secret', $settings['secret']);
        $this->assertSame('secret', Secrets::decrypt($settings['secret']));
        $this->assertTrue(Deliveries::ready($method));
    }

    public function test_a_method_without_keys_is_not_ready(): void
    {
        $this->assertFalse(Deliveries::ready($this->cdekMethod(['account' => ''])));
        $this->assertFalse(Deliveries::ready($this->cdekMethod(['tariffs' => []])));
        $this->assertFalse(Deliveries::ready(DeliveryMethod::factory()->create()));
    }

    public function test_the_tariff_price_comes_from_cdek(): void
    {
        $this->fakeCdek();

        $product = $this->product(['price' => 5000]);
        $cart = $this->emptyCart();
        $cart->add($product->id, 2);

        $rates = Rates::forCart($this->cdekMethod(), $cart->summary(), 137);

        $this->assertSame(1000.0, $rates[0]['price']);
        $this->assertSame(3, $rates[0]['period_min']);
        $this->assertNull($rates[0]['error']);
    }

    public function test_the_markup_and_rounding_reach_the_buyer(): void
    {
        $this->fakeCdek();

        $product = $this->product();
        $cart = $this->emptyCart();
        $cart->add($product->id);

        $method = $this->cdekMethod(['price' => ['markup_percent' => 10, 'markup_fixed' => 90, 'round' => 'hundred']]);

        // 1000 + 10% = 1100, плюс 90 — и вверх до сотни.
        $this->assertSame(1200.0, Rates::forCart($method, $cart->summary(), 137)[0]['price']);
    }

    public function test_the_weight_and_size_come_from_the_product_properties(): void
    {
        $this->fakeCdek();

        $iblock = $this->catalogIblock();

        foreach (['WEIGHT' => 3, 'LENGTH' => 60] as $code => $value) {
            $property = IblockProperty::query()->create([
                'iblock_id' => $iblock->id,
                'name' => $code,
                'code' => $code,
                'type' => 'decimal',
                'is_active' => true,
            ]);

            $this->product()->values()->create(['property_id' => $property->id, 'value_decimal' => $value]);
        }

        $product = $this->catalogIblock()->elements()->latest('id')->first();

        $cart = $this->emptyCart();
        $cart->add($product->id, 2);

        Rates::forCart($this->cdekMethod(), $cart->summary(), 137);

        Http::assertSent(function (Request $request): bool {
            if (! str_contains($request->url(), 'calculator/tariff')) {
                return true;
            }

            $package = $request->data()['packages'][0];

            // 3 кг за штуку, две штуки — шесть килограммов в граммах.
            return $package['weight'] === 6000
                && $package['length'] === 60
                // Незаполненные габариты берутся из настроек способа.
                && $package['width'] === 20
                && $package['height'] === 10;
        });
    }

    public function test_a_tariff_to_the_door_needs_an_address(): void
    {
        $this->fakeCdek();

        $product = $this->product();
        $cart = $this->emptyCart();
        $cart->add($product->id);

        $method = $this->cdekMethod(['tariffs' => [['code' => 137, 'name' => 'До двери', 'to_door' => true]]]);

        $this->assertSame('Укажите адрес доставки.', Rates::forCart($method, $cart->summary(), 137)[0]['error']);
        $this->assertNull(Rates::forCart($method, $cart->summary(), 137, 'ул. Ленина, 1')[0]['error']);
    }

    public function test_a_refusal_of_cdek_stays_inside_the_tariff(): void
    {
        Http::fake([
            '*/oauth/token' => Http::response(['access_token' => 'token', 'expires_in' => 3600]),
            '*/calculator/tariff' => Http::response(['errors' => [['message' => 'Тариф недоступен']]]),
        ]);

        $product = $this->product();
        $cart = $this->emptyCart();
        $cart->add($product->id);

        $rate = Rates::forCart($this->cdekMethod(), $cart->summary(), 137)[0];

        $this->assertNull($rate['price']);
        $this->assertSame('Тариф недоступен', $rate['error']);
    }

    public function test_an_unreachable_cdek_does_not_break_the_checkout(): void
    {
        Http::fake(['*' => Http::response('', 500)]);

        $product = $this->product();
        $cart = $this->emptyCart();
        $cart->add($product->id);

        $rate = Rates::forCart($this->cdekMethod(), $cart->summary(), 137)[0];

        $this->assertNull($rate['price']);
        $this->assertNotNull($rate['error']);
    }

    public function test_the_token_is_asked_once_and_reused(): void
    {
        $this->fakeCdek();

        $method = $this->cdekMethod();
        $product = $this->product();
        $cart = $this->emptyCart();
        $cart->add($product->id);

        Rates::forCart($method, $cart->summary(), 137);
        Rates::forCart($method, $cart->summary(), 138);

        $tokens = 0;

        Http::assertSent(function (Request $request) use (&$tokens): bool {
            $tokens += str_contains($request->url(), 'oauth/token') ? 1 : 0;

            return true;
        });

        $this->assertSame(1, $tokens);
    }

    public function test_the_checkout_picks_a_city_a_tariff_and_a_point(): void
    {
        $this->ultimate();
        $this->fakeCdek();

        Http::fake([
            '*/oauth/token' => Http::response(['access_token' => 'token', 'expires_in' => 3600]),
            '*/calculator/tariff' => Http::response(['total_sum' => 1000, 'period_min' => 3, 'period_max' => 5]),
            '*/location/suggest/cities*' => Http::response([
                ['code' => 44, 'city' => 'Москва', 'region' => 'Москва', 'full_name' => 'Москва, Москва'],
            ]),
            '*/deliverypoints*' => Http::response([
                [
                    'code' => 'MSK101',
                    'name' => 'Пункт на Ленина',
                    'type' => 'PVZ',
                    'location' => ['address' => 'ул. Ленина, 1', 'address_full' => 'Москва, ул. Ленина, 1', 'latitude' => 55.7, 'longitude' => 37.6],
                ],
            ]),
        ]);

        $method = $this->cdekMethod();
        PaymentMethod::factory()->create(['name' => 'Наличными']);

        $product = $this->product(['price' => 5000]);

        Livewire::withCookies($this->cartCookie([$product->id => 1]))
            ->test(Checkout::class)
            ->set('deliveryId', $method->id)
            ->set('cityQuery', 'Моск')
            ->call('searchCity')
            ->assertSet('cities.0.code', 44)
            ->call('pickCity', 44, 'Москва')
            ->assertSet('cityCode', 44)
            ->set('tariffCode', 136)
            // Цена тарифа сразу видна покупателю и уходит в итог заказа.
            ->assertSee('1 000 ₽')
            // Пункты уезжают в разметку списком на Alpine, поэтому смотрим на код.
            ->assertSee('MSK101')
            ->call('pickPoint', 'MSK101', 'Москва, ул. Ленина, 1')
            ->assertSet('pointCode', 'MSK101')
            ->assertSee('Выбран пункт MSK101');
    }

    public function test_the_order_keeps_the_chosen_tariff_and_point(): void
    {
        $this->ultimate();
        $this->fakeCdek();

        $method = $this->cdekMethod();
        $product = $this->product(['price' => 5000]);
        $cart = $this->emptyCart();
        $cart->add($product->id);

        $order = app(OrderPlacer::class)->place($cart, ['NAME' => 'Иван', 'PHONE' => '+70000000000'], $method->id, null, [
            'city_code' => 44,
            'city' => 'Москва',
            'tariff' => 136,
            'point' => 'MSK101',
            'point_address' => 'Москва, ул. Ленина, 1',
        ]);

        $this->assertSame(1000.0, (float) $order->delivery_price);
        $this->assertSame('СДЭК, Посылка склад-склад', $order->delivery_name);
        $this->assertSame(44, $order->delivery_data['city_code']);
        $this->assertSame('MSK101', $order->delivery_data['point']);
        $this->assertSame(6000.0, (float) $order->total);
    }

    public function test_an_order_without_a_point_is_refused(): void
    {
        $this->ultimate();
        $this->fakeCdek();

        $method = $this->cdekMethod();
        $product = $this->product();
        $cart = $this->emptyCart();
        $cart->add($product->id);

        $this->expectException(ValidationException::class);

        app(OrderPlacer::class)->place($cart, ['NAME' => 'Иван', 'PHONE' => '+70000000000'], $method->id, null, [
            'city_code' => 44,
            'tariff' => 136,
        ]);
    }

    public function test_an_unpriced_tariff_goes_to_the_manager(): void
    {
        $this->ultimate();

        Http::fake([
            '*/oauth/token' => Http::response(['access_token' => 'token', 'expires_in' => 3600]),
            '*/calculator/tariff' => Http::response(['errors' => [['message' => 'Тариф недоступен']]]),
        ]);

        $method = $this->cdekMethod();
        $product = $this->product();
        $cart = $this->emptyCart();
        $cart->add($product->id);

        $order = app(OrderPlacer::class)->place($cart, ['NAME' => 'Иван', 'PHONE' => '+70000000000'], $method->id, null, [
            'city_code' => 44,
            'tariff' => 136,
            'point' => 'MSK101',
        ]);

        $this->assertSame(0.0, (float) $order->delivery_price);
        $this->assertStringContainsString('рассчитает менеджер', $order->delivery_name);
        $this->assertTrue($order->delivery_data['manual']);
    }

    public function test_the_block_setting_does_not_let_the_order_through(): void
    {
        $this->ultimate();

        Http::fake([
            '*/oauth/token' => Http::response(['access_token' => 'token', 'expires_in' => 3600]),
            '*/calculator/tariff' => Http::response(['errors' => [['message' => 'Тариф недоступен']]]),
        ]);

        $method = $this->cdekMethod(['price' => ['on_error' => 'block']]);
        $product = $this->product();
        $cart = $this->emptyCart();
        $cart->add($product->id);

        $this->expectException(ValidationException::class);

        app(OrderPlacer::class)->place($cart, ['NAME' => 'Иван', 'PHONE' => '+70000000000'], $method->id, null, [
            'city_code' => 44,
            'tariff' => 136,
            'point' => 'MSK101',
        ]);
    }

    public function test_free_from_wins_over_the_calculation(): void
    {
        $this->ultimate();
        $this->fakeCdek();

        $method = $this->cdekMethod();
        $method->update(['free_from' => 1000]);

        $product = $this->product(['price' => 5000]);
        $cart = $this->emptyCart();
        $cart->add($product->id);

        $order = app(OrderPlacer::class)->place($cart, ['NAME' => 'Иван', 'PHONE' => '+70000000000'], $method->id, null, [
            'city_code' => 44,
            'tariff' => 136,
            'point' => 'MSK101',
        ]);

        $this->assertSame(0.0, (float) $order->delivery_price);
        $this->assertTrue($order->delivery_data['free']);
    }

    public function test_the_address_can_come_from_an_order_field(): void
    {
        $this->ultimate();
        $this->fakeCdek();

        $method = $this->cdekMethod([
            'tariffs' => [['code' => 137, 'name' => 'До двери', 'to_door' => true]],
            'address' => ['own' => false, 'field' => 'ADDRESS'],
        ]);

        OrderField::factory()->create(['code' => 'ADDRESS', 'name' => 'Адрес', 'type' => 'text', 'is_required' => false]);

        $product = $this->product();
        $cart = $this->emptyCart();
        $cart->add($product->id);

        $order = app(OrderPlacer::class)->place($cart, [
            'NAME' => 'Иван',
            'PHONE' => '+70000000000',
            'ADDRESS' => 'Москва, ул. Ленина, 1',
        ], $method->id, null, ['city_code' => 44, 'tariff' => 137]);

        $this->assertSame('Москва, ул. Ленина, 1', $order->delivery_data['address']);
        $this->assertSame(1000.0, (float) $order->delivery_price);
    }

    public function test_an_empty_address_field_stops_the_order(): void
    {
        $this->ultimate();
        $this->fakeCdek();

        $method = $this->cdekMethod([
            'tariffs' => [['code' => 137, 'name' => 'До двери', 'to_door' => true]],
            'address' => ['own' => false, 'field' => 'ADDRESS'],
        ]);

        $product = $this->product();
        $cart = $this->emptyCart();
        $cart->add($product->id);

        $this->expectExceptionMessage('Заполните адрес в данных покупателя.');

        app(OrderPlacer::class)->place($cart, ['NAME' => 'Иван', 'PHONE' => '+70000000000'], $method->id, null, [
            'city_code' => 44,
            'tariff' => 137,
        ]);
    }

    public function test_typing_a_city_loads_the_suggestions(): void
    {
        $this->ultimate();

        Http::fake([
            '*/oauth/token' => Http::response(['access_token' => 'token', 'expires_in' => 3600]),
            '*/location/suggest/cities*' => Http::response([
                ['code' => 44, 'city' => 'Москва', 'region' => 'Москва', 'full_name' => 'Москва, Россия'],
            ]),
        ]);

        $method = $this->cdekMethod();
        $product = $this->product();

        Livewire::withCookies($this->cartCookie([$product->id => 1]))
            ->test(Checkout::class)
            ->set('deliveryId', $method->id)
            // Отдельного нажатия «Найти» не требуется: хватает ввода.
            ->set('cityQuery', 'Моск')
            ->assertSet('cities.0.code', 44)
            // Одна буква — не повод дёргать службу.
            ->set('cityQuery', 'М')
            ->assertSet('cities', [])
            // Поле шлёт ввод на сервер само: с отложенной привязкой подсказки
            // появлялись бы только по кнопке «Найти».
            ->assertSeeHtml('wire:model.live.debounce.500ms="cityQuery"');
    }

    public function test_the_order_shows_the_delivery_details_to_the_manager(): void
    {
        $this->ultimate();
        $this->fakeCdek();

        $method = $this->cdekMethod();
        $product = $this->product();
        $cart = $this->emptyCart();
        $cart->add($product->id);

        $order = app(OrderPlacer::class)->place($cart, ['NAME' => 'Иван', 'PHONE' => '+70000000000'], $method->id, null, [
            'city_code' => 44,
            'city' => 'Москва, Россия',
            'tariff' => 136,
            'point' => 'MSK101',
            'point_address' => 'Москва, ул. Ленина, 1',
        ]);

        $details = collect($order->deliveryDetails())->pluck('value', 'label');

        $this->assertSame('Москва, Россия', $details['Город']);
        $this->assertStringContainsString('MSK101', $details['Пункт выдачи']);
        $this->assertStringContainsString('Посылка склад-склад', $details['Тариф']);
        $this->assertSame('3–5 дн.', $details['Срок']);
    }

    /**
     * Заказ, уже оформленный со СДЭК, — с него начинается передача.
     */
    protected function cdekOrder(DeliveryMethod $method): Order
    {
        $product = $this->product(['price' => 5000], ['code' => 'stul-venskiy']);
        $cart = $this->emptyCart();
        $cart->add($product->id, 2);

        return app(OrderPlacer::class)->place($cart, ['NAME' => 'Иван Петров', 'PHONE' => '+79000000000'], $method->id, null, [
            'city_code' => 44,
            'city' => 'Москва',
            'tariff' => 136,
            'point' => 'MSK101',
            'point_address' => 'Москва, ул. Ленина, 1',
        ]);
    }

    public function test_the_order_goes_to_cdek_and_gets_a_track_number(): void
    {
        $this->ultimate();

        Http::fake([
            '*/oauth/token' => Http::response(['access_token' => 'token', 'expires_in' => 3600]),
            '*/calculator/tariff' => Http::response(['total_sum' => 1000, 'period_min' => 3, 'period_max' => 5]),
            '*/v2/orders' => Http::response(['entity' => ['uuid' => 'uuid-1']], 202),
            '*/v2/orders/uuid-1' => Http::response(['entity' => [
                'uuid' => 'uuid-1',
                'cdek_number' => '1105223344',
                'statuses' => [['name' => 'Создан'], ['name' => 'Принят на склад отправителя']],
            ]]),
        ]);

        $method = $this->cdekMethod(['sender' => ['name' => 'ООО «Ромашка»', 'phone' => '+74950000000']]);
        $order = $this->cdekOrder($method);

        $this->assertSame(DeliveryState::None, $order->delivery_state);
        $this->assertTrue(Shipments::possible($order));

        Shipments::register($order);

        $order->refresh();

        $this->assertSame(DeliveryState::Registered, $order->delivery_state);
        $this->assertSame('1105223344', $order->delivery_track);
        $this->assertSame('Принят на склад отправителя', $order->delivery_status);
        $this->assertNull($order->delivery_error);
        $this->assertStringContainsString('1105223344', $order->deliveryTrackUrl());

        // Передавать второй раз нечего.
        $this->assertFalse(Shipments::possible($order));
    }

    public function test_the_request_carries_the_recipient_the_point_and_the_items(): void
    {
        $this->ultimate();

        Http::fake([
            '*/oauth/token' => Http::response(['access_token' => 'token', 'expires_in' => 3600]),
            '*/calculator/tariff' => Http::response(['total_sum' => 1000]),
            '*/v2/orders' => Http::response(['entity' => ['uuid' => 'uuid-2']], 202),
            '*/v2/orders/uuid-2' => Http::response(['entity' => ['uuid' => 'uuid-2', 'cdek_number' => '777']]),
        ]);

        $method = $this->cdekMethod();
        Shipments::register($this->cdekOrder($method));

        Http::assertSent(function (Request $request): bool {
            if (! str_ends_with($request->url(), '/v2/orders') || $request->method() !== 'POST') {
                return true;
            }

            $body = $request->data();
            $item = $body['packages'][0]['items'][0];

            return $body['tariff_code'] === 136
                && $body['delivery_point'] === 'MSK101'
                && $body['recipient']['name'] === 'Иван Петров'
                && $body['recipient']['phones'][0]['number'] === '+79000000000'
                // Наложенного платежа нет, объявленная стоимость — цена товара.
                && $item['payment']['value'] === 0
                && $item['cost'] === 5000.0
                && $item['amount'] === 2
                // Артикул — символьный код элемента, свойства ARTICLE у него нет.
                && $item['ware_key'] === 'stul-venskiy'
                // Два стула по два килограмма из настроек способа.
                && $body['packages'][0]['weight'] === 4000;
        });
    }

    public function test_a_refusal_of_cdek_is_kept_with_the_order(): void
    {
        $this->ultimate();

        Http::fake([
            '*/oauth/token' => Http::response(['access_token' => 'token', 'expires_in' => 3600]),
            '*/calculator/tariff' => Http::response(['total_sum' => 1000]),
            '*/v2/orders' => Http::response(['requests' => [['errors' => [['message' => 'Пункт выдачи закрыт']]]]], 202),
        ]);

        $method = $this->cdekMethod();
        $order = $this->cdekOrder($method);

        try {
            Shipments::register($order);
            $this->fail('Регистрация должна была отказать.');
        } catch (DeliveryException $exception) {
            $this->assertSame('Пункт выдачи закрыт', $exception->getMessage());
        }

        $order->refresh();

        $this->assertSame(DeliveryState::Failed, $order->delivery_state);
        $this->assertSame('Пункт выдачи закрыт', $order->delivery_error);
        // Отказ не мешает попробовать снова после правки заказа.
        $this->assertTrue(Shipments::possible($order));
    }

    public function test_a_number_that_is_not_ready_yet_leaves_the_order_waiting(): void
    {
        $this->ultimate();

        Http::fake([
            '*/oauth/token' => Http::response(['access_token' => 'token', 'expires_in' => 3600]),
            '*/calculator/tariff' => Http::response(['total_sum' => 1000]),
            '*/v2/orders' => Http::response(['entity' => ['uuid' => 'uuid-3']], 202),
            '*/v2/orders/uuid-3' => Http::sequence()
                ->push(['entity' => ['uuid' => 'uuid-3']])
                ->push(['entity' => ['uuid' => 'uuid-3', 'cdek_number' => '999']]),
        ]);

        $method = $this->cdekMethod();
        $order = $this->cdekOrder($method);

        Shipments::register($order);
        $order->refresh();

        $this->assertSame(DeliveryState::Pending, $order->delivery_state);
        $this->assertNull($order->delivery_track);

        // Второй заход за номером — он уже есть.
        Shipments::sync($order);

        $this->assertSame(DeliveryState::Registered, $order->refresh()->delivery_state);
        $this->assertSame('999', $order->delivery_track);
    }

    public function test_the_panel_sends_the_order_and_shows_the_track(): void
    {
        $this->ultimate();

        Http::fake([
            '*/oauth/token' => Http::response(['access_token' => 'token', 'expires_in' => 3600]),
            '*/calculator/tariff' => Http::response(['total_sum' => 1000]),
            '*/v2/orders' => Http::response(['entity' => ['uuid' => 'uuid-4']], 202),
            '*/v2/orders/uuid-4' => Http::response(['entity' => ['uuid' => 'uuid-4', 'cdek_number' => '1234567890']]),
        ]);

        $method = $this->cdekMethod();
        $order = $this->cdekOrder($method);

        $this->actingAs($this->adminWith(['shop.orders.view', 'shop.orders.update']))
            ->postJson("/admin/api/shop/orders/{$order->id}/delivery/register")
            ->assertOk()
            ->assertJsonPath('data.delivery.track', '1234567890')
            ->assertJsonPath('data.delivery.state', 'registered')
            ->assertJsonPath('data.delivery.can_register', false);
    }

    public function test_sending_an_order_needs_the_right_to_edit_orders(): void
    {
        $this->ultimate();
        $this->fakeCdek();

        $order = $this->cdekOrder($this->cdekMethod());

        $this->actingAs($this->adminWith(['shop.orders.view']))
            ->postJson("/admin/api/shop/orders/{$order->id}/delivery/register")
            ->assertForbidden();
    }

    public function test_the_test_contour_goes_to_another_host(): void
    {
        $this->assertSame(Cdek::TEST_URL, (new Cdek('a', 'b', true))->url());
        $this->assertSame(Cdek::URL, (new Cdek('a', 'b', false))->url());
    }
}
