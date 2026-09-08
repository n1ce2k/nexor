<?php

namespace Tests\Feature;

use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Str;
use Nexor\Cms\Enums\PropertyType;
use Nexor\Cms\Models\Iblock;
use Nexor\Cms\Models\IblockElement;
use Nexor\Cms\Models\IblockProperty;
use Nexor\Cms\Models\IblockSection;
use Nexor\Cms\Services\InfoBlockService;
use RuntimeException;
use Tests\TestCase;

/**
 * The read core the public components are built on: filters, ordering,
 * sections and breadcrumbs, over the typed property values.
 */
class InfoBlockServiceTest extends TestCase
{
    use RefreshDatabase;

    protected InfoBlockService $service;

    protected function setUp(): void
    {
        parent::setUp();

        $this->service = app(InfoBlockService::class);
    }

    /**
     * A catalogue with a string, an integer and a select property.
     */
    protected function catalogue(): Iblock
    {
        $iblock = Iblock::factory()->create(['code' => 'katalog', 'name' => 'Каталог', 'has_sections' => true]);

        IblockProperty::factory()->for($iblock)->create(['code' => 'ARTICLE', 'name' => 'Артикул']);
        IblockProperty::factory()->for($iblock)->ofType(PropertyType::Integer)->create(['code' => 'PRICE']);
        IblockProperty::factory()->for($iblock)->withEnums(['Матовый', 'Глянцевый'])->create(['code' => 'FINISH']);

        return $iblock;
    }

    /**
     * @param  array<string, mixed>  $properties  Property code => raw value
     */
    protected function element(Iblock $iblock, string $name, array $properties = [], array $attributes = []): IblockElement
    {
        $element = IblockElement::factory()->for($iblock)->create(array_merge([
            'name' => $name,
            'code' => Str::slug($name).'-'.fake()->unique()->numberBetween(1, 9999),
        ], $attributes));

        foreach ($properties as $code => $value) {
            $property = $iblock->properties()->where('code', $code)->firstOrFail();

            $column = $property->type === PropertyType::Select
                ? 'value_enum_id'
                : $property->type->column();

            if ($property->type === PropertyType::Select) {
                $value = $property->enums()->where('value', $value)->firstOrFail()->id;
            }

            $element->values()->create(['property_id' => $property->id, $column => $value]);
        }

        return $element;
    }

    // ----------------------------------------------------------- инфоблок

    public function test_an_infoblock_is_found_by_its_code(): void
    {
        $this->catalogue();

        $this->assertSame('Каталог', $this->service->getInfoBlockByCode('katalog')?->name);
        $this->assertTrue($this->service->infoBlockExists('katalog'));
    }

    public function test_a_disabled_infoblock_is_invisible(): void
    {
        Iblock::factory()->create(['code' => 'hidden', 'is_active' => false]);

        $this->assertNull($this->service->getInfoBlockByCode('hidden'));
    }

    public function test_asking_for_an_unknown_infoblock_fails_loudly(): void
    {
        $this->expectException(RuntimeException::class);

        $this->service->getElements('no-such-block');
    }

    // ----------------------------------------------------------- элементы

    public function test_elements_come_back_in_the_requested_order(): void
    {
        $iblock = $this->catalogue();
        $this->element($iblock, 'Второй', [], ['sort' => 200]);
        $this->element($iblock, 'Первый', [], ['sort' => 100]);

        $names = $this->service->getElements('katalog')->pluck('name')->all();

        $this->assertSame(['Первый', 'Второй'], $names);

        $desc = $this->service->getElements('katalog', order: ['sort' => 'desc'])->pluck('name')->all();

        $this->assertSame(['Второй', 'Первый'], $desc);
    }

