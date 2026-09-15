<?php

namespace Tests\Feature\PageBuilder;

use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Blade;
use Illuminate\Support\Facades\File;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Facades\View;
use Nexor\Cms\Models\CatalogProduct;
use Nexor\Cms\Models\Iblock;
use Nexor\Cms\Models\IblockElement;
use Nexor\Cms\Models\IblockSection;
use Nexor\Cms\Models\IblockType;
use Nexor\Cms\Support\Nexor;
use Nexor\PageBuilder\Models\Layout;
use Nexor\PageBuilder\PageBuilder;
use Tests\Concerns\CreatesAdminUsers;
use Tests\TestCase;

/**
 * Модуль «Конструктор страниц»: переключатель инфоблока, вкладка элемента,
 * сохранение блоков, загрузка картинок и вывод на сайте.
 */
class PageBuilderTest extends TestCase
{
    use CreatesAdminUsers, RefreshDatabase;

    protected function iblock(bool $builder = true): Iblock
    {
        return Iblock::factory()->create([
            'code' => 'news',
            'is_active' => true,
            'has_sections' => false,
            'settings' => $builder ? ['modules' => ['pagebuilder' => ['detail' => true]]] : null,
        ]);
    }

    /**
     * @param  array<int, array<string, mixed>>  $blocks
     */
    protected function content(array $blocks): string
    {
        return json_encode(['version' => 1, 'blocks' => $blocks], JSON_UNESCAPED_UNICODE);
    }

    // ------------------------------------------------------------- инфоблок

    public function test_the_panel_learns_about_the_iblock_switch(): void
    {
        $this->actingAs($this->superAdmin())
            ->getJson('/admin/api/bootstrap')
            ->assertOk()
            ->assertJsonFragment(['module' => 'pagebuilder', 'key' => 'detail']);
    }

    public function test_the_switch_is_saved_on_the_iblock(): void
    {
        $iblock = $this->iblock(builder: false);

        $this->actingAs($this->superAdmin())
            ->putJson("/admin/api/iblocks/{$iblock->id}", [
                'iblock_type_id' => $iblock->iblock_type_id,
                'code' => $iblock->code,
                'name' => $iblock->name,
                'module_settings' => ['pagebuilder' => ['detail' => true]],
            ])
            ->assertOk()
            ->assertJsonPath('data.module_settings.pagebuilder.detail', true);

        $this->assertTrue($iblock->refresh()->moduleSetting('pagebuilder', 'detail'));
    }

    public function test_saving_the_iblock_keeps_its_form_layout(): void
    {
        $iblock = $this->iblock(builder: false);
        $iblock->forceFill(['settings' => ['form_tabs' => [['key' => 'main', 'label' => 'Главное', 'fields' => ['name']]]]])->save();

        $this->actingAs($this->superAdmin())->putJson("/admin/api/iblocks/{$iblock->id}", [
            'iblock_type_id' => $iblock->iblock_type_id,
            'code' => $iblock->code,
            'name' => $iblock->name,
            'module_settings' => ['pagebuilder' => ['detail' => true]],
        ])->assertOk();

        $this->assertSame('Главное', $iblock->refresh()->settings['form_tabs'][0]['label']);
    }

    // -------------------------------------------------------------- элемент

    public function test_the_element_form_gets_a_builder_tab_only_when_switched_on(): void
    {
        $admin = $this->superAdmin();

        $with = $this->iblock();
        $without = Iblock::factory()->create(['code' => 'plain', 'iblock_type_id' => IblockType::factory()]);

        $tabs = $this->actingAs($admin)->getJson("/admin/api/iblocks/{$with->id}/schema")->json('form_tabs');
        $builderTab = collect($tabs)->firstWhere('key', 'pagebuilder');

        $this->assertSame('Конструктор', $builderTab['label']);
        $this->assertSame(['module:pagebuilder.content'], $builderTab['fields']);

        $plainTabs = $this->actingAs($admin)->getJson("/admin/api/iblocks/{$without->id}/schema")->json('form_tabs');

        $this->assertNull(collect($plainTabs)->firstWhere('key', 'pagebuilder'));
    }

