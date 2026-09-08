<?php

namespace Tests\Feature;

use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Blade;
use Illuminate\Support\Facades\File;
use Illuminate\View\ViewException;
use Nexor\Cms\Enums\PaginationTemplate;
use Nexor\Cms\Enums\PropertyType;
use Nexor\Cms\Models\Iblock;
use Nexor\Cms\Models\IblockElement;
use Nexor\Cms\Models\IblockProperty;
use Nexor\Cms\Models\IblockSection;
use Nexor\Cms\Services\InfoBlockService;
use Tests\TestCase;

/**
 * Компоненты публичной части: логика в классе, вёрстка в шаблоне, и шаблон
 * можно подменить своим — как копирование шаблона компонента в Битриксе.
 */
class ComponentTest extends TestCase
{
    use RefreshDatabase;

    /** Опубликованные шаблоны, которые надо убрать за собой. */
    protected string $published = '';

    protected function setUp(): void
    {
        parent::setUp();

        $this->published = resource_path('views/vendor/nexor/components');
    }

    protected function tearDown(): void
    {
        File::deleteDirectory($this->published);

        parent::tearDown();
    }

    protected function catalogue(array $attributes = []): Iblock
    {
        return Iblock::factory()->create(array_merge([
            'code' => 'katalog',
            'name' => 'Каталог',
            'has_sections' => true,
            'is_active' => true,
        ], $attributes));
    }

    protected function element(Iblock $iblock, string $name, array $attributes = []): IblockElement
    {
        return IblockElement::factory()->for($iblock)->create(array_merge([
            'name' => $name,
            'code' => 'item-'.fake()->unique()->numberBetween(1, 9999),
            'is_active' => true,
        ], $attributes));
    }

    // ------------------------------------------------------------- catalog.section

    public function test_the_listing_renders_its_elements(): void
    {
        $iblock = $this->catalogue();
        $this->element($iblock, 'Венский стул');

        $html = Blade::render('<x-nexor::catalog.section iblock="katalog" />');

        $this->assertStringContainsString('Венский стул', $html);
        $this->assertStringContainsString('id="nexor-items"', $html);
    }

    public function test_the_template_prop_picks_the_markup(): void
    {
        $iblock = $this->catalogue();
        $this->element($iblock, 'Товар');

        $default = Blade::render('<x-nexor::catalog.section iblock="katalog" />');
        $tiles = Blade::render('<x-nexor::catalog.section iblock="katalog" template="tiles" />');

        $this->assertStringContainsString('lg:grid-cols-3', $default);
        $this->assertStringContainsString('lg:grid-cols-4', $tiles);
    }

    public function test_a_published_template_wins_over_the_package_one(): void
    {
        $this->catalogue();

        File::ensureDirectoryExists($this->published.'/catalog/section');
        File::put($this->published.'/catalog/section/default.blade.php', 'моя вёрстка списка');

        $this->assertSame('моя вёрстка списка', trim(Blade::render('<x-nexor::catalog.section iblock="katalog" />')));
    }

    public function test_an_unknown_template_says_where_the_file_is_expected(): void
    {
        $this->catalogue();

        // Blade заворачивает исключения шаблона в своё, сообщение остаётся нашим.
        $this->expectException(ViewException::class);
        $this->expectExceptionMessage('components/catalog/section/no-such.blade.php');

        Blade::render('<x-nexor::catalog.section iblock="katalog" template="no-such" />');
    }

    public function test_an_unknown_infoblock_fails_loudly(): void
    {
        $this->expectException(ViewException::class);
        $this->expectExceptionMessage('«net-takogo» не найден');

        Blade::render('<x-nexor::catalog.section iblock="net-takogo" />');
    }

    public function test_the_card_can_be_replaced_without_copying_the_listing(): void
    {
        $iblock = $this->catalogue();
        $this->element($iblock, 'Товар');

        File::ensureDirectoryExists(resource_path('views/test-cards'));
        File::put(resource_path('views/test-cards/card.blade.php'), 'КАРТОЧКА {{ $element->name }}');

        try {
            $html = Blade::render('<x-nexor::catalog.section iblock="katalog" card="test-cards.card" />');

            $this->assertStringContainsString('КАРТОЧКА Товар', $html);
            // Обёртка списка осталась пакетной.
            $this->assertStringContainsString('id="nexor-items"', $html);
        } finally {
            File::deleteDirectory(resource_path('views/test-cards'));
        }
    }

