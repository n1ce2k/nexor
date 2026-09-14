<?php

namespace Tests\Feature\Shop;

use Illuminate\Foundation\Testing\RefreshDatabase;
use Livewire\Livewire;
use Nexor\Cms\Models\IblockSection;
use Nexor\Shop\Enums\PromocodeScope;
use Nexor\Shop\Livewire\CartPage;
use Nexor\Shop\Models\Promocode;
use Tests\Concerns\BuildsShop;
use Tests\TestCase;

/**
 * Промокоды корзины Ultimate.
 */
class PromocodeTest extends TestCase
{
    use BuildsShop, RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();

        $this->ultimate();
    }

    public function test_a_percent_code_discounts_the_order(): void
    {
        $product = $this->product(['price' => 2000]);
        Promocode::factory()->percent(10)->create(['code' => 'SALE10']);

        $cart = $this->emptyCart();
        $cart->add($product->id);
        $cart->setPromocode('sale10');

        $summary = $cart->summary();

        $this->assertSame(200.0, $summary->discount);
        $this->assertSame(1800.0, $summary->total());
    }

    public function test_a_fixed_code_never_goes_below_zero(): void
    {
        $product = $this->product(['price' => 300]);
        Promocode::factory()->fixed(1000)->create(['code' => 'BIG']);

        $cart = $this->emptyCart();
        $cart->add($product->id);
        $cart->setPromocode('BIG');

        $this->assertSame(0.0, $cart->summary()->total());
    }

    public function test_a_code_below_its_minimum_sum_is_refused(): void
    {
        $product = $this->product(['price' => 300]);
        Promocode::factory()->create(['code' => 'MIN', 'min_sum' => 5000]);

        $cart = $this->emptyCart();
        $cart->add($product->id);
        $cart->setPromocode('MIN');

        $summary = $cart->summary();

        $this->assertSame(0.0, $summary->discount);
        $this->assertStringContainsString('от', $summary->promocodeError);
    }

    public function test_an_expired_or_used_up_code_is_refused(): void
    {
        $product = $this->product();
        Promocode::factory()->create(['code' => 'OLD', 'ends_at' => now()->subDay()]);
        Promocode::factory()->create(['code' => 'USED', 'usage_limit' => 1, 'used_count' => 1]);

        $cart = $this->emptyCart();
        $cart->add($product->id);

        $cart->setPromocode('OLD');
        $this->assertSame('Срок действия промокода истёк.', $cart->summary()->promocodeError);

        $cart->setPromocode('USED');
        $this->assertSame('Промокод больше не действует.', $cart->summary()->promocodeError);
    }

    public function test_a_section_code_covers_subsections_only(): void
    {
        $iblock = $this->catalogIblock();
        $furniture = IblockSection::factory()->for($iblock)->create(['code' => 'mebel']);
        $chairs = IblockSection::factory()->childOf($furniture)->create(['code' => 'stulya']);
        $lamps = IblockSection::factory()->for($iblock)->create(['code' => 'svet']);

        $chair = $this->product(['price' => 1000], ['section_id' => $chairs->id]);
        $lamp = $this->product(['price' => 1000], ['section_id' => $lamps->id]);

        Promocode::factory()->percent(50)->create([
            'code' => 'MEBEL',
            'scope' => PromocodeScope::Sections,
            'section_ids' => [$furniture->id],
        ]);

        $cart = $this->emptyCart();
        $cart->add($chair->id);
        $cart->add($lamp->id);
        $cart->setPromocode('MEBEL');

        $summary = $cart->summary();

        $this->assertSame(500.0, $summary->discount);
        $this->assertSame(500.0, $summary->lines->firstWhere('id', $chair->id)->discount);
        $this->assertSame(0.0, $summary->lines->firstWhere('id', $lamp->id)->discount);
    }

    public function test_codes_do_nothing_in_a_basic_cart(): void
    {
        $this->shopSettings(['edition' => 'basic']);

        $product = $this->product(['price' => 2000]);
        Promocode::factory()->percent(10)->create(['code' => 'SALE10']);

        $cart = $this->emptyCart();
        $cart->add($product->id);
        $cart->setPromocode('SALE10');

        $this->assertSame(0.0, $cart->summary()->discount);
    }

    public function test_the_cart_page_applies_a_code(): void
    {
        $product = $this->product(['price' => 2000]);
        Promocode::factory()->percent(10)->create(['code' => 'SALE10']);

        Livewire::withCookies($this->cartCookie([$product->id => 1]))
            ->test(CartPage::class)
            ->set('promocodeInput', 'sale10')
            ->call('applyPromocode')
            ->assertHasNoErrors()
            ->assertSet('promocodeMessage', 'Промокод применён: скидка 200 ₽.');
    }

    public function test_the_cart_page_explains_a_wrong_code(): void
    {
        $product = $this->product();

        Livewire::withCookies($this->cartCookie([$product->id => 1]))
            ->test(CartPage::class)
            ->set('promocodeInput', 'NOPE')
            ->call('applyPromocode')
            ->assertHasErrors(['promocode']);
    }
}