    public function test_blocks_are_saved_cleaned_and_returned_to_the_form(): void
    {
        $iblock = $this->iblock();

        $id = $this->actingAs($this->superAdmin())
            ->postJson("/admin/api/iblocks/{$iblock->id}/elements", [
                'name' => 'Вышка-тура',
                'modules' => ['pagebuilder' => ['content' => $this->content([
                    ['id' => 'h1', 'type' => 'header', 'data' => ['text' => 'Что такое вышка-тура', 'tag' => 'h2', 'cssClass' => 'mb-0']],
                    ['id' => 't1', 'type' => 'text', 'data' => ['text' => '<p>Текст<script>alert(1)</script></p>']],
                    // Пустой блок не сохраняется.
                    ['id' => 'q1', 'type' => 'quote', 'data' => ['text' => '<p><br></p>']],
                ])]],
            ])
            ->assertCreated()
            ->json('data.id');

        $layout = Layout::query()->where('owner_id', $id)->sole();

        $this->assertCount(2, $layout->blocks());
        $this->assertSame('<p>Текст</p>', $layout->blocks()[1]['data']['text']);
        $this->assertSame('mb-0', $layout->blocks()[0]['data']['cssClass']);
        $this->assertSame('Что такое вышка-тура Текст', $layout->search_text);

        $this->getJson("/admin/api/iblocks/{$iblock->id}/elements/{$id}")
            ->assertOk()
            ->assertJsonPath('modules.pagebuilder.content.blocks.0.data.text', 'Что такое вышка-тура');
    }

    public function test_a_broken_block_is_reported_by_its_number(): void
    {
        $iblock = $this->iblock();

        $this->actingAs($this->superAdmin())
            ->postJson("/admin/api/iblocks/{$iblock->id}/elements", [
                'name' => 'Страница',
                'modules' => ['pagebuilder' => ['content' => $this->content([
                    ['type' => 'header', 'data' => ['text' => 'Ок']],
                    ['type' => 'video', 'data' => ['url' => 'https://evil.example/video']],
                ])]],
            ])
            ->assertUnprocessable()
            ->assertJsonValidationErrors(['modules.pagebuilder.content' => 'Блок 2 «Видео»']);

        $this->assertSame(0, Layout::query()->count());
    }

    public function test_an_unknown_block_type_is_rejected(): void
    {
        $iblock = $this->iblock();

        $this->actingAs($this->superAdmin())
            ->postJson("/admin/api/iblocks/{$iblock->id}/elements", [
                'name' => 'Страница',
                'modules' => ['pagebuilder' => ['content' => $this->content([['type' => 'script', 'data' => []]])]],
            ])
            ->assertJsonValidationErrors('modules.pagebuilder.content');
    }

    public function test_removing_every_block_brings_the_detail_text_back(): void
    {
        $iblock = $this->iblock();
        $element = IblockElement::factory()->for($iblock)->create();
        PageBuilder::save($element, ['blocks' => [['type' => 'header', 'data' => ['text' => 'Был']]]]);

        $this->actingAs($this->superAdmin())
            ->putJson("/admin/api/iblocks/{$iblock->id}/elements/{$element->id}", [
                'name' => $element->name,
                'modules' => ['pagebuilder' => ['content' => $this->content([])]],
            ])
            ->assertOk();

        $this->assertSame(0, Layout::query()->count());
    }

    public function test_a_switched_off_module_ignores_the_blocks(): void
    {
        $iblock = $this->iblock();
        Nexor::modules()->setEnabled('pagebuilder', false);

        $this->actingAs($this->superAdmin())
            ->postJson("/admin/api/iblocks/{$iblock->id}/elements", [
                'name' => 'Страница',
                'modules' => ['pagebuilder' => ['content' => $this->content([['type' => 'header', 'data' => ['text' => 'Х']]])]],
            ])
            ->assertCreated();

        $this->assertSame(0, Layout::query()->count());
    }

    // ------------------------------------------------------------- загрузка

    public function test_an_editor_of_the_iblock_uploads_a_picture(): void
    {
        Storage::fake('public');
        $iblock = $this->iblock();
        $editor = $this->grantIblock($this->adminWith(), $iblock, ['view', 'update']);

        $path = $this->actingAs($editor)
            ->post('/admin/api/pagebuilder/uploads', [
                'iblock' => $iblock->id,
                'file' => UploadedFile::fake()->image('tura.jpg'),
            ], ['Accept' => 'application/json'])
            ->assertCreated()
            ->json('path');

        Storage::disk('public')->assertExists($path);
    }

