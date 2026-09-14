<?php

namespace Tests\Feature;

use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Blade;
use Nexor\Cms\Enums\Currency;
use Nexor\Cms\Models\CatalogProduct;
use Nexor\Cms\Models\Iblock;
use Nexor\Cms\Models\IblockElement;
use Nexor\Cms\Models\IblockProperty;
use Nexor\Cms\Models\IblockType;
use Nexor\Cms\Support\CatalogManager;
use Tests\Concerns\CreatesAdminUsers;
use Tests\TestCase;

/**
 * Торговый каталог: цена, остатки, скидка и торговые предложения.
 */
class CatalogTest extends TestCase
{
    use CreatesAdminUsers, RefreshDatabase;

    /**
     * Включает «Торговый каталог» через ту же форму, что и панель.
     */
    protected function enableCatalog(Iblock $iblock, bool $enabled = true): void
    {
        $this->actingAs($this->superAdmin())->putJson("/admin/api/iblocks/{$iblock->id}", [
            'iblock_type_id' => $iblock->iblock_type_id,
            'code' => $iblock->code,
            'name' => $iblock->name,
            'is_active' => '1',
            'is_catalog' => $enabled ? '1' : '0',
        ])->assertOk();
    }

    protected function catalogue(): Iblock
    {
        $iblock = Iblock::factory()->create(['code' => 'katalog', 'name' => 'Каталог', 'is_active' => true]);

        $this->enableCatalog($iblock);

        return $iblock->refresh();
    }

    // ------------------------------------------------------ включение каталога

    public function test_the_catalog_switch_creates_a_linked_offers_infoblock(): void
    {
        $iblock = $this->catalogue();
        $offers = $iblock->offersIblock;

        $this->assertNotNull($offers);
        $this->assertSame('Предложения — Каталог', $offers->name);
        $this->assertSame($iblock->id, $offers->product_iblock_id);
        $this->assertSame($iblock->iblock_type_id, $offers->iblock_type_id);
    }

    public function test_saving_the_catalog_again_does_not_duplicate_offers(): void
    {
        $iblock = $this->catalogue();

        $this->enableCatalog($iblock);
        $this->enableCatalog($iblock);

        $this->assertSame(1, Iblock::query()->where('product_iblock_id', $iblock->id)->count());
    }

    public function test_turning_the_catalog_off_keeps_the_offers(): void
    {
        $iblock = $this->catalogue();
        $offersId = $iblock->offers_iblock_id;

        $this->enableCatalog($iblock, false);

        // Снятая галочка — не повод уничтожать данные.
        $this->assertNotNull(Iblock::query()->find($offersId));

        $this->enableCatalog($iblock);

        $this->assertSame($offersId, $iblock->refresh()->offers_iblock_id);
    }

    public function test_the_offers_follow_a_renamed_catalog(): void
    {
        $iblock = $this->catalogue();

        $iblock->update(['name' => 'Магазин']);
        CatalogManager::sync($iblock);

        $this->assertSame('Предложения — Магазин', $iblock->offersIblock->refresh()->name);
    }

    public function test_offers_cannot_have_offers_of_their_own(): void
    {
        $offers = $this->catalogue()->offersIblock;

        $offers->update(['is_catalog' => true]);

        $this->assertNull(CatalogManager::sync($offers));
        $this->assertNull($offers->refresh()->offers_iblock_id);
    }

    public function test_roles_that_manage_the_catalog_get_the_offers_too(): void
    {
        $type = IblockType::factory()->create();
        $iblock = Iblock::factory()->create(['iblock_type_id' => $type->id, 'code' => 'shop']);

        $manager = $this->grantIblock($this->adminWith(), $iblock, ['view', 'update']);

        $this->enableCatalog($iblock);

        $offers = $iblock->refresh()->offersIblock;

        // Без переноса прав менеджер упёрся бы в 403 на вкладке «Предложения».
        $this->assertTrue($manager->fresh()->hasPermission($offers->permissionCode('view')));
        $this->assertTrue($manager->fresh()->hasPermission($offers->permissionCode('update')));
        $this->assertFalse($manager->fresh()->hasPermission($offers->permissionCode('delete')));
    }

