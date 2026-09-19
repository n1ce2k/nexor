<?php

namespace Tests\Feature\Shop;

use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Http\Client\Request;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\URL;
use Livewire\Livewire;
use Nexor\Cms\Support\Nexor;
use Nexor\Cms\Support\Secrets;
use Nexor\Shop\Enums\OrderPaymentStatus;
use Nexor\Shop\Enums\PaymentProvider;
use Nexor\Shop\Enums\PaymentStatus;
use Nexor\Shop\Livewire\Checkout;
use Nexor\Shop\Models\Order;
use Nexor\Shop\Models\Payment;
use Nexor\Shop\Models\PaymentMethod;
use Nexor\Shop\Support\Payments\PaymentException;
use Nexor\Shop\Support\Payments\Payments;
use Tests\Concerns\BuildsShop;
use Tests\Concerns\CreatesAdminUsers;
use Tests\TestCase;

/**
 * Оплата заказа через ЮKassa: создание платежа, подтверждение, возврат.
 *
 * До самой ЮKassa тесты не ходят — её ответы подставляются. Проверяется то,
 * что зависит от нас: что уходит в запросе и как меняется заказ.
 */
class PaymentTest extends TestCase
{
    use BuildsShop, CreatesAdminUsers, RefreshDatabase;

    /** @var array<string, mixed> Ответ, который подставляется на запрос к ЮKassa. */
    protected array $answer = [];

    protected int $answerStatus = 200;

    protected function setUp(): void
    {
        parent::setUp();

        Http::preventStrayRequests();

        // Повторный Http::fake() не отменяет прежний — срабатывает первый
        // подошедший. Поэтому обработчик один, а тесты меняют его ответ.
        Http::fake(['api.yookassa.ru/*' => fn () => Http::response($this->answer, $this->answerStatus)]);
    }

    protected function method(array $settings = []): PaymentMethod
    {
        return PaymentMethod::query()->create([
            'name' => 'Картой онлайн',
            'provider' => PaymentProvider::YooKassa,
            'settings' => Payments::fromInput([
                'shop_id' => '123456',
                'secret_key' => 'test_secret',
                ...$settings,
            ], null),
            'is_active' => true,
        ]);
    }

    protected function order(?PaymentMethod $method = null, array $attributes = []): Order
    {
        $method ??= $this->method();

        $order = Order::factory()->create([
            'payment_method_id' => $method->id,
            'payment_name' => $method->name,
            'total' => 1500,
            'delivery_price' => 300,
            'customer' => [
                ['code' => 'EMAIL', 'name' => 'E-mail', 'value' => 'client@example.test'],
                ['code' => 'PHONE', 'name' => 'Телефон', 'value' => '+7 999 000-00-00'],
            ],
            ...$attributes,
        ]);

        $order->items()->create([
            'name' => 'Стул',
            'quantity' => 2,
            'measure' => 'шт',
            'base_price' => 600,
            'price' => 600,
            'sum' => 1200,
        ]);

        return $order->fresh();
    }

    protected function fakeCreated(string $id = 'pay-1', string $status = 'pending'): void
    {
        $this->answer = [
            'id' => $id,
            'status' => $status,
            'paid' => $status === 'succeeded',
            'amount' => ['value' => '1500.00', 'currency' => 'RUB'],
            'confirmation' => ['type' => 'redirect', 'confirmation_url' => 'https://yookassa.ru/checkout/'.$id],
        ];
        $this->answerStatus = 200;
    }

    protected function fakeRefund(string $id, string $amount): void
    {
        $this->answer = [
            'id' => $id,
            'status' => 'succeeded',
            'amount' => ['value' => $amount, 'currency' => 'RUB'],
        ];
        $this->answerStatus = 200;
    }

    public function test_a_payment_is_created_with_the_order_amount(): void
    {
        $this->fakeCreated();
        $order = $this->order();

        $payment = Payments::start($order, 'https://site.test/return');

        $this->assertSame('pay-1', $payment->external_id);
        $this->assertSame(PaymentStatus::Pending, $payment->status);
        $this->assertSame('https://yookassa.ru/checkout/pay-1', $payment->confirmation_url);
        $this->assertSame(OrderPaymentStatus::Pending, $order->fresh()->payment_status);

        Http::assertSent(function (Request $request) {
            $body = $request->data();

            return str_ends_with($request->url(), '/payments')
                && $body['amount']['value'] === '1500.00'
                && $body['capture'] === true
                && $body['confirmation']['return_url'] === 'https://site.test/return'
                && $request->hasHeader('Idempotence-Key');
        });
    }