    public function test_someone_without_rights_on_the_iblock_cannot_upload(): void
    {
        Storage::fake('public');
        $iblock = $this->iblock();

        $this->actingAs($this->grantIblock($this->adminWith(), $iblock, ['view']))
            ->post('/admin/api/pagebuilder/uploads', [
                'iblock' => $iblock->id,
                'file' => UploadedFile::fake()->image('tura.jpg'),
            ], ['Accept' => 'application/json'])
            ->assertForbidden();
    }

    public function test_a_document_is_uploaded_but_html_dressed_as_pdf_is_not(): void
    {
        Storage::fake('public');
        $iblock = $this->iblock();
        $admin = $this->superAdmin();

        $this->actingAs($admin)
            ->post('/admin/api/pagebuilder/uploads', [
                'iblock' => $iblock->id,
                'kind' => 'document',
                'file' => UploadedFile::fake()->createWithContent('price.pdf', "%PDF-1.4\n1 0 obj<<>>endobj\ntrailer<<>>\n%%EOF"),
            ], ['Accept' => 'application/json'])
            ->assertCreated();

        $this->actingAs($admin)
            ->post('/admin/api/pagebuilder/uploads', [
                'iblock' => $iblock->id,
                'kind' => 'document',
                // Фейковый файл узнаёт тип по имени — задаём тот, что определит содержимое.
                'file' => UploadedFile::fake()->createWithContent('price.pdf', '<html><script>alert(1)</script></html>')->mimeType('text/html'),
            ], ['Accept' => 'application/json'])
            ->assertJsonValidationErrors('file');
    }

    public function test_svg_and_html_are_not_accepted_as_pictures(): void
    {
        Storage::fake('public');
        $iblock = $this->iblock();

        foreach (['logo.svg' => 'image/svg+xml', 'page.html' => 'text/html'] as $name => $mime) {
            $this->actingAs($this->superAdmin())
                ->post('/admin/api/pagebuilder/uploads', [
                    'iblock' => $iblock->id,
                    'file' => UploadedFile::fake()->createWithContent($name, '<svg onload="alert(1)"></svg>')->mimeType($mime),
                ], ['Accept' => 'application/json'])
                ->assertJsonValidationErrors('file');
        }
    }

    public function test_the_palette_lists_the_blocks(): void
    {
        $types = collect($this->actingAs($this->superAdmin())->getJson('/admin/api/pagebuilder/blocks')->assertOk()->json('data'))
            ->pluck('type');

        $this->assertSame(
            ['header', 'text', 'quote', 'text_image', 'photo', 'slider', 'video', 'accordion', 'tabs', 'table', 'link_cards', 'catalog_list'],
            $types->all(),
        );
    }

    // ----------------------------------------------------------------- сайт

    public function test_the_detail_page_shows_blocks_instead_of_the_detail_text(): void
    {
        $iblock = $this->iblock();
        $element = IblockElement::factory()->for($iblock)->create([
            'detail_text' => 'Старый подробный текст',
            'detail_text_type' => 'text',
        ]);

        PageBuilder::save($element, ['blocks' => [
            ['id' => 'b1', 'type' => 'header', 'data' => ['text' => 'Заголовок блока', 'tag' => 'h3', 'cssClass' => 'my-header']],
            ['id' => 'b2', 'type' => 'video', 'data' => ['url' => 'https://youtu.be/dQw4w9WgXcQ', 'layout' => 'split', 'reverse' => true, 'text' => '<p>Рядом</p>']],
            ['id' => 'b3', 'type' => 'table', 'data' => ['header' => true, 'rows' => [['Высота', 'Цена'], ['2 м', '<b>1000</b>']]]],
            ['id' => 'b4', 'type' => 'accordion', 'data' => ['items' => [['title' => 'Вопрос', 'content' => '<p>Ответ</p>']]]],
        ]]);

        $html = Blade::render('<x-nexor::news.detail :element="$element" />', ['element' => $element->fresh('iblock')]);

        // Вёрстка как у образца: классы pb-* и nw-*.
        $this->assertStringContainsString('<div class="pb-wrapper" data-pb-standalone="true">', $html);
        $this->assertStringContainsString('class="pb-block-wrapper" data-type="header"', $html);
        $this->assertStringContainsString('class="pb-block pb-header my-header"', $html);
        $this->assertMatchesRegularExpression('~<h3 class="pb-header__title">\s*Заголовок блока\s*</h3>~u', $html);
        $this->assertStringContainsString('class="pb-block pb-video pb-video--split pb-video--reverse "', $html);
        $this->assertStringContainsString('https://www.youtube.com/embed/dQw4w9WgXcQ', $html);
        $this->assertStringContainsString('<th>Высота</th>', $html);
        $this->assertStringContainsString('<td>&lt;b&gt;1000&lt;/b&gt;</td>', $html);
        $this->assertStringContainsString('class="nw-accordion__item" data-accordion-item data-active', $html);
        $this->assertStringContainsString('nexor-pagebuilder/assets/pagebuilder.css', $html);
        $this->assertStringNotContainsString('Старый подробный текст', $html);
    }

