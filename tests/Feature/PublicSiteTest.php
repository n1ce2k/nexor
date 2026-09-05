<?php

namespace Tests\Feature;

use Database\Seeders\PageSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Nexor\Cms\Database\Seeders\IblockSeeder;
use Nexor\Cms\Database\Seeders\RoleSeeder;
use Nexor\Cms\Database\Seeders\SettingSeeder;
use Nexor\Cms\Models\IblockElement;
use Nexor\Cms\Models\IblockElementValue;
use Nexor\Cms\Models\Setting;
use Nexor\Cms\Support\Site;
use Tests\TestCase;

class PublicSiteTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();

        $this->seed([RoleSeeder::class, SettingSeeder::class, IblockSeeder::class, PageSeeder::class]);
    }

    public function test_the_home_page_lists_the_seeded_pages(): void
    {
        $this->get(route('home'))
            ->assertOk()
            ->assertSee('О компании')
            ->assertSee('Контакты');
    }

    public function test_a_page_is_served_by_its_symbolic_code(): void
    {
        $this->get(route('page', 'about'))
            ->assertOk()
            ->assertSee('О компании')
            ->assertSee('Кто мы и чем занимаемся');
    }

    public function test_an_unknown_code_returns_the_404_page(): void
    {
        $this->get('/no-such-page')
            ->assertNotFound()
            ->assertSee('Страница не найдена');
    }

    public function test_a_hidden_page_is_not_served(): void
    {
        IblockElement::query()->where('code', 'about')->update(['is_active' => false]);

        $this->get(route('page', 'about'))->assertNotFound();
    }

    public function test_the_menu_only_contains_pages_flagged_for_it(): void
    {
        $this->assertContains('services', Site::menu()->pluck('code')->all());

        $page = IblockElement::query()->where('code', 'services')->firstOrFail();
        $property = $page->iblock->properties()->where('code', 'show_in_menu')->firstOrFail();

        IblockElementValue::query()
            ->where('element_id', $page->id)
            ->where('property_id', $property->id)
            ->update(['value_bool' => false]);

        $this->assertNotContains('services', Site::menu()->pluck('code')->all());
    }

    public function test_the_menu_is_ordered_by_the_menu_sort_property(): void
    {
        $this->assertSame(['about', 'services', 'delivery', 'contacts'], Site::menu()->pluck('code')->all());
    }

    public function test_robots_txt_comes_from_the_settings(): void
    {
        Setting::put('seo.robots', "User-agent: *\nDisallow: /admin");

        $this->get('/robots.txt')
            ->assertOk()
            ->assertHeader('Content-Type', 'text/plain; charset=UTF-8')
            ->assertSee('Disallow: /admin')
            ->assertSee('Sitemap:');
    }

    public function test_the_site_title_follows_the_site_name_setting(): void
    {
        Setting::put('site.name', 'Новое имя');

        $this->get(route('home'))->assertOk()->assertSee('Новое имя');
    }

    public function test_the_admin_prefix_is_not_swallowed_by_the_page_route(): void
    {
        $this->get('/admin')->assertRedirect(route('admin.login'));
    }
}