    // ------------------------------------------------------------ форма элемента

    public function test_a_catalog_form_gains_the_commerce_tabs(): void
    {
        $iblock = $this->catalogue();

        $tabs = $this->actingAs($this->superAdmin())
            ->getJson("/admin/api/iblocks/{$iblock->id}/schema")
            ->assertOk()
            ->json('form_tabs');

        $this->assertSame(
            ['main', 'seo', 'preview', 'detail', 'sections', 'price', 'stock', 'discount', 'offers'],
            array_column($tabs, 'key'),
        );
    }

    public function test_offers_get_prices_but_no_offers_tab(): void
    {
        $offers = $this->catalogue()->offersIblock;

        $keys = array_column($this->actingAs($this->superAdmin())
            ->getJson("/admin/api/iblocks/{$offers->id}/schema")
            ->json('form_tabs'), 'key');

        $this->assertContains('price', $keys);
        $this->assertContains('stock', $keys);
        $this->assertNotContains('offers', $keys);
    }

    public function test_a_plain_infoblock_has_no_commerce_tabs(): void
    {
        $iblock = Iblock::factory()->create();

        $keys = array_column($this->actingAs($this->superAdmin())
            ->getJson("/admin/api/iblocks/{$iblock->id}/schema")
            ->json('form_tabs'), 'key');

        $this->assertNotContains('price', $keys);
    }

    public function test_an_element_saves_its_price_stock_and_discount(): void
    {
        $iblock = $this->catalogue();

        $this->actingAs($this->superAdmin())->postJson("/admin/api/iblocks/{$iblock->id}/elements", [
            'name' => 'Стул',
            'catalog' => [
                'price' => 1000,
                'discount_percent' => 15,
                'quantity' => 7,
                'measure' => 'шт',
                'ratio' => 1,
                'quantity_trace' => true,
                'can_buy_zero' => false,
            ],
        ])->assertCreated()
            ->assertJsonPath('data.catalog.final_price', 850)
            ->assertJsonPath('data.catalog.is_available', true);

        $catalog = IblockElement::query()->where('name', 'Стул')->firstOrFail()->catalog;

        $this->assertSame('1000.00', $catalog->price);
        $this->assertTrue($catalog->quantity_trace);
    }

    public function test_a_discount_over_one_hundred_percent_is_refused(): void
    {
        $iblock = $this->catalogue();

        $this->actingAs($this->superAdmin())->postJson("/admin/api/iblocks/{$iblock->id}/elements", [
            'name' => 'Стул',
            'catalog' => ['price' => 1000, 'discount_percent' => 120],
        ])->assertStatus(422)->assertJsonValidationErrors('catalog.discount_percent');
    }

    public function test_editing_the_name_alone_keeps_the_price(): void
    {
        $iblock = $this->catalogue();
        $element = IblockElement::factory()->for($iblock)->create(['name' => 'Стул']);
        CatalogProduct::factory()->create(['element_id' => $element->id, 'price' => 500]);

        // Клиент API, который правит одно название, не должен обнулять цену.
        $this->actingAs($this->superAdmin())
            ->putJson("/admin/api/iblocks/{$iblock->id}/elements/{$element->id}", ['name' => 'Кресло'])
            ->assertOk();

        $this->assertSame('500.00', $element->catalog->refresh()->price);
    }

    public function test_a_price_carries_its_currency(): void
    {
        $iblock = $this->catalogue();

        $this->actingAs($this->superAdmin())->postJson("/admin/api/iblocks/{$iblock->id}/elements", [
            'name' => 'Стул',
            'catalog' => ['price' => 100, 'currency' => 'USD'],
        ])->assertCreated()
            ->assertJsonPath('data.catalog.currency', 'USD')
            ->assertJsonPath('data.catalog.currency_symbol', '$');
    }