    public function test_the_photo_block_hides_extra_pictures_under_a_counter(): void
    {
        $element = IblockElement::factory()->for($this->iblock())->create();

        PageBuilder::save($element, ['blocks' => [[
            'id' => 'g1',
            'type' => 'photo',
            'data' => [
                'presetId' => '3_asym',
                'asymmetricDir' => 'left',
                'images' => array_map(fn (int $i) => ['path' => "pagebuilder/{$i}.jpg", 'alt' => "Фото {$i}"], range(1, 5)),
            ],
        ]]]);

        $html = PageBuilder::render($element->fresh('iblock'), ['assets' => false])->toHtml();

        $this->assertStringContainsString('class="pb-photo__grid pb-layout-3_asym--left"', $html);
        $this->assertSame(2, substr_count($html, 'pb-photo__item--hidden'));
        $this->assertStringContainsString('<span>+2</span>', $html);
        $this->assertStringContainsString('data-fancybox="gallery-g1"', $html);
        $this->assertStringContainsString('data-pb-standalone="false"', $html);
        $this->assertStringNotContainsString('pagebuilder.css', $html);
    }

    public function test_the_sidebar_links_to_the_blocks(): void
    {
        $element = IblockElement::factory()->for($this->iblock())->create();

        PageBuilder::save($element, [
            'settings' => ['showSidebar' => true, 'sidebarItems' => [
                ['text' => 'К заголовку', 'targetId' => 'h1'],
                ['text' => 'В никуда', 'targetId' => 'missing'],
            ]],
            'blocks' => [['id' => 'h1', 'type' => 'header', 'data' => ['text' => 'Раздел']]],
        ]);

        $html = PageBuilder::render($element->fresh('iblock'))->toHtml();

        $this->assertStringContainsString('<div class="pb-wrapper has-sidebar"', $html);
        $this->assertStringContainsString('id="block-h1"', $html);
        $this->assertStringContainsString('<a href="#block-h1" class="pb-aside__menu-link js-scroll-to" rel="nofollow">К заголовку</a>', $html);
        $this->assertStringNotContainsString('В никуда', $html);
    }

    public function test_tabs_render_with_the_chosen_tab_open(): void
    {
        $element = IblockElement::factory()->for($this->iblock())->create();

        PageBuilder::save($element, ['blocks' => [[
            'id' => 't1',
            'type' => 'tabs',
            'data' => [
                'activeTabId' => 'second',
                'items' => [
                    ['id' => 'first', 'title' => 'Описание', 'content' => '<p>Раз</p>'],
                    ['id' => 'second', 'title' => 'Характеристики', 'content' => '<p>Два<script>x</script></p>'],
                    ['id' => 'empty', 'title' => '', 'content' => '<p><br></p>'],
                ],
            ],
        ]]]);

        $html = PageBuilder::render($element->fresh('iblock'))->toHtml();

        $this->assertStringContainsString('class="pb-block pb-tabs "', $html);
        $this->assertStringContainsString('<div class="nw-pb-tabs" data-tabs>', $html);
        $this->assertSame(2, substr_count($html, 'class="nw-pb-tabs__btn"'));
        $this->assertMatchesRegularExpression('~data-tab-target="tab_t1_second"\s+data-active~', $html);
        $this->assertMatchesRegularExpression('~data-tab-content="tab_t1_first"\s+hidden~', $html);
        $this->assertStringNotContainsString('<script>x', $html);
    }

