<?php

namespace Tests\Feature;

use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\File;
use Nexor\Cms\Enums\PaginationTemplate;
use Nexor\Cms\Models\CatalogProduct;
use Nexor\Cms\Models\Iblock;
use Nexor\Cms\Models\IblockElement;
use Nexor\Cms\Models\IblockSection;
use Nexor\Cms\Support\CatalogManager;
use Nexor\Cms\Support\PageGenerator;
use Tests\Concerns\CreatesAdminUsers;
use Tests\TestCase;

/**
 * A page-backed infoblock: the folder it scaffolds, the listing it serves, the
 * paging templates and the detail page behind each element.
 */
class IblockPageTest extends TestCase
{
    use CreatesAdminUsers, RefreshDatabase;

    /** Pages are written into a throwaway folder so the app's views stay clean. */
    protected string $pagesDirectory = 'nexor-test-pages';

    protected function setUp(): void
    {
        parent::setUp();

        config(['nexor.pages.directory' => $this->pagesDirectory]);
    }

    protected function tearDown(): void
    {
        File::deleteDirectory(resource_path('views/'.$this->pagesDirectory));

        parent::tearDown();
    }

    /**
     * A published infoblock with its page already on disk.
     *
     * @param  array<string, mixed>  $attributes
     */
    protected function pagedIblock(array $attributes = []): Iblock
    {
        $iblock = Iblock::factory()->create(array_merge([
            'code' => 'katalog-test',
            'name' => 'Каталог',
            'has_page' => true,
            'is_active' => true,
        ], $attributes));

        PageGenerator::create($iblock);

        return $iblock;
    }

    /**
     * @return array<int, IblockElement>
     */
    protected function elements(Iblock $iblock, int $count): array
    {
        return collect(range(1, $count))
            ->map(fn (int $index) => IblockElement::factory()->for($iblock)->create([
                'code' => 'item-'.$index,
                'name' => 'Товар '.$index,
                'sort' => $index * 10,
                'is_active' => true,
            ]))
            ->all();
    }

    // ------------------------------------------------------------ scaffolding

    public function test_the_page_switch_scaffolds_a_listing_and_a_detail_page(): void
    {
        $iblock = $this->pagedIblock();

        foreach (PageGenerator::FILES as $file) {
            $this->assertTrue(PageGenerator::exists($iblock, $file), "{$file}.blade.php не создан");
        }
    }

    public function test_the_generated_page_only_calls_components(): void
    {
        $iblock = $this->pagedIblock();
        $listing = File::get(PageGenerator::path($iblock));

        $this->assertStringContainsString('<x-nexor::catalog.section iblock="katalog-test"', $listing);
        $this->assertStringContainsString('<x-nexor::breadcrumbs iblock="katalog-test"', $listing);

        // Вёрстка живёт в шаблонах компонентов, а не копируется в страницу.
        $this->assertStringNotContainsString('@forelse', $listing);
    }

    public function test_a_sectioned_infoblock_gets_the_tree_and_the_filter(): void
    {
        $withSections = File::get(PageGenerator::path($this->pagedIblock()));
        $plain = File::get(PageGenerator::path(
            $this->pagedIblock(['code' => 'plain-page-test', 'has_sections' => false]),
        ));

        $this->assertStringContainsString('<x-nexor::menu.sections', $withSections);
        $this->assertStringContainsString('<x-nexor::catalog.filter', $withSections);
        $this->assertStringNotContainsString('<x-nexor::menu.sections', $plain);
    }

    public function test_the_paging_template_is_a_setting_rather_than_a_file(): void
    {
        $iblock = $this->pagedIblock(['pagination_template' => PaginationTemplate::ButtonLoad]);
        $admin = $this->adminWith(['iblocks.update']);

        File::put(PageGenerator::path($iblock), 'моя разметка');

        $this->actingAs($admin)->putJson("/admin/api/iblocks/{$iblock->id}", [
            'iblock_type_id' => $iblock->iblock_type_id,
            'code' => $iblock->code,
            'name' => $iblock->name,
            'has_page' => '1',
            'is_active' => '1',
            'pagination_template' => PaginationTemplate::Simple->value,
        ])->assertOk();

        // Смена шаблона меняет настройку, а не переписывает чьи-то файлы.
        $this->assertSame('моя разметка', File::get(PageGenerator::path($iblock)));
        $this->assertSame(PaginationTemplate::Simple, $iblock->refresh()->pagination_template);
    }