    public function test_the_listing_narrows_to_the_section_from_the_address(): void
    {
        $iblock = $this->catalogue();
        $section = IblockSection::factory()->create([
            'iblock_id' => $iblock->id, 'name' => 'Мебель', 'code' => 'mebel',
        ]);

        $this->element($iblock, 'В разделе', ['section_id' => $section->id]);
        $this->element($iblock, 'Вне раздела');

        $this->get('/?section=mebel');

        $html = Blade::render('<x-nexor::catalog.section iblock="katalog" />');

        $this->assertStringContainsString('В разделе', $html);
        $this->assertStringNotContainsString('Вне раздела', $html);
    }

    public function test_the_listing_filters_by_a_property_from_the_address(): void
    {
        $iblock = $this->catalogue();
        $property = IblockProperty::factory()->for($iblock)->create([
            'code' => 'ARTICLE', 'is_filterable' => true,
        ]);

        $wanted = $this->element($iblock, 'Нужный');
        $other = $this->element($iblock, 'Лишний');

        $wanted->values()->create(['property_id' => $property->id, 'value_string' => 'A']);
        $other->values()->create(['property_id' => $property->id, 'value_string' => 'B']);

        $this->get('/?ARTICLE=A');

        $html = Blade::render('<x-nexor::catalog.section iblock="katalog" />');

        $this->assertStringContainsString('Нужный', $html);
        $this->assertStringNotContainsString('Лишний', $html);
    }

    public function test_a_property_outside_the_filter_cannot_be_used_from_the_address(): void
    {
        $iblock = $this->catalogue();
        $property = IblockProperty::factory()->for($iblock)->create([
            'code' => 'SECRET', 'is_filterable' => false,
        ]);

        $this->element($iblock, 'Виден')->values()->create([
            'property_id' => $property->id, 'value_string' => 'A',
        ]);

        $this->get('/?SECRET=B');

        // Свойство без галочки «участвует в фильтре» из URL не читается.
        $this->assertStringContainsString('Виден', Blade::render('<x-nexor::catalog.section iblock="katalog" />'));
    }

    // -------------------------------------------------------------- pagination

    public function test_the_paging_template_comes_from_the_infoblock_setting(): void
    {
        $iblock = $this->catalogue([
            'pagination_template' => PaginationTemplate::ButtonLoad,
            'load_more_size' => 1,
        ]);

        $this->element($iblock, 'Первый');
        $this->element($iblock, 'Второй');

        $html = Blade::render('<x-nexor::catalog.section iblock="katalog" />');

        $this->assertStringContainsString('Показать ещё', $html);
        $this->assertStringNotContainsString('aria-label="Страницы"', $html);
    }

    public function test_the_template_prop_overrides_the_infoblock_setting(): void
    {
        $iblock = $this->catalogue(['per_page' => 1]);
        $this->element($iblock, 'Первый');
        $this->element($iblock, 'Второй');

        $html = Blade::render(
            '<x-nexor::catalog.section iblock="katalog" />'.
            '<x-nexor::pagination :paginator="$page" template="full" />',
            ['page' => app(InfoBlockService::class)->getElements('katalog', perPage: 1)],
        );

        $this->assertStringContainsString('Первая', $html);
    }

    // ---------------------------------------------------------- catalog.filter

    public function test_the_filter_shows_only_the_properties_marked_for_it(): void
    {
        $iblock = $this->catalogue();

        IblockProperty::factory()->for($iblock)->withEnums(['Матовый', 'Глянцевый'])->create([
            'code' => 'FINISH', 'name' => 'Покрытие', 'is_filterable' => true,
        ]);

        IblockProperty::factory()->for($iblock)->create([
            'code' => 'SECRET', 'name' => 'Служебное', 'is_filterable' => false,
        ]);

        $html = Blade::render('<x-nexor::catalog.filter iblock="katalog" />');

        $this->assertStringContainsString('Покрытие', $html);
        $this->assertStringContainsString('Матовый', $html);
        $this->assertStringNotContainsString('Служебное', $html);
    }