    public function test_link_cards_point_at_the_uploaded_file_or_the_link(): void
    {
        $element = IblockElement::factory()->for($this->iblock())->create();

        PageBuilder::save($element, ['blocks' => [[
            'id' => 'l1',
            'type' => 'link_cards',
            'data' => [
                'variant' => 'docs',
                'cols' => 2,
                'items' => [
                    ['text' => 'Прайс', 'file' => ['path' => 'pagebuilder/price.pdf'], 'isDownload' => true],
                    ['text' => "Сайт\nпартнёра", 'link' => 'https://example.com'],
                ],
            ],
        ]]]);

        $html = PageBuilder::render($element->fresh('iblock'))->toHtml();

        $this->assertStringContainsString('class="pb-block pb-link-cards pb-cards--docs "', $html);
        $this->assertStringContainsString('class="pb-cards-grid pb-cards-grid--cols-2"', $html);
        $this->assertMatchesRegularExpression('~href="[^"]*pagebuilder/price\.pdf" class="pb-card-item"\s+download~', $html);
        $this->assertMatchesRegularExpression('~href="https://example\.com" class="pb-card-item"\s+target="_blank" rel="nofollow"~', $html);
        $this->assertStringContainsString('Сайт<br />', $html);
    }

    public function test_a_link_card_cannot_carry_javascript(): void
    {
        $iblock = $this->iblock();

        $this->actingAs($this->superAdmin())
            ->postJson("/admin/api/iblocks/{$iblock->id}/elements", [
                'name' => 'Страница',
                'modules' => ['pagebuilder' => ['content' => $this->content([
                    ['type' => 'link_cards', 'data' => ['items' => [['text' => 'Х', 'link' => 'javascript:alert(1)']]]],
                ])]],
            ])
            ->assertJsonValidationErrors('modules.pagebuilder.content');
    }

    public function test_the_slider_renders_swiper_markup(): void
    {
        $element = IblockElement::factory()->for($this->iblock())->create();

        PageBuilder::save($element, ['blocks' => [[
            'id' => 's1',
            'type' => 'slider',
            'data' => [
                'slidesPerView' => 2,
                'gap' => 10,
                'autoplay' => true,
                'dots' => false,
                'images' => [['path' => 'pagebuilder/1.jpg', 'alt' => 'Один'], ['path' => 'pagebuilder/2.jpg']],
            ],
        ]]]);

        $html = PageBuilder::render($element->fresh('iblock'))->toHtml();

        $this->assertStringContainsString('class="pb-block pb-slider "', $html);
        $this->assertMatchesRegularExpression('~class="swiper js-pb-slider"\s+data-slides="2"\s+data-gap="10"\s+data-autoplay="true"~', $html);
        $this->assertSame(2, substr_count($html, '<div class="swiper-slide">'));
        $this->assertStringContainsString('swiper-button-next', $html);
        $this->assertStringNotContainsString('swiper-pagination', $html);
    }

    public function test_the_catalog_block_shows_published_elements_of_a_section_with_its_children(): void
    {
        $page = IblockElement::factory()->for($this->iblock())->create();

        $catalog = Iblock::factory()->create(['code' => 'katalog', 'is_active' => true, 'is_catalog' => true]);
        $root = IblockSection::factory()->create(['iblock_id' => $catalog->id, 'code' => 'stulya']);
        $child = IblockSection::factory()->childOf($root)->create(['code' => 'barnye']);
        $other = IblockSection::factory()->create(['iblock_id' => $catalog->id, 'code' => 'stoly']);

        $inRoot = IblockElement::factory()->for($catalog)->create(['name' => 'Стул венский', 'section_id' => $root->id, 'is_active' => true, 'sort' => 1]);
        IblockElement::factory()->for($catalog)->create(['name' => 'Стул барный', 'section_id' => $child->id, 'is_active' => true, 'sort' => 2]);
        IblockElement::factory()->for($catalog)->create(['name' => 'Стол', 'section_id' => $other->id, 'is_active' => true]);
        IblockElement::factory()->for($catalog)->create(['name' => 'Стул в архиве', 'section_id' => $root->id, 'is_active' => false]);
        CatalogProduct::factory()->create(['element_id' => $inRoot->id, 'price' => 1500, 'discount_percent' => 0]);

        PageBuilder::save($page, ['blocks' => [[
            'id' => 'c1',
            'type' => 'catalog_list',
            'data' => [
                'title' => 'Похожие товары',
                'source' => ['iblockId' => $catalog->id, 'selectionMode' => 'section', 'sectionId' => $root->id, 'sortBy' => 'sort'],
                'view' => ['slidesPerView' => 3, 'arrows' => false],
            ],
        ]]]);

        $html = PageBuilder::render($page->fresh('iblock'))->toHtml();

        $this->assertStringContainsString('class="pb-block pb-catalog-list "', $html);
        $this->assertStringContainsString('<h2 class="pb-block-title">Похожие товары</h2>', $html);
        $this->assertMatchesRegularExpression('~data-slides="3"~', $html);
        $this->assertLessThan(mb_strpos($html, 'Стул барный'), mb_strpos($html, 'Стул венский'));
        $this->assertStringNotContainsString('Стол<', $html);
        $this->assertStringNotContainsString('Стул в архиве', $html);
        // Карточка каталога с ценой; кнопка магазина — внутри @feature('shop').
        $this->assertStringContainsString('1 500', $html);
        $this->assertStringNotContainsString('swiper-button-next', $html);
    }