    public function test_the_receipt_carries_the_order_lines(): void
    {
        $this->fakeCreated();
        Nexor::modules()->updateSettings('shop', [
            'receipts_enabled' => true,
            'tax_system_code' => 2,
            'vat_code' => 4,
            'delivery_vat_code' => 1,
        ]);

        Payments::start($this->order(), 'https://site.test/return');

        Http::assertSent(function (Request $request) {
            $receipt = $request->data()['receipt'] ?? null;

            return $receipt !== null
                && $receipt['customer']['email'] === 'client@example.test'
                && $receipt['tax_system_code'] === 2
                && $receipt['items'][0]['description'] === 'Стул'
                && $receipt['items'][0]['quantity'] === '2.000'
                && $receipt['items'][0]['vat_code'] === 4
                // Доставка идёт отдельной строкой со своей ставкой.
                && $receipt['items'][1]['payment_subject'] === 'service'
                && $receipt['items'][1]['vat_code'] === 1;
        });
    }

    public function test_no_receipt_is_sent_when_receipts_are_off(): void
    {
        $this->fakeCreated();

        Payments::start($this->order(), 'https://site.test/return');

        Http::assertSent(fn (Request $request) => ! isset($request->data()['receipt']));
    }

    public function test_an_unfinished_payment_is_reused(): void
    {
        $this->fakeCreated();
        $order = $this->order();

        $first = Payments::start($order, 'https://site.test/return');
        $second = Payments::start($order->fresh(), 'https://site.test/return');

        $this->assertSame($first->id, $second->id);
        $this->assertSame(1, Payment::query()->count());
    }

    public function test_a_successful_payment_marks_the_order_paid(): void
    {
        $this->fakeCreated();
        $order = $this->order();
        $payment = Payments::start($order, 'https://site.test/return');

        $this->fakeCreated('pay-1', 'succeeded');
        Payments::sync($payment);

        $order = $order->fresh();

        $this->assertSame(OrderPaymentStatus::Paid, $order->payment_status);
        $this->assertNotNull($order->paid_at);
        $this->assertSame(PaymentStatus::Succeeded, $payment->fresh()->status);
    }

    public function test_a_paid_order_is_not_paid_twice(): void
    {
        $this->fakeCreated('pay-1', 'succeeded');
        $order = $this->order();
        Payments::start($order, 'https://site.test/return');

        $this->expectException(PaymentException::class);

        Payments::start($order->fresh(), 'https://site.test/return');
    }

    public function test_a_method_without_keys_refuses_to_start(): void
    {
        $method = PaymentMethod::query()->create([
            'name' => 'Наличными',
            'provider' => PaymentProvider::None,
            'is_active' => true,
        ]);

        $this->expectException(PaymentException::class);

        Payments::start($this->order($method), 'https://site.test/return');
    }

    public function test_the_secret_key_never_leaves_the_panel(): void
    {
        $method = $this->method();

        $this->assertSame('test_secret', Secrets::decrypt($method->settings['secret_key']));
        $this->assertSame(Secrets::MASK, Payments::forPanel($method)['secret_key']);
        $this->assertStringNotContainsString('test_secret', json_encode(Payments::forPanel($method)));
    }

    public function test_the_buyer_is_sent_to_the_payment_page(): void
    {
        $this->fakeCreated();
        $order = $this->order();

        $this->get(URL::signedRoute('shop.payment.pay', ['order' => $order->id]))
            ->assertRedirect('https://yookassa.ru/checkout/pay-1');
    }

    public function test_the_payment_link_must_be_signed(): void
    {
        $order = $this->order();

        $this->get('/shop/pay/'.$order->id)->assertForbidden();
        $this->get('/shop/pay/'.$order->id.'/result')->assertForbidden();
    }

