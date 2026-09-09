<?php

namespace Tests\Feature;

use Database\Seeders\PageSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Nexor\Cms\Database\Seeders\IblockSeeder;
use Nexor\Cms\Database\Seeders\RoleSeeder;
use Nexor\Cms\Database\Seeders\SettingSeeder;
use Nexor\Cms\Models\IblockElement;
use Nexor\Cms\Models\Menu;
use Nexor\Cms\Models\MenuItem;
use Nexor\Cms\Models\Setting;
use Tests\TestCase;

class PublicSiteTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();

        $this->seed([RoleSeeder::class, SettingSeeder::class, IblockSeeder::class, PageSeeder::class]);
    }

    public function test_the_header_shows_the_main_menu(): void
    {
        $menu = Menu::factory()->create(['code' => 'main']);

        MenuItem::factory()->for($menu)->create(['title' => 'О компании', 'url' => '/about']);
        MenuItem::factory()->for($menu)->hidden()->create(['title' => 'Черновик', 'url' => '/draft']);

        $this->get(route('home'))
            ->assertOk()
            ->assertSee('О компании')
            ->assertDontSee('Черновик');
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
