<?php

namespace Tests\Feature;

use Illuminate\Support\Facades\File;
use Tests\TestCase;

/**
 * `php artisan nexor:component` — тот же жест, что копирование шаблона
 * компонента в свой шаблон сайта в Битриксе.
 */
class PublishComponentCommandTest extends TestCase
{
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

    public function test_without_an_argument_it_lists_the_components(): void
    {
        $this->artisan('nexor:component')
            ->expectsOutputToContain('catalog.section')
            ->expectsOutputToContain('pagination')
            ->assertSuccessful();
    }

    public function test_the_listing_hides_the_admin_panels_own_components(): void
    {
        $this->artisan('nexor:component')
            ->doesntExpectOutputToContain('admin.')
            ->doesntExpectOutputToContain('pagination.partials')
            ->assertSuccessful();
    }

    public function test_a_component_is_copied_with_all_its_templates(): void
    {
        $this->artisan('nexor:component', ['component' => 'catalog.section'])->assertSuccessful();

        $this->assertFileExists($this->published.'/catalog/section/default.blade.php');
        $this->assertFileExists($this->published.'/catalog/section/tiles.blade.php');
    }

    public function test_a_single_template_can_be_taken(): void
    {
        $this->artisan('nexor:component', ['component' => 'catalog.section', '--template' => 'tiles'])
            ->assertSuccessful();

        $this->assertFileExists($this->published.'/catalog/section/tiles.blade.php');
        $this->assertFileDoesNotExist($this->published.'/catalog/section/default.blade.php');
    }

    public function test_nested_files_of_a_component_come_along(): void
    {
        $this->artisan('nexor:component', ['component' => 'pagination'])->assertSuccessful();

        // Кнопка «Показать ещё» общая для шаблонов — без неё они не работают.
        $this->assertFileExists($this->published.'/pagination/partials/load-more.blade.php');
    }

    public function test_an_edited_template_is_not_overwritten(): void
    {
        $this->artisan('nexor:component', ['component' => 'breadcrumbs'])->assertSuccessful();

        $file = $this->published.'/breadcrumbs/default.blade.php';
        File::put($file, 'моя вёрстка');

        $this->artisan('nexor:component', ['component' => 'breadcrumbs'])->assertSuccessful();
        $this->assertSame('моя вёрстка', File::get($file));

        $this->artisan('nexor:component', ['component' => 'breadcrumbs', '--force' => true])->assertSuccessful();
        $this->assertNotSame('моя вёрстка', File::get($file));
    }

    public function test_the_bitrix_style_slash_form_is_accepted(): void
    {
        $this->artisan('nexor:component', ['component' => 'catalog/filter'])->assertSuccessful();

        $this->assertFileExists($this->published.'/catalog/filter/default.blade.php');
    }

    public function test_an_unknown_component_fails_and_shows_what_there_is(): void
    {
        $this->artisan('nexor:component', ['component' => 'net.takogo'])
            ->expectsOutputToContain('catalog.section')
            ->assertFailed();
    }

    public function test_an_unknown_template_fails(): void
    {
        $this->artisan('nexor:component', ['component' => 'pagination', '--template' => 'net-takogo'])
            ->assertFailed();

        $this->assertDirectoryDoesNotExist($this->published.'/pagination');
    }
}