    public function test_hand_picked_catalog_elements_keep_their_order_and_their_iblock(): void
    {
        $page = IblockElement::factory()->for($this->iblock())->create();
        $catalog = Iblock::factory()->create(['code' => 'katalog', 'is_active' => true]);
        $foreign = Iblock::factory()->create(['code' => 'drugoy', 'is_active' => true]);

        $first = IblockElement::factory()->for($catalog)->create(['name' => 'Первый', 'is_active' => true]);
        $second = IblockElement::factory()->for($catalog)->create(['name' => 'Второй', 'is_active' => true]);
        $stranger = IblockElement::factory()->for($foreign)->create(['name' => 'Чужой', 'is_active' => true]);

        $layout = PageBuilder::save($page, ['blocks' => [[
            'type' => 'catalog_list',
            'data' => ['source' => [
                'iblockId' => $catalog->id,
                'selectionMode' => 'manual',
                'manualItems' => [['id' => $second->id], ['id' => $stranger->id], ['id' => $first->id]],
            ]],
        ]]]);

        $this->assertSame(
            [['id' => $second->id, 'name' => 'Второй'], ['id' => $first->id, 'name' => 'Первый']],
            $layout->blocks()[0]['data']['source']['manualItems'],
        );

        $html = PageBuilder::render($page->fresh('iblock'))->toHtml();

        $this->assertLessThan(mb_strpos($html, 'Первый'), mb_strpos($html, 'Второй'));
        $this->assertStringNotContainsString('Чужой', $html);
    }

    public function test_the_catalog_block_uses_the_chosen_card_template(): void
    {
        // Свой шаблон карточки — во временной папке, чтобы не трогать шаблоны сайта.
        $views = storage_path('framework/testing/pagebuilder-cards');
        File::ensureDirectoryExists($views.'/components/catalog/card');
        File::put($views.'/components/catalog/card/mini.blade.php', '<div class="mini-card">{{ $element->name }}</div>');
        View::prependNamespace('nexor', $views);

        try {
            $page = IblockElement::factory()->for($this->iblock())->create();
            $catalog = Iblock::factory()->create(['code' => 'katalog', 'is_active' => true]);
            IblockElement::factory()->for($catalog)->create(['name' => 'Стул', 'is_active' => true]);

            $layout = PageBuilder::save($page, ['blocks' => [[
                'type' => 'catalog_list',
                'data' => [
                    // Вставили вместе с префиксом — он отрезается.
                    'cardTemplate' => 'nexor::components.catalog.card.mini',
                    'source' => ['iblockId' => $catalog->id, 'selectionMode' => 'all'],
                ],
            ]]]);

            $this->assertSame('catalog.card.mini', $layout->blocks()[0]['data']['cardTemplate']);
            $this->assertStringContainsString('<div class="mini-card">Стул</div>', PageBuilder::render($page->fresh('iblock'))->toHtml());

            $this->actingAs($this->superAdmin())
                ->getJson('/admin/api/pagebuilder/card-templates')
                ->assertOk()
                ->assertJsonPath('prefix', 'nexor::components.')
                ->assertJsonFragment(['catalog.card.mini']);

            // Шаблон удалили — на сайте стандартная карточка, а не ошибка.
            File::delete($views.'/components/catalog/card/mini.blade.php');
            View::getFinder()->flush();

            $html = PageBuilder::render($page->fresh('iblock'))->toHtml();

            $this->assertStringNotContainsString('mini-card', $html);
            $this->assertStringContainsString('Стул', $html);
        } finally {
            File::deleteDirectory($views);
        }
    }

