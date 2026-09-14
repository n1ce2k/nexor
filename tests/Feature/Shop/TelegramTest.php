<?php

namespace Tests\Feature\Shop;

use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Http\Client\Request;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Mail;
use Livewire\Livewire;
use Nexor\Shop\Livewire\CartPage;
use Nexor\Shop\Support\Shop;
use Nexor\Shop\Support\TelegramNotifier;
use Tests\Concerns\BuildsShop;
use Tests\Concerns\CreatesAdminUsers;
use Tests\TestCase;

/**
 * Уведомления о заказах в Telegram.
 */
class TelegramTest extends TestCase
{
    use BuildsShop, CreatesAdminUsers, RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();

        Mail::fake();
    }

    /**
     * @param  array<string, mixed>  $overrides
     * @return array<string, mixed>
     */
    protected function settingsPayload(array $overrides = []): array
    {
        return $overrides + [
            'edition' => 'basic',
            'display' => 'page',
            'currency' => 'RUB',
            'telegram_enabled' => true,
            'telegram_token' => '123456:secret-token',
            'telegram_chat_id' => '987654',
        ];
    }

    public function test_the_token_is_stored_encrypted_and_never_sent_back(): void
    {
        $response = $this->actingAs($this->adminWith(['shop.cart.update']))
            ->putJson('/admin/api/shop/settings', $this->settingsPayload())
            ->assertOk()
            ->assertJsonPath('settings.telegram_token', TelegramNotifier::MASK);

        $this->assertStringNotContainsString('secret-token', $response->getContent());
        $this->assertStringNotContainsString('secret-token', json_encode(Shop::settings()));
        $this->assertSame('123456:secret-token', TelegramNotifier::token());
    }

    public function test_saving_the_mask_keeps_the_stored_token(): void
    {
        $admin = $this->adminWith(['shop.cart.update']);

        $this->actingAs($admin)->putJson('/admin/api/shop/settings', $this->settingsPayload())->assertOk();
        $this->actingAs($admin)->putJson('/admin/api/shop/settings', $this->settingsPayload([
            'telegram_token' => TelegramNotifier::MASK,
            'telegram_chat_id' => '111',
        ]))->assertOk();

        $this->assertSame('123456:secret-token', TelegramNotifier::token());
        $this->assertSame('111', TelegramNotifier::chatId());
    }

    public function test_enabling_telegram_needs_a_token_and_a_chat(): void
    {
        $this->actingAs($this->adminWith(['shop.cart.update']))
            ->putJson('/admin/api/shop/settings', $this->settingsPayload(['telegram_token' => '', 'telegram_chat_id' => '']))
            ->assertStatus(422)
            ->assertJsonValidationErrors(['telegram_token', 'telegram_chat_id']);
    }

    public function test_a_new_order_is_sent_to_the_chat(): void
    {
        Http::fake(['api.telegram.org/*' => Http::response(['ok' => true, 'result' => []])]);

        $this->shopSettings([
            'telegram_enabled' => true,
            'telegram_token' => TelegramNotifier::encrypt('123456:secret-token'),
            'telegram_chat_id' => '987654',
        ]);

        $product = $this->product(['price' => 1500], ['name' => 'Кресло']);

        Livewire::withCookies($this->cartCookie([$product->id => 2]))
            ->test(CartPage::class)
            ->set('customer.NAME', 'Иван')
            ->set('customer.PHONE', '+7 900 123-45-67')
            ->call('placeOrder')
            ->assertHasNoErrors();

        Http::assertSent(fn (Request $request) => str_contains($request->url(), 'bot123456:secret-token/sendMessage')
            && $request['chat_id'] === '987654'
            && str_contains($request['text'], 'Новый заказ №000001')
            && str_contains($request['text'], 'Кресло'));
    }

    public function test_a_broken_telegram_does_not_break_the_order(): void
    {
        Http::fake(['api.telegram.org/*' => Http::response(['ok' => false, 'description' => 'Unauthorized'], 401)]);

        $this->shopSettings([
            'telegram_enabled' => true,
            'telegram_token' => TelegramNotifier::encrypt('wrong'),
            'telegram_chat_id' => '987654',
        ]);

        $product = $this->product();

        Livewire::withCookies($this->cartCookie([$product->id => 1]))
            ->test(CartPage::class)
            ->set('customer.NAME', 'Иван')
            ->set('customer.PHONE', '+7 900 123-45-67')
            ->call('placeOrder')
            ->assertHasNoErrors()
            ->assertSet('placedNumber', '000001');
    }

    public function test_nothing_is_sent_while_telegram_is_off(): void
    {
        Http::fake();

        $product = $this->product();

        Livewire::withCookies($this->cartCookie([$product->id => 1]))
            ->test(CartPage::class)
            ->set('customer.NAME', 'Иван')
            ->set('customer.PHONE', '+7 900 123-45-67')
            ->call('placeOrder');

        Http::assertNothingSent();
    }

    public function test_the_check_button_explains_what_telegram_said(): void
    {
        Http::fake(['api.telegram.org/*' => Http::response(['ok' => false, 'description' => 'Bad Request: chat not found'], 400)]);

        $this->actingAs($this->adminWith(['shop.cart.update']))
            ->postJson('/admin/api/shop/settings/telegram-test', [
                'telegram_token' => '123456:secret-token',
                'telegram_chat_id' => '1',
            ])
            ->assertStatus(422)
            ->assertJsonPath('message', 'Чат не найден. Напишите боту /start или добавьте его в группу, затем проверьте chat id.');
    }

    public function test_the_check_button_uses_the_stored_token_behind_the_mask(): void
    {
        Http::fake(['api.telegram.org/*' => Http::response(['ok' => true])]);

        $this->shopSettings(['telegram_token' => TelegramNotifier::encrypt('123456:stored')]);

        $this->actingAs($this->adminWith(['shop.cart.update']))
            ->postJson('/admin/api/shop/settings/telegram-test', [
                'telegram_token' => TelegramNotifier::MASK,
                'telegram_chat_id' => '42',
            ])
            ->assertOk();

        Http::assertSent(fn (Request $request) => str_contains($request->url(), 'bot123456:stored/'));
    }
}
