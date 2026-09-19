<?php

namespace Tests\Feature;

use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Blade;
use Illuminate\View\ViewException;
use Nexor\Cms\Enums\MenuItemType;
use Nexor\Cms\Enums\MenuVisibility;
use Nexor\Cms\Models\Iblock;
use Nexor\Cms\Models\IblockElement;
use Nexor\Cms\Models\IblockSection;
use Nexor\Cms\Models\Menu;
use Nexor\Cms\Models\MenuItem;
use Nexor\Cms\Support\MenuResolver;
use Tests\Concerns\CreatesAdminUsers;
use Tests\TestCase;

/**
 * Меню сайта: сборка дерева, динамические ветки разделов, подсветка текущей
 * страницы и экран настройки в панели.
 */
class MenuTest extends TestCase
{
    use CreatesAdminUsers, RefreshDatabase;

    protected function menu(string $code = 'main'): Menu
    {
        return Menu::factory()->create(['code' => $code, 'name' => 'Меню', 'is_active' => true]);
    }

    protected function resolver(): MenuResolver
    {
        return app(MenuResolver::class);
    }

    /** Каталог с тремя уровнями разделов. */
    protected function catalogue(): Iblock
    {
        $iblock = Iblock::factory()->create([
            'code' => 'katalog', 'name' => 'Каталог', 'has_sections' => true, 'is_active' => true,
        ]);

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

    // ------------------------------------------------------------------ сборка

    public function test_hand_written_links_come_back_in_order(): void
    {
        $menu = $this->menu();

        MenuItem::factory()->for($menu)->create(['title' => 'Второй', 'url' => '/two', 'sort' => 200]);
        MenuItem::factory()->for($menu)->create(['title' => 'Первый', 'url' => '/one', 'sort' => 100]);

        $tree = $this->resolver()->tree('main', url('/'));

        $this->assertSame(['Первый', 'Второй'], array_column($tree, 'name'));
    }

    public function test_items_nest_under_their_parent(): void
    {
        $menu = $this->menu();
        $parent = MenuItem::factory()->for($menu)->create(['title' => 'Услуги', 'url' => '/services']);
        MenuItem::factory()->for($menu)->create([
            'parent_id' => $parent->id, 'title' => 'Доставка', 'url' => '/services/delivery',
        ]);

        $tree = $this->resolver()->tree('main', url('/'));

        $this->assertSame(['Доставка'], array_column($tree[0]['children'], 'name'));
    }

    public function test_a_hidden_item_stays_out(): void
    {
        $menu = $this->menu();
        MenuItem::factory()->for($menu)->create(['title' => 'Виден', 'url' => '/one']);
        MenuItem::factory()->for($menu)->hidden()->create(['title' => 'Скрыт', 'url' => '/two']);

        $this->assertSame(['Виден'], array_column($this->resolver()->tree('main', url('/')), 'name'));
    }

    public function test_an_unknown_menu_renders_nothing(): void
    {
        $this->assertSame([], $this->resolver()->tree('net-takogo', url('/')));
    }

    // ------------------------------------------------------- ссылки на сущности

    public function test_a_page_item_takes_its_address_from_the_element(): void
    {
        $iblock = Iblock::factory()->withoutSections()->create(['code' => 'pages', 'is_active' => true]);
        $element = IblockElement::factory()->for($iblock)->create(['name' => 'О компании', 'code' => 'about']);

        $menu = $this->menu();
        MenuItem::factory()->for($menu)->ofType(MenuItemType::Page)->create([
            'title' => null, 'url' => null, 'iblock_id' => $iblock->id, 'element_id' => $element->id,
        ]);

        $tree = $this->resolver()->tree('main', url('/'));

        $this->assertSame('О компании', $tree[0]['name']);
        $this->assertSame(url('/about'), $tree[0]['url']);
    }

    public function test_renaming_the_element_code_does_not_break_the_menu(): void
    {
        $iblock = Iblock::factory()->withoutSections()->create(['code' => 'pages', 'is_active' => true]);
        $element = IblockElement::factory()->for($iblock)->create(['name' => 'О компании', 'code' => 'about']);

        $menu = $this->menu();
        MenuItem::factory()->for($menu)->ofType(MenuItemType::Page)->create([
            'title' => null, 'url' => null, 'iblock_id' => $iblock->id, 'element_id' => $element->id,
        ]);

        $element->update(['code' => 'o-kompanii']);
        MenuResolver::forget();

        // Ради этого страницы и хранятся ссылкой, а не записанным руками адресом.
        $this->assertSame(url('/o-kompanii'), $this->resolver()->tree('main', url('/'))[0]['url']);
    }

    // --------------------------------------------------------- динамический пункт

    public function test_a_dynamic_item_expands_into_the_section_tree(): void
    {
        $iblock = $this->catalogue();
        $menu = $this->menu();

        MenuItem::factory()->for($menu)->create(['title' => 'Главная', 'url' => '/', 'sort' => 100]);
        MenuItem::factory()->for($menu)->ofType(MenuItemType::Sections)->create([
            'title' => null, 'url' => null, 'iblock_id' => $iblock->id, 'max_depth' => 2, 'sort' => 200,
        ]);

        $tree = $this->resolver()->tree('main', url('/no-such'));

        // Ветка встала на своё место, а не в конец и не отдельным пунктом.
        $this->assertSame(['Главная', 'Мебель', 'Декор'], array_column($tree, 'name'));
        $this->assertSame(['Стулья'], array_column($tree[1]['children'], 'name'));
    }

    public function test_a_dynamic_item_respects_its_depth(): void
    {
        $iblock = $this->catalogue();
        $menu = $this->menu();

        MenuItem::factory()->for($menu)->ofType(MenuItemType::Sections)->create([
            'title' => null, 'url' => null, 'iblock_id' => $iblock->id, 'max_depth' => 1,
        ]);

        $tree = $this->resolver()->tree('main', url('/no-such'));

        $this->assertSame(['Мебель', 'Декор'], array_column($tree, 'name'));
        $this->assertSame([], $tree[0]['children']);
    }

    public function test_a_dynamic_item_can_show_its_own_title(): void
    {
        $iblock = $this->catalogue();
        $menu = $this->menu();

        MenuItem::factory()->for($menu)->create(['title' => 'Главная', 'url' => '/', 'sort' => 100]);
        MenuItem::factory()->for($menu)->ofType(MenuItemType::Sections)->create([
            'title' => null, 'url' => null, 'iblock_id' => $iblock->id,
            'max_depth' => 2, 'with_title' => true, 'sort' => 200,
        ]);

        $tree = $this->resolver()->tree('main', url('/no-such'));

        // Пункт больше не растворяется: своё имя — от инфоблока, разделы — внутрь.
        $this->assertSame(['Главная', 'Каталог'], array_column($tree, 'name'));
        $this->assertSame(url('/katalog'), $tree[1]['url']);
        $this->assertSame(['Мебель', 'Декор'], array_column($tree[1]['children'], 'name'));
        $this->assertSame([2, 2], array_column($tree[1]['children'], 'level'));
        $this->assertSame(['Стулья'], array_column($tree[1]['children'][0]['children'], 'name'));
    }

    public function test_the_shown_title_can_be_written_by_hand(): void
    {
        $iblock = $this->catalogue();
        $menu = $this->menu();

        MenuItem::factory()->for($menu)->ofType(MenuItemType::Sections)->create([
            'title' => 'Вся продукция', 'url' => null, 'iblock_id' => $iblock->id,
            'max_depth' => 1, 'with_title' => true,
        ]);

        $tree = $this->resolver()->tree('main', url('/no-such'));

        $this->assertSame(['Вся продукция'], array_column($tree, 'name'));
        $this->assertSame(['Мебель', 'Декор'], array_column($tree[0]['children'], 'name'));
    }

    public function test_the_chosen_section_becomes_the_title_instead_of_a_second_item(): void
    {
        $iblock = $this->catalogue();
        $mebel = IblockSection::query()->where('code', 'mebel')->firstOrFail();
        $menu = $this->menu();

        MenuItem::factory()->for($menu)->ofType(MenuItemType::Sections)->create([
            'title' => null, 'url' => null, 'iblock_id' => $iblock->id,
            'section_id' => $mebel->id, 'max_depth' => 2, 'with_title' => true,
        ]);

        $tree = $this->resolver()->tree('main', url('/no-such'));

        // «Мебель» ровно одна: названием пункта, а не названием и первым разделом.
        $this->assertSame(['Мебель'], array_column($tree, 'name'));
        $this->assertSame($mebel->url(), $tree[0]['url']);

        // Корень в глубину не считается: два уровня — это «Стулья» и «Венские».
        $this->assertSame(['Стулья'], array_column($tree[0]['children'], 'name'));
        $this->assertSame(['Венские'], array_column($tree[0]['children'][0]['children'], 'name'));
    }

    public function test_the_shown_title_lights_up_on_a_section_page(): void
    {
        $iblock = $this->catalogue();
        $menu = $this->menu();

        MenuItem::factory()->for($menu)->ofType(MenuItemType::Sections)->create([
            'title' => null, 'url' => null, 'iblock_id' => $iblock->id,
            'max_depth' => 2, 'with_title' => true, 'highlight_children' => true,
        ]);

        $tree = $this->resolver()->tree('main', url('/katalog/mebel'));

        $this->assertTrue($tree[0]['open']);
        $this->assertFalse($tree[0]['active']);
        $this->assertTrue($tree[0]['children'][0]['active']);
    }

    public function test_a_dynamic_item_can_start_from_a_section(): void
    {
        $iblock = $this->catalogue();
        $mebel = IblockSection::query()->where('code', 'mebel')->firstOrFail();
        $menu = $this->menu();

        MenuItem::factory()->for($menu)->ofType(MenuItemType::Sections)->create([
            'title' => null, 'url' => null,
            'iblock_id' => $iblock->id, 'section_id' => $mebel->id, 'max_depth' => 2,
        ]);

        $tree = $this->resolver()->tree('main', url('/no-such'));

        $this->assertSame(['Мебель'], array_column($tree, 'name'));
        $this->assertSame(['Стулья'], array_column($tree[0]['children'], 'name'));
    }

    // ------------------------------------------------------------------ подсветка

    public function test_the_current_page_is_marked_active(): void
    {
        $menu = $this->menu();
        MenuItem::factory()->for($menu)->create(['title' => 'Каталог', 'url' => '/katalog']);
        MenuItem::factory()->for($menu)->create(['title' => 'Новости', 'url' => '/news']);

        $tree = $this->resolver()->tree('main', url('/katalog'));

        $this->assertTrue($tree[0]['active']);
        $this->assertFalse($tree[1]['active']);
    }

    public function test_a_nested_page_highlights_the_deepest_match(): void
    {
        $menu = $this->menu();
        $parent = MenuItem::factory()->for($menu)->create(['title' => 'Каталог', 'url' => '/katalog']);
        MenuItem::factory()->for($menu)->create([
            'parent_id' => $parent->id, 'title' => 'Раздел 1', 'url' => '/katalog/razdel1',
        ]);

        $tree = $this->resolver()->tree('main', url('/katalog/razdel1'));

        // Подсвечен раздел, а родитель только раскрыт.
        $this->assertFalse($tree[0]['active']);
        $this->assertTrue($tree[0]['open']);
        $this->assertTrue($tree[0]['children'][0]['active']);
    }

    public function test_a_parent_is_active_when_nothing_deeper_matches(): void
    {
        $menu = $this->menu();
        MenuItem::factory()->for($menu)->create(['title' => 'Каталог', 'url' => '/katalog']);

        $this->assertTrue($this->resolver()->tree('main', url('/katalog/razdel1'))[0]['active']);
    }

    public function test_highlighting_stops_at_a_segment_boundary(): void
    {
        $menu = $this->menu();
        MenuItem::factory()->for($menu)->create(['title' => 'Каталог', 'url' => '/katalog']);

        // `/katalogi` — другая страница, а не вложенная в `/katalog`.
        $this->assertFalse($this->resolver()->tree('main', url('/katalogi'))[0]['active']);
    }

    public function test_a_trailing_slash_does_not_change_the_match(): void
    {
        $menu = $this->menu();
        MenuItem::factory()->for($menu)->create(['title' => 'Каталог', 'url' => '/katalog']);

        $this->assertTrue($this->resolver()->tree('main', url('/katalog/'))[0]['active']);
    }

    public function test_an_item_can_opt_out_of_highlighting_children(): void
    {
        $menu = $this->menu();
        MenuItem::factory()->for($menu)->create([
            'title' => 'Главная', 'url' => '/katalog', 'highlight_children' => false,
        ]);

        $this->assertFalse($this->resolver()->tree('main', url('/katalog/razdel1'))[0]['active']);
    }

    // ------------------------------------------------------------------ видимость

    public function test_items_are_filtered_by_who_is_looking(): void
    {
        $menu = $this->menu();
        MenuItem::factory()->for($menu)->create([
            'title' => 'Войти', 'url' => '/login', 'visibility' => MenuVisibility::Guests,
        ]);
        MenuItem::factory()->for($menu)->create([
            'title' => 'Кабинет', 'url' => '/account', 'visibility' => MenuVisibility::Users,
        ]);

        $this->assertSame(['Войти'], array_column($this->resolver()->tree('main', url('/')), 'name'));

        $this->actingAs($this->adminWith());

        $this->assertSame(['Кабинет'], array_column($this->resolver()->tree('main', url('/')), 'name'));
    }

    // ---------------------------------------------------------------------- кеш

    public function test_the_tree_is_cached_and_dropped_on_any_edit(): void
    {
        config(['nexor.menu.cache' => true]);

        $menu = $this->menu();
        MenuItem::factory()->for($menu)->create(['title' => 'Первый', 'url' => '/one']);

        $this->assertCount(1, $this->resolver()->cached('main'));

        MenuItem::factory()->for($menu)->create(['title' => 'Второй', 'url' => '/two']);

        // Редактор должен видеть результат своей правки сразу, а не через час.
        $this->assertCount(2, $this->resolver()->cached('main'));
    }

    public function test_editing_a_section_drops_the_cache_too(): void
    {
        config(['nexor.menu.cache' => true]);

        $iblock = $this->catalogue();
        $menu = $this->menu();

        MenuItem::factory()->for($menu)->ofType(MenuItemType::Sections)->create([
            'title' => null, 'url' => null, 'iblock_id' => $iblock->id, 'max_depth' => 1,
        ]);

        $this->assertCount(2, $this->resolver()->cached('main'));

        IblockSection::factory()->create(['iblock_id' => $iblock->id, 'name' => 'Свет', 'code' => 'svet']);

        $this->assertCount(3, $this->resolver()->cached('main'));
    }

    // -------------------------------------------------------------------- вывод

    public function test_the_component_renders_a_menu_by_its_code(): void
    {
        $menu = $this->menu();
        MenuItem::factory()->for($menu)->create(['title' => 'Каталог', 'url' => '/katalog']);

        $html = Blade::render('<x-nexor::menu code="main" />');

        $this->assertStringContainsString('Каталог', $html);
        $this->assertStringContainsString('/katalog', $html);
    }

    public function test_the_component_needs_a_source(): void
    {
        $this->expectException(ViewException::class);
        $this->expectExceptionMessage('нужен либо code меню, либо iblock');

        Blade::render('<x-nexor::menu />');
    }

    // ---------------------------------------------------------------------- API

    public function test_the_panel_lists_menus_with_their_item_counts(): void
    {
        $menu = $this->menu();
        MenuItem::factory()->for($menu)->count(2)->create();

        $this->actingAs($this->adminWith(['menus.view']))
            ->getJson('/admin/api/menus')
            ->assertOk()
            ->assertJsonPath('data.0.code', 'main')
            ->assertJsonPath('data.0.items_count', 2);
    }

    public function test_an_item_can_be_created_through_the_api(): void
    {
        $menu = $this->menu();

        $this->actingAs($this->adminWith(['menus.update']))
            ->postJson("/admin/api/menus/{$menu->id}/items", [
                'type' => 'link',
                'title' => 'Каталог',
                'url' => '/katalog',
                'visibility' => 'all',
            ])->assertCreated()->assertJsonPath('data.display_title', 'Каталог');
    }

    public function test_a_link_without_an_address_is_refused(): void
    {
        $menu = $this->menu();

        $this->actingAs($this->adminWith(['menus.update']))
            ->postJson("/admin/api/menus/{$menu->id}/items", ['type' => 'link', 'title' => 'Без адреса'])
            ->assertStatus(422)
            ->assertJsonValidationErrors('url');
    }

    public function test_a_dynamic_item_needs_an_infoblock_but_not_a_title(): void
    {
        $menu = $this->menu();
        $iblock = $this->catalogue();
        $admin = $this->adminWith(['menus.update']);

        $this->actingAs($admin)
            ->postJson("/admin/api/menus/{$menu->id}/items", ['type' => 'sections'])
            ->assertStatus(422)
            ->assertJsonValidationErrors('iblock_id');

        $this->actingAs($admin)
            ->postJson("/admin/api/menus/{$menu->id}/items", [
                'type' => 'sections', 'iblock_id' => $iblock->id, 'max_depth' => 2,
            ])->assertCreated();
    }

    public function test_the_panel_saves_the_shown_title_flag(): void
    {
        $menu = $this->menu();
        $iblock = $this->catalogue();

        $id = $this->actingAs($this->adminWith(['menus.update']))
            ->postJson("/admin/api/menus/{$menu->id}/items", [
                'type' => 'sections', 'iblock_id' => $iblock->id, 'max_depth' => 2, 'with_title' => true,
            ])
            ->assertCreated()
            ->assertJsonPath('data.with_title', true)
            ->json('data.id');

        $this->assertTrue(MenuItem::query()->findOrFail($id)->with_title);
    }

    public function test_dragging_saves_the_new_order_and_nesting(): void
    {
        $menu = $this->menu();
        $first = MenuItem::factory()->for($menu)->create(['title' => 'Первый', 'sort' => 100]);
        $second = MenuItem::factory()->for($menu)->create(['title' => 'Второй', 'sort' => 200]);

        $this->actingAs($this->adminWith(['menus.update']))
            ->putJson("/admin/api/menus/{$menu->id}/reorder", [
                'items' => [
                    ['id' => $second->id, 'parent_id' => null],
                    ['id' => $first->id, 'parent_id' => $second->id],
                ],
            ])->assertOk();

        $this->assertNull($second->refresh()->parent_id);
        $this->assertSame($second->id, $first->refresh()->parent_id);
        $this->assertTrue($first->sort > $second->sort);
    }

    public function test_reordering_ignores_items_of_another_menu(): void
    {
        $menu = $this->menu();
        $other = MenuItem::factory()->for($this->menu('footer'))->create(['title' => 'Чужой']);

        $this->actingAs($this->adminWith(['menus.update']))
            ->putJson("/admin/api/menus/{$menu->id}/reorder", [
                'items' => [['id' => $other->id, 'parent_id' => null]],
            ])->assertOk();

        $this->assertNull($other->refresh()->parent_id);
        $this->assertSame(500, $other->sort);
    }

    public function test_the_menu_screen_is_closed_without_the_permission(): void
    {
        $menu = $this->menu();

        $this->actingAs($this->adminWith())
            ->postJson("/admin/api/menus/{$menu->id}/items", ['type' => 'link', 'title' => 'X', 'url' => '/x'])
            ->assertForbidden();
    }

    public function test_deleting_an_item_takes_its_children_with_it(): void
    {
        $menu = $this->menu();
        $parent = MenuItem::factory()->for($menu)->create(['title' => 'Родитель']);
        $child = MenuItem::factory()->for($menu)->create(['parent_id' => $parent->id, 'title' => 'Ребёнок']);

        $this->actingAs($this->adminWith(['menus.update']))
            ->deleteJson("/admin/api/menus/{$menu->id}/items/{$parent->id}")
            ->assertOk();

        $this->assertDatabaseMissing('menu_items', ['id' => $child->id]);
    }
}
