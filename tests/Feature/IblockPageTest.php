<?php

namespace Tests\Feature;

use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\File;
use Nexor\Cms\Enums\PaginationTemplate;
use Nexor\Cms\Models\Iblock;
use Nexor\Cms\Models\IblockElement;
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

    public function test_the_page_switch_scaffolds_a_listing_a_detail_page_and_a_paging_component(): void
    {
        $iblock = $this->pagedIblock();

        foreach (PageGenerator::FILES as $file) {
            $this->assertTrue(PageGenerator::exists($iblock, $file), "{$file}.blade.php не создан");
        }
    }

    public function test_the_listing_includes_the_paging_component(): void
    {
        $iblock = $this->pagedIblock();

        $this->assertStringContainsString(
            "@include('".PageGenerator::view($iblock, 'pagination')."'",
            File::get(PageGenerator::path($iblock)),
        );
    }

    public function test_the_paging_component_follows_the_chosen_template(): void
    {
        $numbered = File::get(PageGenerator::path($this->pagedIblock(), 'pagination'));

        $this->assertStringContainsString('aria-label="Страницы"', $numbered);

        $button = File::get(PageGenerator::path(
            $this->pagedIblock(['code' => 'btnload-test', 'pagination_template' => PaginationTemplate::ButtonLoad]),
            'pagination',
        ));

        $this->assertStringContainsString('Показать ещё', $button);
        $this->assertStringNotContainsString('aria-label="Страницы"', $button);
    }

    public function test_changing_the_template_rewrites_only_the_paging_component(): void
    {
        $iblock = $this->pagedIblock();
        $admin = $this->adminWith(['iblocks.update']);

        File::put(PageGenerator::path($iblock), 'моя разметка');

        $this->actingAs($admin)->putJson("/admin/api/iblocks/{$iblock->id}", [
            'iblock_type_id' => $iblock->iblock_type_id,
            'code' => $iblock->code,
            'name' => $iblock->name,
            'has_page' => '1',
            'is_active' => '1',
            'pagination_template' => PaginationTemplate::ButtonLoad->value,
        ])->assertOk();

        $this->assertStringContainsString(
            'Показать ещё',
            File::get(PageGenerator::path($iblock, 'pagination')),
        );

        // The listing is the operator's file — resaving must not touch it.
        $this->assertSame('моя разметка', File::get(PageGenerator::path($iblock)));
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