    public function test_the_result_page_asks_the_provider_again(): void
    {
        $this->fakeCreated();
        $order = $this->order();
        Payments::start($order, 'https://site.test/return');

        // Покупатель вернулся: редиректу не верим, состояние спрашиваем заново.
        $this->fakeCreated('pay-1', 'succeeded');

        $this->get(URL::signedRoute('shop.payment.result', ['order' => $order->id]))
            ->assertOk()
            ->assertSee('оплачен', false);

        $this->assertSame(OrderPaymentStatus::Paid, $order->fresh()->payment_status);
    }

    public function test_the_webhook_refreshes_the_payment(): void
    {
        $this->fakeCreated();
        $order = $this->order();
        Payments::start($order, 'https://site.test/return');

        $this->fakeCreated('pay-1', 'succeeded');

        $this->postJson('/shop/payment/yookassa', [
            'event' => 'payment.succeeded',
            'object' => ['id' => 'pay-1', 'status' => 'succeeded'],
        ])->assertOk();

        $this->assertSame(OrderPaymentStatus::Paid, $order->fresh()->payment_status);
    }

    public function test_the_webhook_ignores_an_unknown_payment(): void
    {
        $this->postJson('/shop/payment/yookassa', [
            'event' => 'payment.succeeded',
            'object' => ['id' => 'someone-else'],
        ])->assertOk();

        Http::assertNothingSent();
    }

    public function test_money_can_be_returned_in_full_and_in_part(): void
    {
        $this->fakeCreated('pay-1', 'succeeded');
        $order = $this->order();
        $payment = Payments::start($order, 'https://site.test/return');

        $this->fakeRefund('refund-1', '500.00');

        $refund = Payments::refund($payment, 500, 'Не подошёл размер', 7);

        $this->assertSame('refund-1', $refund->external_id);
        $this->assertSame('500.00', $payment->fresh()->refunded);
        $this->assertSame(OrderPaymentStatus::PartiallyRefunded, $order->fresh()->payment_status);

        $this->fakeRefund('refund-2', '1000.00');

        Payments::refund($payment->fresh(), 1000);

        $this->assertSame(OrderPaymentStatus::Refunded, $order->fresh()->payment_status);
    }

    public function test_more_than_paid_cannot_be_returned(): void
    {
        $this->fakeCreated('pay-1', 'succeeded');
        $payment = Payments::start($this->order(), 'https://site.test/return');

        $this->expectException(PaymentException::class);

        Payments::refund($payment, 2000);
    }

    public function test_a_provider_error_is_explained_in_russian(): void
    {
        $this->answer = ['type' => 'error', 'code' => 'invalid_credentials'];
        $this->answerStatus = 401;

        $this->expectExceptionMessage('ЮKassa не приняла ключи магазина');

        Payments::start($this->order(), 'https://site.test/return');
    }

    // ------------------------------------------------------------------ панель

    public function test_the_panel_saves_the_keys_encrypted(): void
    {
        $admin = $this->adminWith(['shop.checkout.view', 'shop.checkout.update']);

        $id = $this->actingAs($admin)->postJson('/admin/api/shop/payment-methods', [
            'name' => 'Картой онлайн',
            'provider' => 'yookassa',
            'settings' => ['shop_id' => '123456', 'secret_key' => 'test_secret', 'description' => 'Заказ №{number}'],
        ])->assertCreated()->json('data');

        $method = PaymentMethod::query()->find($id['id']);

        $this->assertSame(PaymentProvider::YooKassa, $method->provider);
        $this->assertSame('test_secret', Secrets::decrypt($method->settings['secret_key']));
        // Наружу ключ не выходит даже сразу после сохранения.
        $this->assertSame(Secrets::MASK, $id['settings']['secret_key']);
        $this->assertTrue($id['is_ready']);
    }

    public function test_the_saved_key_survives_an_edit_without_it(): void
    {
        $admin = $this->adminWith(['shop.checkout.update']);
        $method = $this->method();

        $this->actingAs($admin)->putJson("/admin/api/shop/payment-methods/{$method->id}", [
            'name' => 'Картой на сайте',
            'provider' => 'yookassa',
            // Панель вернула маску — её же и отправляет обратно.
            'settings' => ['shop_id' => '123456', 'secret_key' => Secrets::MASK],
        ])->assertOk();

        $this->assertSame('test_secret', Secrets::decrypt($method->fresh()->settings['secret_key']));
    }