    public function test_only_published_elements_are_active(): void
    {
        $iblock = $this->catalogue();
        $this->element($iblock, 'Виден');
        $this->element($iblock, 'Скрыт', [], ['is_active' => false]);
        $this->element($iblock, 'Ещё не начался', [], ['active_from' => now()->addDay()]);
        $this->element($iblock, 'Уже кончился', [], ['active_to' => now()->subDay()]);

        $names = $this->service->getActiveElements('katalog')->pluck('name')->all();

        $this->assertSame(['Виден'], $names);
    }

    public function test_elements_can_be_paged(): void
    {
        $iblock = $this->catalogue();

        foreach (range(1, 5) as $index) {
            $this->element($iblock, 'Товар '.$index, [], ['sort' => $index * 10]);
        }

        $page = $this->service->getElements('katalog', perPage: 2, page: 2);

        $this->assertSame(5, $page->total());
        $this->assertSame(['Товар 3', 'Товар 4'], $page->pluck('name')->all());
    }

    public function test_the_page_number_is_taken_from_the_address_when_it_is_not_given(): void
    {
        $iblock = $this->catalogue();

        foreach (range(1, 5) as $index) {
            $this->element($iblock, 'Товар '.$index, [], ['sort' => $index * 10]);
        }

        // Шаблон номер страницы не передаёт — его должен найти сам пагинатор.
        $this->get('/katalog?page=2');

        $page = $this->service->getElements('katalog', perPage: 2);

        $this->assertSame(2, $page->currentPage());
        $this->assertSame(['Товар 3', 'Товар 4'], $page->pluck('name')->all());
    }

    public function test_an_element_is_found_by_id_and_by_code(): void
    {
        $iblock = $this->catalogue();
        $element = $this->element($iblock, 'Стул', [], ['code' => 'stul']);

        $this->assertSame('Стул', $this->service->getElementById('katalog', $element->id)?->name);
        $this->assertSame($element->id, $this->service->getElementByCode('katalog', 'stul')?->id);
        $this->assertTrue($this->service->elementExists('katalog', $element->id));
        $this->assertTrue($this->service->elementExistsByCode('katalog', 'stul'));
        $this->assertFalse($this->service->elementExistsByCode('katalog', 'net-takogo'));
    }

    public function test_elements_are_searched_by_name_and_code(): void
    {
        $iblock = $this->catalogue();
        $this->element($iblock, 'Кресло офисное', [], ['code' => 'kreslo']);
        $this->element($iblock, 'Диван', [], ['code' => 'divan']);

        // Регистр остаётся на совести базы, поэтому ищем так, как работает и MySQL, и SQLite.
        $this->assertSame(['Кресло офисное'], $this->service->searchElements('katalog', 'Кресло')->pluck('name')->all());
        $this->assertSame(['Диван'], $this->service->searchElements('katalog', 'divan')->pluck('name')->all());
    }

    public function test_the_view_counter_orders_the_popular_ones(): void
    {
        $iblock = $this->catalogue();
        $this->element($iblock, 'Редкий', [], ['views' => 3]);
        $this->element($iblock, 'Частый', [], ['views' => 90]);

        $this->assertSame('Частый', $this->service->getPopularElements('katalog')->first()->name);
    }

    public function test_the_newest_elements_come_first(): void
    {
        $iblock = $this->catalogue();
        $this->element($iblock, 'Старый', [], ['created_at' => now()->subWeek()]);
        $this->element($iblock, 'Новый', [], ['created_at' => now()]);

        $this->assertSame('Новый', $this->service->getLatestElements('katalog', 1)->first()->name);
    }

    // ------------------------------------------------------- фильтр свойств

    public function test_elements_are_filtered_by_a_property_value(): void
    {
        $iblock = $this->catalogue();
        $this->element($iblock, 'Тот самый', ['ARTICLE' => 'ART-1']);
        $this->element($iblock, 'Другой', ['ARTICLE' => 'ART-2']);

        $names = $this->service->getElementsByProperty('katalog', 'ARTICLE', 'ART-1')->pluck('name')->all();

        $this->assertSame(['Тот самый'], $names);
    }

