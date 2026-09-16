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
use Tests\Concerns\PreservesPublishedViews;
use Tests\TestCase;

/**
 * Компоненты публичной части: логика в классе, вёрстка в шаблоне, и шаблон
 * можно подменить своим — как копирование шаблона компонента в Битриксе.
 */
class ComponentTest extends TestCase
{
    use PreservesPublishedViews, RefreshDatabase;

    /** Опубликованные шаблоны: свои тест убирает, шаблоны сайта возвращает. */
    protected string $published = '';

    protected function setUp(): void
    {
        parent::setUp();

        // Шаблоны сайта откладываются и возвращаются после теста, а сам тест
        // идёт на пустой папке: иначе он проверял бы вёрстку сайта, а не пакета.
        $this->published = $this->preserveViews('vendor/nexor/components');
        File::deleteDirectory($this->published);
    }

    protected function tearDown(): void
    {
        $this->restorePreservedViews();

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

    public function test_an_unknown_infoblock_shows_a_placeholder_instead_of_breaking_the_page(): void
    {
        config(['app.debug' => true]);

        $html = Blade::render('<x-nexor::catalog.section iblock="net-takogo" />');

        $this->assertStringContainsString('Инфоблок недоступен', $html);
        // В отладке заглушка подсказывает, что именно не нашлось.
        $this->assertStringContainsString('«net-takogo» не найден или отключён — компонент catalog.section', $html);

        config(['app.debug' => false]);

        $this->assertStringNotContainsString('net-takogo', Blade::render('<x-nexor::catalog.section iblock="net-takogo" />'));
    }

    public function test_every_iblock_component_falls_back_to_the_placeholder(): void
    {
        foreach ([
            '<x-nexor::catalog.section-list iblock="net-takogo" />',
            '<x-nexor::catalog.filter iblock="net-takogo" />',
            '<x-nexor::news.list iblock="net-takogo" />',
            '<x-nexor::menu iblock="net-takogo" />',
            '<x-nexor::news.detail iblock="net-takogo" :id="1" />',
            '<x-nexor::catalog.element iblock="net-takogo" code="stul" />',
        ] as $tag) {
            $this->assertStringContainsString('Инфоблок недоступен', Blade::render($tag), $tag);
        }
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
        $this->threeLevels();

        $tree = Blade::render('<x-nexor::menu.sections iblock="katalog" :depth="3" />');

        foreach (['Мебель', 'Стулья', 'Венские'] as $name) {
            $this->assertStringContainsString($name, $tree);
        }
    }

    public function test_the_menu_is_cut_off_at_the_given_depth(): void
    {
        $this->threeLevels();

        $one = Blade::render('<x-nexor::menu.sections iblock="katalog" :depth="1" />');
        $two = Blade::render('<x-nexor::menu.sections iblock="katalog" :depth="2" />');

        $this->assertStringNotContainsString('Стулья', $one);
        $this->assertStringContainsString('Стулья', $two);
        $this->assertStringNotContainsString('Венские', $two);
    }

    public function test_the_menu_can_start_from_a_section(): void
    {
        $this->threeLevels();

        $html = Blade::render('<x-nexor::menu.sections iblock="katalog" root="mebel" :depth="1" />');

        // От корня глубина считается заново, поэтому видны его дети.
        $this->assertStringContainsString('Стулья', $html);
        $this->assertStringNotContainsString('Декор', $html);
    }

    public function test_the_chips_template_puts_the_sections_in_a_row(): void
    {
        $this->threeLevels();

        $chips = Blade::render('<x-nexor::menu.sections iblock="katalog" template="chips" :depth="1" />');

        $this->assertStringContainsString('Все', $chips);
        $this->assertStringContainsString('Мебель', $chips);
    }

    public function test_a_menu_of_an_infoblock_without_sections_lists_its_elements(): void
    {
        $pages = Iblock::factory()->withoutSections()->create(['code' => 'pages', 'is_active' => true]);
        $this->element($pages, 'О компании');

        $this->assertStringContainsString('О компании', Blade::render('<x-nexor::menu iblock="pages" />'));
    }

    public function test_a_menu_hangs_elements_under_their_sections(): void
    {
        $iblock = $this->catalogue();
        $section = IblockSection::factory()->create([
            'iblock_id' => $iblock->id, 'name' => 'Мебель', 'code' => 'mebel',
        ]);

        $this->element($iblock, 'Стул', ['section_id' => $section->id]);

        $html = Blade::render('<x-nexor::menu iblock="katalog" :elements="true" :depth="2" />');

        $this->assertStringContainsString('Мебель', $html);
        $this->assertStringContainsString('Стул', $html);
    }

    /** Каталог с тремя уровнями разделов и соседом на верхнем. */
    protected function threeLevels(): Iblock
    {
        $iblock = $this->catalogue();

        $mebel = IblockSection::factory()->create([
            'iblock_id' => $iblock->id, 'name' => 'Мебель', 'code' => 'mebel', 'sort' => 100,
        ]);

        $stulya = IblockSection::factory()->create([
            'iblock_id' => $iblock->id, 'parent_id' => $mebel->id, 'name' => 'Стулья', 'code' => 'stulya',
        ]);

        IblockSection::factory()->create([
            'iblock_id' => $iblock->id, 'parent_id' => $stulya->id, 'name' => 'Венские', 'code' => 'venskie',
        ]);

        IblockSection::factory()->create([
            'iblock_id' => $iblock->id, 'name' => 'Декор', 'code' => 'dekor', 'sort' => 200,
        ]);

        return $iblock;
    }

    // ------------------------------------------------------ catalog.section-list

    public function test_the_section_list_shows_the_top_level_with_counts(): void
    {
        $iblock = $this->threeLevels();
        $mebel = IblockSection::query()->where('code', 'mebel')->firstOrFail();
        $stulya = IblockSection::query()->where('code', 'stulya')->firstOrFail();

        $this->element($iblock, 'Стул', ['section_id' => $stulya->id]);

        $html = Blade::render('<x-nexor::catalog.section-list iblock="katalog" />');

        $this->assertStringContainsString('Мебель', $html);
        $this->assertStringContainsString('Декор', $html);
        // Только прямые потомки корня, вложенные сюда не попадают.
        $this->assertStringNotContainsString('Стулья', $html);
        // Счётчик рекурсивный: товар лежит во внуке «Мебели».
        $this->assertStringContainsString('1 шт.', $html);
    }

    public function test_the_section_list_can_start_from_a_section(): void
    {
        $this->threeLevels();

        $html = Blade::render('<x-nexor::catalog.section-list iblock="katalog" root="mebel" />');

        $this->assertStringContainsString('Стулья', $html);
        $this->assertStringNotContainsString('Декор', $html);
    }

    // ------------------------------------------------------------------- news

    public function test_the_news_list_is_reachable_by_its_bitrix_name(): void
    {
        $iblock = $this->catalogue(['code' => 'news', 'has_sections' => false]);
        $this->element($iblock, 'Открытие магазина');

        // `news.list` — псевдоним класса News\Listing: класса News\List не бывает.
        $html = Blade::render('<x-nexor::news.list iblock="news" />');

        $this->assertStringContainsString('Открытие магазина', $html);
    }

    public function test_the_news_list_puts_the_newest_first(): void
    {
        $iblock = $this->catalogue(['code' => 'news', 'has_sections' => false]);
        $this->element($iblock, 'Старая', ['created_at' => now()->subWeek()]);
        $this->element($iblock, 'Свежая', ['created_at' => now()]);

        $html = Blade::render('<x-nexor::news.list iblock="news" />');

        $this->assertLessThan(mb_strpos($html, 'Старая'), mb_strpos($html, 'Свежая'));
    }

    public function test_the_news_detail_renders_without_a_property_table(): void
    {
        $iblock = $this->catalogue(['code' => 'news', 'has_sections' => false]);
        $property = IblockProperty::factory()->for($iblock)->create(['code' => 'HIDDEN', 'name' => 'Служебное']);

        $element = $this->element($iblock, 'Новость', ['detail_text' => 'Текст', 'detail_text_type' => 'text']);
        $element->values()->create(['property_id' => $property->id, 'value_string' => 'X']);

        $html = Blade::render('<x-nexor::news.detail :element="$element" />', compact('element'));

        $this->assertStringContainsString('Текст', $html);
        $this->assertStringNotContainsString('Служебное', $html);
    }

    // ------------------------------------------------------------------- form

    public function test_the_form_renders_the_requested_fields(): void
    {
        $html = Blade::render(
            '<x-nexor::form :fields="$fields" title="Заказать звонок" />',
            ['fields' => ['name', 'phone']],
        );

        $this->assertStringContainsString('Заказать звонок', $html);
        $this->assertStringContainsString('name="name"', $html);
        $this->assertStringContainsString('name="phone"', $html);
        $this->assertStringNotContainsString('name="message"', $html);
    }

    public function test_the_form_hides_its_settings_from_the_browser(): void
    {
        $html = Blade::render('<x-nexor::form to="sales@example.com" />');

        // Адрес получателя не должен быть виден и подменяем в разметке.
        $this->assertStringNotContainsString('sales@example.com', $html);
        $this->assertStringContainsString('name="_form"', $html);
    }

    public function test_an_unknown_field_is_refused(): void
    {
        $this->expectException(ViewException::class);
        $this->expectExceptionMessage('не знает поля');

        Blade::render('<x-nexor::form :fields="$fields" />', ['fields' => ['kartoshka']]);
    }

    // ----------------------------------------------------------------- search

    public function test_the_search_form_keeps_the_current_query(): void
    {
        $this->get('/?q=стул');

        $html = Blade::render('<x-nexor::search.form />');

        $this->assertStringContainsString('value="стул"', $html);
        $this->assertStringContainsString('method="get"', $html);
    }

    public function test_the_search_page_groups_results_by_infoblock(): void
    {
        $catalogue = $this->catalogue();
        $news = $this->catalogue(['code' => 'news', 'name' => 'Новости', 'has_sections' => false]);

        $this->element($catalogue, 'Стул венский');
        $this->element($news, 'Стулья приехали');
        $this->element($news, 'Ничего общего');

        $this->get('/?q=Стул');

        $html = Blade::render('<x-nexor::search.page />');

        $this->assertStringContainsString('Каталог', $html);
        $this->assertStringContainsString('Новости', $html);
        $this->assertStringContainsString('Стул венский', $html);
        $this->assertStringNotContainsString('Ничего общего', $html);
    }

    public function test_the_search_page_looks_inside_searchable_properties(): void
    {
        $iblock = $this->catalogue();
        $property = IblockProperty::factory()->for($iblock)->create([
            'code' => 'ARTICLE', 'is_searchable' => true,
        ]);

        $this->element($iblock, 'Безымянный')->values()->create([
            'property_id' => $property->id, 'value_string' => 'ART-777',
        ]);

        $this->get('/?q=ART-777');

        $this->assertStringContainsString('Безымянный', Blade::render('<x-nexor::search.page />'));
    }

    public function test_the_search_page_says_nothing_was_found(): void
    {
        $this->catalogue();

        $this->get('/?q=несуществующее');

        $this->assertStringContainsString('ничего не нашлось', Blade::render('<x-nexor::search.page />'));
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