    public function test_a_yookassa_method_needs_a_shop_id(): void
    {
        $this->actingAs($this->adminWith(['shop.checkout.update']))
            ->postJson('/admin/api/shop/payment-methods', [
                'name' => 'Картой онлайн',
                'provider' => 'yookassa',
                'settings' => ['shop_id' => '', 'secret_key' => 'test_secret'],
            ])
            ->assertJsonValidationErrors('settings.shop_id');
    }

    public function test_the_manager_sees_the_payment_and_can_re_ask_the_provider(): void
    {
        $this->fakeCreated();
        $order = $this->order();
        Payments::start($order, 'https://site.test/return');

        $this->fakeCreated('pay-1', 'succeeded');

        $data = $this->actingAs($this->adminWith(['shop.orders.view']))
            ->postJson("/admin/api/shop/orders/{$order->id}/payments/sync")
            ->assertOk()
            ->json('data');

        $this->assertSame('paid', $data['payment_status']);
        $this->assertSame('pay-1', $data['payments'][0]['external_id']);
        $this->assertArrayNotHasKey('payload', $data['payments'][0]);
    }

    public function test_a_refund_needs_its_own_permission(): void
    {
        $this->fakeCreated('pay-1', 'succeeded');
        $order = $this->order();
        $payment = Payments::start($order, 'https://site.test/return');

        $this->actingAs($this->adminWith(['shop.orders.view', 'shop.orders.update']))
            ->postJson("/admin/api/shop/orders/{$order->id}/payments/{$payment->id}/refund", ['amount' => 100])
            ->assertForbidden();

        $this->fakeRefund('refund-1', '100.00');

        $this->actingAs($this->adminWith(['shop.orders.view', 'shop.payments.refund']))
            ->postJson("/admin/api/shop/orders/{$order->id}/payments/{$payment->id}/refund", [
                'amount' => 100,
                'reason' => 'Товар не подошёл',
            ])
            ->assertOk()
            ->assertJsonPath('data.payment_status', 'partially_refunded');
    }

    public function test_more_than_paid_is_refused_by_the_panel(): void
    {
        $this->fakeCreated('pay-1', 'succeeded');
        $order = $this->order();
        $payment = Payments::start($order, 'https://site.test/return');

        $this->actingAs($this->adminWith(['shop.payments.refund']))
            ->postJson("/admin/api/shop/orders/{$order->id}/payments/{$payment->id}/refund", ['amount' => 9000])
            ->assertJsonValidationErrors('amount');
    }

    // --------------------------------------------------------- оформление

    /**
     * Оформление Ultimate с уже готовым к оплате способом.
     */
    protected function checkout(PaymentMethod $method)
    {
        $this->ultimate();
        $product = $this->product(['price' => 1500]);

        return Livewire::withCookies($this->cartCookie([$product->id => 1]))
            ->test(Checkout::class)
            ->set('customer.NAME', 'Иван')
            ->set('customer.PHONE', '+7 900 123-45-67')
            ->set('paymentId', $method->id)
            ->call('placeOrder');
    }

    public function test_the_buyer_gets_a_pay_button_after_checkout(): void
    {
        $component = $this->checkout($this->method())->assertHasNoErrors();

        $order = Order::query()->sole();

        $this->assertNotNull($component->get('paymentUrl'));
        $component->assertSee('Оплатить заказ')->assertNoRedirect();
        $this->assertSame(
            URL::signedRoute('shop.payment.pay', ['order' => $order->id]),
            $component->get('paymentUrl'),
        );
    }

    public function test_the_method_can_take_the_buyer_to_payment_at_once(): void
    {
        $this->checkout($this->method(['auto_redirect' => true]))
            ->assertHasNoErrors()
            ->assertRedirect(URL::signedRoute('shop.payment.pay', ['order' => Order::query()->sole()->id]));
    }

    public function test_a_method_without_online_payment_leaves_no_link(): void
    {
        $method = PaymentMethod::query()->create([
            'name' => 'Наличными',
            'provider' => PaymentProvider::None,
            'is_active' => true,
        ]);

        $component = $this->checkout($method)->assertHasNoErrors();

        $this->assertNull($component->get('paymentUrl'));
        $component->assertDontSee('Оплатить заказ')->assertNoRedirect();
    }
}