    public function test_the_filter_can_be_narrowed_to_named_properties(): void
    {
        $iblock = $this->catalogue();

        foreach (['ONE', 'TWO'] as $code) {
            IblockProperty::factory()->for($iblock)->create([
                'code' => $code, 'name' => 'Свойство '.$code, 'is_filterable' => true,
            ]);
        }

        $html = Blade::render('<x-nexor::catalog.filter iblock="katalog" :only="[\'ONE\']" />');

        $this->assertStringContainsString('Свойство ONE', $html);
        $this->assertStringNotContainsString('Свойство TWO', $html);
    }

    public function test_a_number_property_gets_a_range_in_the_filter(): void
    {
        $iblock = $this->catalogue();
        IblockProperty::factory()->for($iblock)->ofType(PropertyType::Integer)->create([
            'code' => 'PRICE', 'is_filterable' => true,
        ]);

        $html = Blade::render('<x-nexor::catalog.filter iblock="katalog" />');

        $this->assertStringContainsString('name="PRICE_FROM"', $html);
        $this->assertStringContainsString('name="PRICE_TO"', $html);
    }

    // -------------------------------------------------------- catalog.sections

    public function test_the_section_menu_renders_the_tree(): void
    {
        $iblock = $this->catalogue();
        $parent = IblockSection::factory()->create([
            'iblock_id' => $iblock->id, 'name' => 'Мебель', 'code' => 'mebel',
        ]);
        IblockSection::factory()->create([
            'iblock_id' => $iblock->id, 'parent_id' => $parent->id, 'name' => 'Стулья', 'code' => 'stulya',
        ]);

        $flat = Blade::render('<x-nexor::catalog.sections iblock="katalog" />');
        $tree = Blade::render('<x-nexor::catalog.sections iblock="katalog" template="tree" />');

        // Чипсы показывают только верхний уровень, дерево — всё.
        $this->assertStringContainsString('Мебель', $flat);
        $this->assertStringNotContainsString('Стулья', $flat);
        $this->assertStringContainsString('Стулья', $tree);
    }

    // ------------------------------------------------------------ breadcrumbs

    public function test_breadcrumbs_walk_down_to_the_element(): void
    {
        $iblock = $this->catalogue();
        $section = IblockSection::factory()->create(['iblock_id' => $iblock->id, 'name' => 'Мебель']);
        $element = $this->element($iblock, 'Стул', ['section_id' => $section->id]);

        $html = Blade::render('<x-nexor::breadcrumbs iblock="katalog" :element="$element" />', compact('element'));

        foreach (['Главная', 'Каталог', 'Мебель', 'Стул'] as $step) {
            $this->assertStringContainsString($step, $html);
        }
    }

    // ---------------------------------------------------------- catalog.element

    public function test_the_detail_card_prints_the_property_table(): void
    {
        $iblock = $this->catalogue();
        $property = IblockProperty::factory()->for($iblock)->create(['code' => 'ARTICLE', 'name' => 'Артикул']);

        $element = $this->element($iblock, 'Стул', ['detail_text' => 'Описание', 'detail_text_type' => 'text']);
        $element->values()->create(['property_id' => $property->id, 'value_string' => 'ART-7']);

        $html = Blade::render('<x-nexor::catalog.element :element="$element" />', compact('element'));

        $this->assertStringContainsString('Описание', $html);
        $this->assertStringContainsString('Артикул', $html);
        $this->assertStringContainsString('ART-7', $html);
    }

    public function test_the_detail_card_finds_the_element_by_code(): void
    {
        $iblock = $this->catalogue();
        $this->element($iblock, 'Стул', ['code' => 'stul']);

        $html = Blade::render('<x-nexor::catalog.element iblock="katalog" code="stul" />');

        $this->assertStringContainsString('Стул', $html);
    }
}