    public function test_a_property_filter_understands_comparison(): void
    {
        $iblock = $this->catalogue();
        $this->element($iblock, 'Дешёвый', ['PRICE' => 500]);
        $this->element($iblock, 'Дорогой', ['PRICE' => 5000]);

        $names = $this->service->getElements('katalog', ['PRICE' => ['>', 1000]])->pluck('name')->all();

        $this->assertSame(['Дорогой'], $names);
    }

    public function test_a_property_filter_accepts_a_list_of_values(): void
    {
        $iblock = $this->catalogue();
        $this->element($iblock, 'Первый', ['ARTICLE' => 'A']);
        $this->element($iblock, 'Второй', ['ARTICLE' => 'B']);
        $this->element($iblock, 'Третий', ['ARTICLE' => 'C']);

        $names = $this->service->getElements('katalog', ['ARTICLE' => ['A', 'C']], ['sort' => 'asc'])
            ->pluck('name')->sort()->values()->all();

        $this->assertSame(['Первый', 'Третий'], $names);
    }

    public function test_a_select_property_is_filtered_by_its_visible_value(): void
    {
        $iblock = $this->catalogue();
        $this->element($iblock, 'Матовая плитка', ['FINISH' => 'Матовый']);
        $this->element($iblock, 'Глянцевая плитка', ['FINISH' => 'Глянцевый']);

        $names = $this->service->getElements('katalog', ['FINISH' => 'Матовый'])->pluck('name')->all();

        $this->assertSame(['Матовая плитка'], $names);
    }

    public function test_elements_are_ordered_by_a_property(): void
    {
        $iblock = $this->catalogue();
        $this->element($iblock, 'Дорогой', ['PRICE' => 5000], ['sort' => 100]);
        $this->element($iblock, 'Дешёвый', ['PRICE' => 500], ['sort' => 200]);

        $names = $this->service->getElements('katalog', order: ['PRICE' => 'asc'])->pluck('name')->all();

        $this->assertSame(['Дешёвый', 'Дорогой'], $names);
    }

    public function test_filtering_by_an_unknown_property_fails_loudly(): void
    {
        $this->catalogue();

        $this->expectException(RuntimeException::class);

        $this->service->getElements('katalog', ['NO_SUCH_PROPERTY' => 1]);
    }

    public function test_elements_can_be_grouped_by_a_property(): void
    {
        $iblock = $this->catalogue();
        $this->element($iblock, 'Плитка А', ['FINISH' => 'Матовый']);
        $this->element($iblock, 'Плитка Б', ['FINISH' => 'Матовый']);
        $this->element($iblock, 'Плитка В', ['FINISH' => 'Глянцевый']);

        $grouped = $this->service->getElementsGroupedByProperty('katalog', 'FINISH');

        $this->assertCount(2, $grouped['Матовый']);
        $this->assertCount(1, $grouped['Глянцевый']);
    }

    public function test_elements_can_be_counted(): void
    {
        $iblock = $this->catalogue();
        $this->element($iblock, 'Раз', ['PRICE' => 100]);
        $this->element($iblock, 'Два', ['PRICE' => 900]);

        $this->assertSame(2, $this->service->countElements('katalog'));
        $this->assertSame(1, $this->service->countElements('katalog', ['PRICE' => ['>=', 900]]));
    }

    public function test_an_element_comes_with_its_property_definitions(): void
    {
        $iblock = $this->catalogue();
        $element = $this->element($iblock, 'Стол', ['ARTICLE' => 'ART-7']);

        $bundle = $this->service->getElementWithFields('katalog', $element->id);

        $this->assertSame('Стол', $bundle['element']->name);
        $this->assertSame('Артикул', $bundle['properties']['ARTICLE']['property']->name);
        $this->assertSame('ART-7', $bundle['properties']['ARTICLE']['value']);
    }