    public function test_a_currency_outside_the_list_is_refused(): void
    {
        $iblock = $this->catalogue();

        $this->actingAs($this->superAdmin())->postJson("/admin/api/iblocks/{$iblock->id}/elements", [
            'name' => 'Стул',
            'catalog' => ['price' => 100, 'currency' => 'BTC'],
        ])->assertStatus(422)->assertJsonValidationErrors('catalog.currency');
    }

    public function test_a_product_can_be_marked_as_having_offers(): void
    {
        $iblock = $this->catalogue();

        $this->actingAs($this->superAdmin())->postJson("/admin/api/iblocks/{$iblock->id}/elements", [
            'name' => 'Футболка',
            'catalog' => ['type' => 'with_offers'],
        ])->assertCreated()->assertJsonPath('data.catalog.type', 'with_offers');

        $this->assertTrue(IblockElement::query()->where('name', 'Футболка')->firstOrFail()->catalog->usesOffers());
    }

    public function test_a_product_shows_its_offers_by_properties_until_told_otherwise(): void
    {
        $iblock = $this->catalogue();
        $admin = $this->superAdmin();

        $created = $this->actingAs($admin)->postJson("/admin/api/iblocks/{$iblock->id}/elements", [
            'name' => 'Футболка',
            'catalog' => ['type' => 'with_offers'],
        ])->assertCreated()->assertJsonPath('data.catalog.offers_by_properties', true);

        $this->actingAs($admin)->putJson("/admin/api/iblocks/{$iblock->id}/elements/{$created->json('data.id')}", [
            'name' => 'Футболка',
            'catalog' => ['type' => 'with_offers', 'offers_by_properties' => false],
        ])->assertOk()->assertJsonPath('data.catalog.offers_by_properties', false);

        $this->assertTrue(IblockElement::query()->findOrFail($created->json('data.id'))->catalog->listsOffers());
    }

    public function test_only_a_product_chooses_its_type(): void
    {
        $iblock = $this->catalogue();

        $keys = fn (int $id) => array_column($this->actingAs($this->superAdmin())
            ->getJson("/admin/api/iblocks/{$id}/schema")
            ->json('form_fields'), 'key');

        $this->assertContains('catalog.type', $keys($iblock->id));
        $this->assertContains('catalog.currency', $keys($iblock->id));

        // У предложения своих предложений нет, а валюта есть.
        $this->assertNotContains('catalog.type', $keys($iblock->offers_iblock_id));
        $this->assertContains('catalog.currency', $keys($iblock->offers_iblock_id));
    }

    // ------------------------------------------------------ торговые предложения

    public function test_an_offer_belongs_to_a_product(): void
    {
        $iblock = $this->catalogue();
        $product = IblockElement::factory()->for($iblock)->create(['name' => 'Футболка']);

        $this->actingAs($this->superAdmin())->postJson("/admin/api/iblocks/{$iblock->offers_iblock_id}/elements", [
            'name' => 'Футболка, M',
            'parent_element_id' => $product->id,
            'catalog' => ['price' => 1990, 'quantity' => 3],
        ])->assertCreated()->assertJsonPath('data.catalog.parent_element_id', $product->id);

        $offers = $this->actingAs($this->superAdmin())
            ->getJson("/admin/api/iblocks/{$iblock->id}/elements/{$product->id}/offers")
            ->assertOk();

        $this->assertSame(['Футболка, M'], array_column($offers->json('data'), 'name'));
        $this->assertSame($iblock->offers_iblock_id, $offers->json('offers_iblock.id'));
    }