    public function test_an_unknown_card_template_is_reported(): void
    {
        $iblock = $this->iblock();
        $catalog = Iblock::factory()->create(['code' => 'katalog', 'is_active' => true]);

        $this->actingAs($this->superAdmin())
            ->postJson("/admin/api/iblocks/{$iblock->id}/elements", [
                'name' => 'Страница',
                'modules' => ['pagebuilder' => ['content' => $this->content([[
                    'type' => 'catalog_list',
                    'data' => ['cardTemplate' => 'catalog.card.net-takogo', 'source' => ['iblockId' => $catalog->id, 'selectionMode' => 'all']],
                ]])]],
            ])
            ->assertJsonValidationErrors(['modules.pagebuilder.content' => 'шаблон карточки catalog.card.net-takogo не найден']);
    }

    public function test_blocks_saved_without_newer_keys_still_render(): void
    {
        $element = IblockElement::factory()->for($this->iblock())->create();

        // Как если бы блок сохранили до появления части настроек.
        Layout::query()->create([
            'owner_type' => Layout::OWNER_ELEMENT,
            'owner_id' => $element->id,
            'content' => ['version' => 1, 'blocks' => [
                ['id' => 'a', 'type' => 'header', 'data' => ['text' => 'Старый']],
                ['id' => 'b', 'type' => 'video', 'data' => ['url' => 'https://youtu.be/dQw4w9WgXcQ']],
                ['id' => 'c', 'type' => 'photo', 'data' => []],
            ]],
        ]);

        $html = PageBuilder::render($element->fresh('iblock'))->toHtml();

        $this->assertStringContainsString('<h2 class="pb-header__title">', $html);
        $this->assertStringContainsString('pb-video__wrapper', $html);
    }

    public function test_a_css_class_cannot_break_out_of_the_attribute(): void
    {
        $iblock = $this->iblock();

        $this->actingAs($this->superAdmin())
            ->postJson("/admin/api/iblocks/{$iblock->id}/elements", [
                'name' => 'Страница',
                'modules' => ['pagebuilder' => ['content' => $this->content([
                    ['type' => 'header', 'data' => ['text' => 'Х', 'cssClass' => '" onmouseover="alert(1)']],
                ])]],
            ])
            ->assertJsonValidationErrors('modules.pagebuilder.content');
    }

    public function test_the_site_assets_are_served_from_the_package(): void
    {
        $this->get('/nexor-pagebuilder/assets/pagebuilder.js')->assertOk();
        $this->get('/nexor-pagebuilder/assets/pagebuilder.css')->assertOk();
        $this->get('/nexor-pagebuilder/assets/composer.json')->assertNotFound();
    }

    public function test_without_blocks_the_detail_text_is_shown(): void
    {
        $element = IblockElement::factory()->for($this->iblock())->create([
            'detail_text' => 'Подробный текст',
            'detail_text_type' => 'text',
        ]);

        $html = Blade::render('<x-nexor::news.detail :element="$element" />', ['element' => $element->fresh('iblock')]);

        $this->assertStringContainsString('Подробный текст', $html);
        $this->assertStringNotContainsString('data-pagebuilder', $html);
    }

    public function test_turning_the_switch_off_brings_the_detail_text_back_but_keeps_the_blocks(): void
    {
        $iblock = $this->iblock();
        $element = IblockElement::factory()->for($iblock)->create(['detail_text' => 'Подробный текст', 'detail_text_type' => 'text']);
        PageBuilder::save($element, ['blocks' => [['type' => 'header', 'data' => ['text' => 'Блок']]]]);

        $iblock->forceFill(['settings' => ['modules' => ['pagebuilder' => ['detail' => false]]]])->save();

        $html = Blade::render('<x-nexor::news.detail :element="$element" />', ['element' => $element->fresh('iblock')]);

        $this->assertStringContainsString('Подробный текст', $html);
        $this->assertSame(1, Layout::query()->count());
    }
}
