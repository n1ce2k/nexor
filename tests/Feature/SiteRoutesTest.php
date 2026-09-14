<?php

namespace Tests\Feature;

use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\File;
use Illuminate\Support\Facades\Route;
use Nexor\Cms\Console\InstallCommand;
use Tests\TestCase;

/**
 * Маршруты сайта живут в пакете, но приложение всегда может их перекрыть.
 */
class SiteRoutesTest extends TestCase
{
    use RefreshDatabase;

    public function test_the_site_routes_come_from_the_package(): void
    {
        $this->assertSame(
            'Nexor\Cms\Http\Controllers\Site\PageController@show',
            Route::getRoutes()->getByName('page')->getActionName(),
        );
    }

    public function test_an_application_route_wins_over_the_package_home(): void
    {
        // Зарегистрирован позже пакетного, но пакетный — fallback.
        Route::get('/', fn () => 'главная приложения');

        $this->get('/')->assertOk()->assertSee('главная приложения');
    }

    public function test_an_application_route_wins_over_an_infoblock_page(): void
    {
        Route::get('/about', fn () => 'о компании из приложения');

        $this->get('/about')->assertOk()->assertSee('о компании из приложения');
    }

    public function test_the_panel_prefix_is_never_taken_for_an_infoblock(): void
    {
        $this->assertFalse((bool) preg_match(
            '#'.Route::getRoutes()->getByName('page')->wheres['code'].'#',
            'admin',
        ));
    }

    public function test_install_adds_only_the_missing_site_views(): void
    {
        $layout = resource_path('views/site/layout.blade.php');
        $original = File::get($layout);

        // Все шаблоны уже есть — установка ничего не трогает.
        $this->assertSame(0, InstallCommand::publishSiteViews());
        $this->assertSame($original, File::get($layout));
    }
}