    public function test_an_offer_cannot_point_at_a_product_of_another_catalog(): void
    {
        $iblock = $this->catalogue();
        $stranger = IblockElement::factory()->create();

        $this->actingAs($this->superAdmin())->postJson("/admin/api/iblocks/{$iblock->offers_iblock_id}/elements", [
            'name' => 'Чужое',
            'parent_element_id' => $stranger->id,
            'catalog' => ['price' => 1],
        ])->assertStatus(422)->assertJsonValidationErrors('parent_element_id');
    }

    public function test_the_offers_tab_brings_the_properties_of_the_offers_infoblock(): void
    {
        $iblock = $this->catalogue();
        $offersIblock = $iblock->offersIblock;

        IblockProperty::factory()->for($offersIblock)->create(['code' => 'SIZE', 'name' => 'Размер']);

        $product = IblockElement::factory()->for($iblock)->create();
        $offer = IblockElement::factory()->create(['iblock_id' => $offersIblock->id]);
        CatalogProduct::factory()->create(['element_id' => $offer->id, 'parent_element_id' => $product->id]);

        $response = $this->actingAs($this->superAdmin())
            ->getJson("/admin/api/iblocks/{$iblock->id}/elements/{$product->id}/offers")
            ->assertOk();

        // Из них собирается список колонок таблицы и полей попапа.
        $this->assertSame(['SIZE'], array_column($response->json('properties'), 'code'));
        $this->assertArrayHasKey('properties', $response->json('data.0'));
    }

    public function test_an_existing_offer_can_be_attached_to_a_product(): void
    {
        $iblock = $this->catalogue();
        $product = IblockElement::factory()->for($iblock)->create(['name' => 'Футболка']);
        $offer = IblockElement::factory()->create(['iblock_id' => $iblock->offers_iblock_id, 'name' => 'Размер L']);

        $this->actingAs($this->superAdmin())
            ->postJson("/admin/api/iblocks/{$iblock->id}/elements/{$product->id}/offers", ['offers' => [$offer->id]])
            ->assertOk();

        $this->assertSame($product->id, $offer->refresh()->catalog->parent_element_id);

        // Товар, у которого появились предложения, своей цены больше не имеет.
        $this->assertTrue($product->refresh()->catalog->usesOffers());
    }

    public function test_an_element_of_another_infoblock_cannot_be_attached_as_an_offer(): void
    {
        $iblock = $this->catalogue();
        $product = IblockElement::factory()->for($iblock)->create();
        $stranger = IblockElement::factory()->create();

        $this->actingAs($this->superAdmin())
            ->postJson("/admin/api/iblocks/{$iblock->id}/elements/{$product->id}/offers", ['offers' => [$stranger->id]])
            ->assertStatus(422)->assertJsonValidationErrors('offers.0');
    }

    public function test_attaching_offers_needs_the_right_to_edit_them(): void
    {
        $iblock = $this->catalogue();
        $product = IblockElement::factory()->for($iblock)->create();
        $offer = IblockElement::factory()->create(['iblock_id' => $iblock->offers_iblock_id]);

        // Права на каталог выданы уже после включения, на предложения — нет.
        $manager = $this->grantIblock($this->adminWith(), $iblock, ['view', 'update']);

        $this->actingAs($manager)
            ->postJson("/admin/api/iblocks/{$iblock->id}/elements/{$product->id}/offers", ['offers' => [$offer->id]])
            ->assertForbidden();
    }

    public function test_the_offers_list_needs_access_to_the_offers_infoblock(): void
    {
        $iblock = $this->catalogue();
        $product = IblockElement::factory()->for($iblock)->create();

        $viewer = $this->grantIblock($this->adminWith(), $iblock, ['view']);

        // Права на каталог выданы уже после включения, на предложения — нет.
        $this->actingAs($viewer)
            ->getJson("/admin/api/iblocks/{$iblock->id}/elements/{$product->id}/offers")
            ->assertForbidden();
    }