    public function test_the_select_options_are_offered_for_filters(): void
    {
        $this->catalogue();

        $options = $this->service->getEnumOptions('katalog');

        $this->assertSame(['Матовый', 'Глянцевый'], array_column($options['FINISH'], 'value'));
        $this->assertArrayNotHasKey('ARTICLE', $options);
    }

    // ------------------------------------------------------------- разделы

    public function test_the_section_tree_is_nested(): void
    {
        $iblock = $this->catalogue();
        $parent = IblockSection::factory()->create(['iblock_id' => $iblock->id, 'name' => 'Мебель', 'sort' => 100]);
        IblockSection::factory()->create(['iblock_id' => $iblock->id, 'parent_id' => $parent->id, 'name' => 'Стулья']);
        IblockSection::factory()->create(['iblock_id' => $iblock->id, 'name' => 'Декор', 'sort' => 200]);

        $tree = $this->service->getSectionsTree('katalog');

        $this->assertSame(['Мебель', 'Декор'], array_column($tree, 'name'));
        $this->assertSame(['Стулья'], array_column($tree[0]['children'], 'name'));
        $this->assertSame([], $tree[1]['children']);
    }

    public function test_elements_are_listed_for_one_section_or_for_its_whole_subtree(): void
    {
        $iblock = $this->catalogue();
        $parent = IblockSection::factory()->create(['iblock_id' => $iblock->id, 'name' => 'Мебель']);
        $child = IblockSection::factory()->create(['iblock_id' => $iblock->id, 'parent_id' => $parent->id]);

        $this->element($iblock, 'В родителе', [], ['section_id' => $parent->id]);
        $this->element($iblock, 'В потомке', [], ['section_id' => $child->id]);
        $this->element($iblock, 'Вне разделов');

        $own = $this->service->getElementsBySection('katalog', $parent->id)->pluck('name')->all();
        $this->assertSame(['В родителе'], $own);

        $deep = $this->service->getElementsBySectionRecursive('katalog', $parent->id)
            ->pluck('name')->sort()->values()->all();
        $this->assertSame(['В потомке', 'В родителе'], $deep);
    }

    public function test_a_section_is_found_by_id_and_by_code(): void
    {
        $iblock = $this->catalogue();
        $section = IblockSection::factory()->create([
            'iblock_id' => $iblock->id, 'name' => 'Мебель', 'code' => 'mebel',
        ]);

        $this->assertSame($section->id, $this->service->getSectionById('katalog', $section->id)?->id);
        $this->assertSame('Мебель', $this->service->getSectionByCode('katalog', 'mebel')?->name);
    }

    public function test_sections_are_refused_when_the_infoblock_does_not_use_them(): void
    {
        Iblock::factory()->withoutSections()->create(['code' => 'news']);

        $this->expectException(RuntimeException::class);

        $this->service->getSections('news');
    }

    // ------------------------------------------------------ хлебные крошки

    public function test_breadcrumbs_walk_from_the_home_page_down_to_the_element(): void
    {
        $iblock = $this->catalogue();
        $parent = IblockSection::factory()->create(['iblock_id' => $iblock->id, 'name' => 'Мебель']);
        $child = IblockSection::factory()->create([
            'iblock_id' => $iblock->id, 'parent_id' => $parent->id, 'name' => 'Стулья',
        ]);

        $element = $this->element($iblock, 'Венский стул', [], ['section_id' => $child->id]);

        $names = array_column($this->service->getBreadcrumbs('katalog', $element), 'name');

        $this->assertSame(['Главная', 'Каталог', 'Мебель', 'Стулья', 'Венский стул'], $names);
    }

    public function test_breadcrumbs_of_an_unknown_infoblock_stop_at_the_home_page(): void
    {
        $this->assertSame([['name' => 'Главная', 'url' => url('/')]], $this->service->getBreadcrumbs('no-such'));
    }
}