    // ---------------------------------------------------------------- listing

    public function test_the_listing_pages_its_elements(): void
    {
        $iblock = $this->pagedIblock(['per_page' => 2]);
        $this->elements($iblock, 5);

        $this->get('/katalog-test')
            ->assertOk()
            ->assertSee('Товар 1')
            ->assertSee('Товар 2')
            ->assertDontSee('Товар 3')
            ->assertSee('?page=2', false);

        $this->get('/katalog-test?page=2')
            ->assertOk()
            ->assertSee('Товар 3')
            ->assertDontSee('Товар 1');
    }

    public function test_the_load_more_switch_sets_the_chunk_size(): void
    {
        $iblock = $this->pagedIblock([
            'per_page' => 20,
            'has_load_more' => true,
            'load_more_size' => 3,
        ]);

        $this->assertSame(3, $iblock->pageSize());

        $this->elements($iblock, 5);

        $this->get('/katalog-test')
            ->assertOk()
            ->assertSee('Товар 3')
            ->assertDontSee('Товар 4')
            ->assertSee('Показать ещё');
    }

    public function test_the_numbered_template_hides_the_button_while_the_switch_is_off(): void
    {
        $iblock = $this->pagedIblock(['per_page' => 2]);
        $this->elements($iblock, 5);

        $this->get('/katalog-test')->assertOk()->assertDontSee('Показать ещё');
    }

    public function test_an_inactive_element_stays_out_of_the_listing(): void
    {
        $iblock = $this->pagedIblock();
        $this->elements($iblock, 2);

        IblockElement::query()->where('code', 'item-1')->update(['is_active' => false]);

        $this->get('/katalog-test')->assertOk()->assertDontSee('Товар 1')->assertSee('Товар 2');
    }

    public function test_a_page_without_a_description_does_not_leak_its_content(): void
    {
        $iblock = $this->pagedIblock(['description' => null]);
        $this->sections($iblock);
        $this->elements($iblock, 1);

        $before = ob_get_level();

        $this->get('/katalog-test')->assertOk();
        $this->get('/katalog-test/mebel')->assertOk();

        // `@section('description', null)` Blade понимает как «открыть секцию» и
        // ждёт `@endsection`: буфер остаётся открытым, а содержимое страницы
        // утекает в мета-описание вместо того, чтобы отрисоваться.
        $this->assertSame($before, ob_get_level());
    }

    // ---------------------------------------------------------------- разделы

    /** Раздел «Мебель» с вложенным «Стулья». */
    protected function sections(Iblock $iblock): array
    {
        $mebel = IblockSection::factory()->create([
            'iblock_id' => $iblock->id, 'name' => 'Мебель', 'code' => 'mebel',
        ]);

        $stulya = IblockSection::factory()->create([
            'iblock_id' => $iblock->id, 'parent_id' => $mebel->id, 'name' => 'Стулья', 'code' => 'stulya',
        ]);

        return [$mebel, $stulya];
    }

    public function test_a_section_opens_at_its_own_address(): void
    {
        $iblock = $this->pagedIblock();
        [$mebel] = $this->sections($iblock);

        $this->elements($iblock, 1)[0]->update(['section_id' => $mebel->id]);

        $this->get('/katalog-test/mebel')->assertOk()->assertSee('Мебель');
    }

    public function test_a_nested_section_keeps_its_parents_in_the_address(): void
    {
        $iblock = $this->pagedIblock();
        [, $stulya] = $this->sections($iblock);

        $this->assertSame(url('/katalog-test/mebel/stulya'), $stulya->url());

        $this->get('/katalog-test/mebel/stulya')->assertOk()->assertSee('Стулья');
    }

    public function test_a_section_reached_by_the_wrong_path_is_not_found(): void
    {
        $iblock = $this->pagedIblock();
        $this->sections($iblock);

        // «Стулья» лежит внутри «Мебели», а не в корне.
        $this->get('/katalog-test/stulya')->assertNotFound();
    }