    public function test_deleting_a_product_takes_its_offers_along(): void
    {
        $iblock = $this->catalogue();
        $product = IblockElement::factory()->for($iblock)->create();
        $offer = IblockElement::factory()->create(['iblock_id' => $iblock->offers_iblock_id]);

        CatalogProduct::factory()->create(['element_id' => $offer->id, 'parent_element_id' => $product->id]);

        $product->delete();

        $this->assertSoftDeleted($offer);
    }

    // -------------------------------------------------------------- наличие

    public function test_without_stock_tracking_an_item_is_always_available(): void
    {
        $this->assertTrue(CatalogProduct::factory()->make(['quantity' => 0, 'quantity_trace' => false])->isAvailable());
    }

    public function test_with_stock_tracking_an_empty_item_is_unavailable_unless_backorders_are_allowed(): void
    {
        $empty = CatalogProduct::factory()->traced(0)->make();

        $this->assertFalse($empty->isAvailable());

        $empty->can_buy_zero = true;

        $this->assertTrue($empty->isAvailable());
        $this->assertTrue(CatalogProduct::factory()->traced(5)->make()->isAvailable());
    }

    // ------------------------------------------------------------- витрина

    public function test_the_card_shows_the_discounted_price_and_the_old_one(): void
    {
        $iblock = $this->catalogue();
        $element = IblockElement::factory()->for($iblock)->create(['name' => 'Стул']);

        CatalogProduct::factory()->discounted(10)->create(['element_id' => $element->id, 'price' => 2000]);

        $html = Blade::render('<x-nexor::catalog.section iblock="katalog" />');

        $this->assertStringContainsString('1 800 ₽', $html);
        $this->assertStringContainsString('line-through', $html);
    }

    public function test_the_card_shows_the_price_in_its_own_currency(): void
    {
        $iblock = $this->catalogue();
        $element = IblockElement::factory()->for($iblock)->create(['name' => 'Стул']);

        CatalogProduct::factory()->create([
            'element_id' => $element->id,
            'price' => 2000,
            'currency' => Currency::EUR,
        ]);

        $this->assertStringContainsString('2 000 €', Blade::render('<x-nexor::catalog.section iblock="katalog" />'));
    }

    public function test_the_detail_page_lists_the_offers(): void
    {
        $iblock = $this->catalogue();
        $product = IblockElement::factory()->for($iblock)->create(['name' => 'Футболка']);
        $offer = IblockElement::factory()->create(['iblock_id' => $iblock->offers_iblock_id, 'name' => 'Размер L']);

        CatalogProduct::factory()->create(['element_id' => $product->id, 'price' => 1500]);
        CatalogProduct::factory()->create(['element_id' => $offer->id, 'parent_element_id' => $product->id, 'price' => 1700]);

        $html = Blade::render('<x-nexor::catalog.element :element="$element" />', ['element' => $product]);

        $this->assertStringContainsString('Размер L', $html);
        $this->assertStringContainsString('1 700 ₽', $html);
    }

    public function test_a_product_listing_its_offers_shows_every_offer_without_a_switcher(): void
    {
        $iblock = $this->catalogue();
        $product = IblockElement::factory()->for($iblock)->create(['name' => 'Футболка']);

        CatalogProduct::factory()->withOffers()->create(['element_id' => $product->id, 'offers_by_properties' => false]);

        foreach (['Размер M' => 1600, 'Размер L' => 1700] as $name => $price) {
            $offer = IblockElement::factory()->create(['iblock_id' => $iblock->offers_iblock_id, 'name' => $name, 'is_active' => true]);
            CatalogProduct::factory()->create(['element_id' => $offer->id, 'parent_element_id' => $product->id, 'price' => $price]);
        }

        $html = Blade::render('<x-nexor::catalog.element :element="$element" />', ['element' => $product->fresh()]);

        $this->assertStringContainsString('1 600 ₽', $html);
        $this->assertStringContainsString('1 700 ₽', $html);
        $this->assertStringNotContainsString('data-offer-link', $html);
    }
}
