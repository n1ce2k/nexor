<?php

namespace Tests\Feature\Shop;

use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\File;
use Livewire\Livewire;
use Nexor\Shop\Livewire\AddToCart;
use Nexor\Shop\Livewire\CartPage;
use Tests\Concerns\BuildsShop;
use Tests\Concerns\PreservesPublishedViews;
use Tests\TestCase;

/**
 * Консольные команды магазина и поведение кнопки «В корзину».
 */
class ShopCommandsTest extends TestCase
{
    use BuildsShop, PreservesPublishedViews, RefreshDatabase;

    protected string $layout;

    protected function setUp(): void
    {
        parent::setUp();

        $this->layout = resource_path('views/shop-test-layout.blade.php');

        // Своими шаблонами сайта тест не рискует: копирует в сторону и возвращает.
        File::deleteDirectory($this->preserveViews('vendor/nexor-shop'));
    }

    protected function tearDown(): void
    {
        File::delete($this->layout);
        $this->restorePreservedViews();

        parent::tearDown();
    }

    public function test_the_component_command_copies_a_template_with_its_parts(): void
    {
        $this->artisan('nexor-shop:component', ['component' => 'cart-page'])->assertSuccessful();

        $this->assertFileExists(resource_path('views/vendor/nexor-shop/livewire/cart-page.blade.php'));
        $this->assertFileExists(resource_path('views/vendor/nexor-shop/partials/lines.blade.php'));
    }

    public function test_the_component_command_never_overwrites_without_force(): void
    {
        $target = resource_path('views/vendor/nexor-shop/livewire/cart-button.blade.php');

        File::ensureDirectoryExists(dirname($target));
        File::put($target, 'моя кнопка');

        $this->artisan('nexor-shop:component', ['component' => 'cart-button'])->assertSuccessful();

        $this->assertSame('моя кнопка', File::get($target));
    }

    public function test_an_unknown_component_is_reported(): void
    {
        $this->artisan('nexor-shop:component', ['component' => 'nope'])->assertFailed();
    }

    public function test_install_puts_the_cart_into_the_layout(): void
    {
        File::put($this->layout, "<header>{{-- nexor-shop:cart-button --}}</header>\n<body>\n</body>");

        $this->artisan('nexor-shop:install', ['--layout' => 'shop-test-layout'])->assertSuccessful();

        $content = File::get($this->layout);

        $this->assertStringContainsString('<livewire:nexor-shop::cart-button />', $content);
        $this->assertStringContainsString("<livewire:nexor-shop::cart-offcanvas />\n</body>", $content);
    }

    public function test_install_does_not_add_the_cart_twice(): void
    {
        File::put($this->layout, "<body>\n    <livewire:nexor-shop::cart-offcanvas />\n</body>");

        $this->artisan('nexor-shop:install', ['--layout' => 'shop-test-layout'])->assertSuccessful();

        $this->assertSame(1, substr_count(File::get($this->layout), 'cart-offcanvas'));
    }

    // ------------------------------------------------------ кнопка «В корзину»

    public function test_changing_the_cart_tells_other_tabs(): void
    {
        $product = $this->product();

        Livewire::test(AddToCart::class, ['elementId' => $product->id])
            ->call('add')
            ->assertDispatched('cart-changed');

        Livewire::withCookies($this->cartCookie([$product->id => 2]))
            ->test(CartPage::class)
            ->call('decrease', $product->id)
            ->assertDispatched('cart-changed');
    }

    public function test_a_redraw_requested_by_another_tab_is_not_sent_back(): void
    {
        $product = $this->product();

        // Сигнал из другой вкладки только перерисовывает — иначе вкладки
        // перекидывали бы его друг другу бесконечно.
        Livewire::withCookies($this->cartCookie([$product->id => 1]))
            ->test(CartPage::class)
            ->dispatch('cart-updated')
            ->assertNotDispatched('cart-changed');
    }

    public function test_the_offcanvas_opens_on_add_by_default(): void
    {
        $product = $this->product();

        Livewire::test(AddToCart::class, ['elementId' => $product->id])
            ->call('add')
            ->assertDispatched('cart-open')
            ->assertNotDispatched('cart-added');
    }

    public function test_the_offcanvas_can_stay_closed_and_the_button_says_added(): void
    {
        $this->shopSettings(['open_on_add' => false, 'added_feedback' => 'toast']);

        $product = $this->product(['price' => 500], ['name' => 'Лампа']);

        Livewire::test(AddToCart::class, ['elementId' => $product->id])
            ->call('add')
            ->assertNotDispatched('cart-open')
            ->assertDispatched('cart-added', elementId: $product->id, name: 'Лампа')
            ->assertSee('добавлен в корзину');
    }
}