    public function test_a_section_shows_only_its_own_elements(): void
    {
        $iblock = $this->pagedIblock();
        [$mebel] = $this->sections($iblock);

        $elements = $this->elements($iblock, 2);
        $elements[0]->update(['section_id' => $mebel->id]);

        $this->get('/katalog-test/mebel')
            ->assertOk()
            ->assertSee('Товар 1')
            ->assertDontSee('Товар 2');
    }

    public function test_an_element_lives_inside_its_section_path(): void
    {
        $iblock = $this->pagedIblock();
        [, $stulya] = $this->sections($iblock);

        $element = $this->elements($iblock, 1)[0];
        $element->update(['section_id' => $stulya->id]);

        $this->assertSame(url('/katalog-test/mebel/stulya/item-1'), $element->refresh()->url());

        $this->get('/katalog-test/mebel/stulya/item-1')->assertOk()->assertSee('Товар 1');
    }

    public function test_an_element_reached_through_a_foreign_section_goes_to_its_own_address(): void
    {
        $iblock = $this->pagedIblock();
        [$mebel, $stulya] = $this->sections($iblock);

        $element = $this->elements($iblock, 1)[0];
        $element->update(['section_id' => $stulya->id]);

        // Одна страница — один адрес: из чужого раздела и без раздела — 301 на канонический.
        $this->get('/katalog-test/mebel/item-1')->assertRedirect(url('/katalog-test/mebel/stulya/item-1'))->assertStatus(301);
        $this->get('/katalog-test/item-1?utm=1')->assertRedirect(url('/katalog-test/mebel/stulya/item-1?utm=1'));
    }

    public function test_a_flat_infoblock_serves_elements_right_under_itself(): void
    {
        $iblock = $this->pagedIblock(['element_url' => 'flat']);
        [, $stulya] = $this->sections($iblock);

        $element = $this->elements($iblock, 1)[0];
        $element->update(['section_id' => $stulya->id]);

        $this->assertSame(url('/katalog-test/item-1'), $element->refresh()->url());

        $this->get('/katalog-test/item-1')->assertOk()->assertSee('Товар 1');
        $this->get('/katalog-test/mebel/stulya/item-1')->assertRedirect(url('/katalog-test/item-1'))->assertStatus(301);
    }

    public function test_the_element_address_is_chosen_in_the_infoblock_form(): void
    {
        $iblock = $this->pagedIblock();

        $this->actingAs($this->superAdmin())->putJson("/admin/api/iblocks/{$iblock->id}", [
            'iblock_type_id' => $iblock->iblock_type_id,
            'code' => $iblock->code,
            'name' => $iblock->name,
            'has_page' => '1',
            'element_url' => 'flat',
        ])->assertOk()->assertJsonPath('data.element_url', 'flat');

        $this->actingAs($this->superAdmin())->putJson("/admin/api/iblocks/{$iblock->id}", [
            'iblock_type_id' => $iblock->iblock_type_id,
            'code' => $iblock->code,
            'name' => $iblock->name,
            'element_url' => 'somewhere',
        ])->assertStatus(422)->assertJsonValidationErrors('element_url');
    }

    // -------------------------------------------------------------- предложения

    /**
     * Товар каталога с двумя предложениями.
     *
     * @return array{0: IblockElement, 1: IblockElement, 2: IblockElement}
     */
    protected function productWithOffers(Iblock $iblock): array
    {
        $iblock->update(['is_catalog' => true]);
        $offers = CatalogManager::sync($iblock->refresh());

        $product = $this->elements($iblock, 1)[0];
        CatalogProduct::factory()->withOffers()->create(['element_id' => $product->id]);

        $made = [];

        foreach (['krasnyy' => 'Красный', 'siniy' => 'Синий'] as $code => $name) {
            $offer = IblockElement::factory()->create([
                'iblock_id' => $offers->id, 'code' => $code, 'name' => $name, 'is_active' => true,
            ]);
            CatalogProduct::factory()->create(['element_id' => $offer->id, 'parent_element_id' => $product->id, 'price' => 1000]);
            $made[] = $offer;
        }

        return [$product, ...$made];
    }

    public function test_an_offer_has_its_product_address_plus_its_code(): void
    {
        [$product, $red] = $this->productWithOffers($this->pagedIblock());

        $this->assertSame(url('/katalog-test/item-1/krasnyy'), $red->url());

        $this->get('/katalog-test/item-1/krasnyy')->assertOk()->assertSee('Красный');
    }

