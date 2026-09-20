<?php

namespace Tests\Feature;

use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Blade;
use Nexor\Cms\Models\CatalogProduct;
use Nexor\Cms\Models\Iblock;
use Nexor\Cms\Models\IblockElement;
use Nexor\Cms\Models\IblockProperty;
use Nexor\Cms\Services\InfoBlockService;
use Tests\TestCase;

/**
 * Фильтр и сортировка по цене торгового каталога.
 *
 * Цена лежит не в свойствах, а в `catalog_products`, и у товара с торговыми
 * предложениями — у самих предложений. Покупатель об этом не знает: он двигает
 * ползунок и ждёт, что попадут все товары, которые столько стоят.
 */
class CatalogPriceFilterTest extends TestCase
{
    use RefreshDatabase;

    protected function catalogue(): Iblock
    {
        return Iblock::factory()->create(['code' => 'katalog', 'is_catalog' => true, 'is_active' => true]);
    }

    protected function product(Iblock $iblock, string $name, ?float $price, float $discount = 0): IblockElement
    {
        $element = IblockElement::factory()->for($iblock)->create(['name' => $name, 'is_active' => true]);

        CatalogProduct::factory()->create([
            'element_id' => $element->id,
            'price' => $price,
            'discount_percent' => $discount,
        ]);

        return $element;
    }

    protected function service(): InfoBlockService
    {
        return app(InfoBlockService::class);
    }

    protected function names(iterable $elements): array
    {
        return collect($elements)->pluck('name')->all();
    }

    public function test_a_range_keeps_both_of_its_bounds(): void
    {
        $iblock = $this->catalogue();

        $this->product($iblock, 'Дешёвый', 500);
        $this->product($iblock, 'Средний', 1500);
        $this->product($iblock, 'Дорогой', 5000);

        $found = $this->service()->getElements('katalog', ['price' => ['from' => 1000, 'to' => 2000]]);

        // Раньше вторая граница перекрывала первую, и «от» просто терялось.
        $this->assertSame(['Средний'], $this->names($found));
    }

    public function test_one_bound_is_enough(): void
    {
        $iblock = $this->catalogue();

        $this->product($iblock, 'Дешёвый', 500);
        $this->product($iblock, 'Дорогой', 5000);

        $this->assertSame(['Дорогой'], $this->names($this->service()->getElements('katalog', ['price' => ['from' => 1000]])));
        $this->assertSame(['Дешёвый'], $this->names($this->service()->getElements('katalog', ['price' => ['to' => 1000]])));
    }

    public function test_the_price_with_the_discount_is_the_one_that_counts(): void
    {
        $iblock = $this->catalogue();

        // 2000 со скидкой 50% — это 1000, и покупатель ищет его именно там.
        $this->product($iblock, 'Со скидкой', 2000, 50);

        $this->assertSame(['Со скидкой'], $this->names($this->service()->getElements('katalog', ['price' => ['to' => 1200]])));
        $this->assertSame([], $this->names($this->service()->getElements('katalog', ['price' => ['from' => 1800]])));
    }

    public function test_a_product_is_found_by_the_price_of_its_offers(): void
    {
        $iblock = $this->catalogue();

        $product = $this->product($iblock, 'Стул', null);
        $offer = IblockElement::factory()->for($iblock)->create(['name' => 'Стул красный', 'is_active' => true]);

        CatalogProduct::factory()->create([
            'element_id' => $offer->id,
            'parent_element_id' => $product->id,
            'price' => 1500,
        ]);

        $found = $this->names($this->service()->getElements('katalog', ['price' => ['from' => 1000, 'to' => 2000]]));

        // Сам товар цены не имеет — её знают только предложения.
        $this->assertContains('Стул', $found);
    }

    public function test_sorting_by_price_works_in_both_directions(): void
    {
        $iblock = $this->catalogue();

        $this->product($iblock, 'Средний', 1500);
        $this->product($iblock, 'Дешёвый', 500);
        $this->product($iblock, 'Дорогой', 5000);

        $this->assertSame(
            ['Дешёвый', 'Средний', 'Дорогой'],
            $this->names($this->service()->getElements('katalog', [], ['price' => 'asc'])),
        );

        $this->assertSame(
            ['Дорогой', 'Средний', 'Дешёвый'],
            $this->names($this->service()->getElements('katalog', [], ['price' => 'desc'])),
        );
    }

    public function test_the_bounds_come_from_the_goods_themselves(): void
    {
        $iblock = $this->catalogue();

        $this->product($iblock, 'Дешёвый', 500);
        $this->product($iblock, 'Дорогой', 5000);

        $this->assertSame(['min' => 500.0, 'max' => 5000.0], $this->service()->getPriceRange('katalog'));
    }

    public function test_an_infoblock_without_prices_has_no_range(): void
    {
        Iblock::factory()->create(['code' => 'news', 'is_catalog' => false, 'is_active' => true]);

        $this->assertNull($this->service()->getPriceRange('news'));
    }

    // ---------------------------------------------------------- компоненты

    public function test_the_list_reads_the_price_from_the_address(): void
    {
        $iblock = $this->catalogue();

        $this->product($iblock, 'Дешёвый', 500);
        $this->product($iblock, 'Средний', 1500);
        $this->product($iblock, 'Дорогой', 5000);

        // Фильтр и список связаны только адресной строкой — как на сайте.
        request()->merge(['price_from' => 1000, 'price_to' => 2000]);

        $html = Blade::render('<x-nexor::catalog.section iblock="katalog" :paginate="false" />');

        $this->assertStringContainsString('Средний', $html);
        $this->assertStringNotContainsString('Дорогой', $html);
        $this->assertStringNotContainsString('Дешёвый', $html);
    }

    public function test_the_filter_offers_a_price_block_with_real_bounds(): void
    {
        $iblock = $this->catalogue();

        $this->product($iblock, 'Дешёвый', 500);
        $this->product($iblock, 'Дорогой', 5000);

        IblockProperty::factory()->for($iblock)->create(['code' => 'COLOR', 'is_filterable' => true]);

        $html = Blade::render('<x-nexor::catalog.filter iblock="katalog" />');

        $this->assertStringContainsString('name="price_from"', $html);
        $this->assertStringContainsString('name="price_to"', $html);
        $this->assertStringContainsString('500', $html);
        $this->assertStringContainsString('5000', $html);
    }

    public function test_the_price_block_disappears_when_there_is_nothing_to_choose(): void
    {
        $iblock = $this->catalogue();

        // Одна цена на весь каталог — двигать ползунок некуда.
        $this->product($iblock, 'Единственный', 500);

        IblockProperty::factory()->for($iblock)->create(['code' => 'COLOR', 'is_filterable' => true]);

        $html = Blade::render('<x-nexor::catalog.filter iblock="katalog" />');

        $this->assertStringNotContainsString('name="price_from"', $html);
    }
}