    public function test_an_offer_of_a_product_listing_its_offers_opens_the_product(): void
    {
        [$product, $red] = $this->productWithOffers($this->pagedIblock());

        $product->catalog->update(['offers_by_properties' => false]);

        $this->assertSame(url('/katalog-test/item-1'), $red->fresh()->url());

        $this->get('/katalog-test/item-1/krasnyy')->assertRedirect(url('/katalog-test/item-1'))->assertStatus(301);

        $this->get('/katalog-test/item-1')->assertOk()->assertSee('Красный')->assertSee('Синий');
    }

    public function test_an_unknown_offer_is_not_found(): void
    {
        $this->productWithOffers($this->pagedIblock());

        $this->get('/katalog-test/item-1/zelenyy')->assertNotFound();
        $this->get('/katalog-test/item-1/krasnyy/lishnee')->assertNotFound();
    }

    public function test_an_offer_of_another_product_is_not_served_under_this_one(): void
    {
        $iblock = $this->pagedIblock();
        [, $red] = $this->productWithOffers($iblock);

        IblockElement::factory()->for($iblock)->create(['code' => 'drugoy', 'is_active' => true]);

        $this->get('/katalog-test/drugoy/'.$red->code)->assertNotFound();
    }

    public function test_offers_are_hidden_below_standart(): void
    {
        $this->productWithOffers($this->pagedIblock());

        config(['nexor.license' => 'lite']);

        $this->get('/katalog-test/item-1/krasnyy')->assertNotFound();
    }

    public function test_renaming_a_section_moves_the_whole_branch(): void
    {
        $iblock = $this->pagedIblock();
        [$mebel, $stulya] = $this->sections($iblock);

        $mebel->update(['code' => 'furniture']);

        $this->assertSame(url('/katalog-test/furniture/stulya'), $stulya->refresh()->url());

        $this->get('/katalog-test/furniture/stulya')->assertOk();
        $this->get('/katalog-test/mebel/stulya')->assertNotFound();
    }

    public function test_a_hidden_section_has_no_page(): void
    {
        $iblock = $this->pagedIblock();
        [$mebel] = $this->sections($iblock);

        $mebel->update(['is_active' => false]);

        $this->get('/katalog-test/mebel')->assertNotFound();
    }

    // ----------------------------------------------------------------- detail

    public function test_an_element_opens_on_its_own_page(): void
    {
        $iblock = $this->pagedIblock();
        $this->elements($iblock, 2);

        IblockElement::query()->where('code', 'item-2')->update([
            'detail_text' => 'Подробное описание товара',
            'detail_text_type' => 'text',
        ]);

        $this->get('/katalog-test/item-2')
            ->assertOk()
            ->assertSee('Товар 2')
            ->assertSee('Подробное описание товара');
    }

    public function test_the_listing_links_to_the_detail_page(): void
    {
        $iblock = $this->pagedIblock();
        $this->elements($iblock, 1);

        $this->get('/katalog-test')->assertOk()->assertSee(url('/katalog-test/item-1'), false);
    }

    public function test_an_unknown_element_returns_404(): void
    {
        $this->pagedIblock();

        $this->get('/katalog-test/no-such-item')->assertNotFound();
    }

    public function test_an_inactive_element_has_no_detail_page(): void
    {
        $iblock = $this->pagedIblock();
        $this->elements($iblock, 1);

        IblockElement::query()->where('code', 'item-1')->update(['is_active' => false]);

        $this->get('/katalog-test/item-1')->assertNotFound();
    }

    public function test_an_infoblock_without_a_page_has_no_detail_route(): void
    {
        $iblock = Iblock::factory()->create(['code' => 'plain-test', 'has_page' => false]);
        $this->elements($iblock, 1);

        $this->get('/plain-test/item-1')->assertNotFound();
    }

    public function test_opening_an_element_counts_a_view(): void
    {
        $iblock = $this->pagedIblock();
        $this->elements($iblock, 1);

        $this->get('/katalog-test/item-1')->assertOk();

        $this->assertSame(1, (int) IblockElement::query()->where('code', 'item-1')->value('views'));
    }
}
