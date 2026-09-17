<?php

namespace Tests\Feature;

use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Blade;
use Illuminate\Support\Facades\File;
use Nexor\Cms\Models\Iblock;
use Tests\Concerns\PreservesPublishedViews;
use Tests\TestCase;

/**
 * `php artisan nexor:component` — тот же жест, что копирование шаблона
 * компонента в свой шаблон сайта в Битриксе.
 */
class PublishComponentCommandTest extends TestCase
{
    use PreservesPublishedViews, RefreshDatabase;

    protected string $published = '';

    protected function setUp(): void
    {
        parent::setUp();

        // Тест работает в той же папке, что и шаблоны сайта: копирует их в сторону и возвращает.
        $this->published = $this->preserveViews('vendor/nexor/components');
        File::deleteDirectory($this->published);
    }

    protected function tearDown(): void
    {
        $this->restorePreservedViews();

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

    public function test_all_copies_every_component(): void
    {
        $this->artisan('nexor:component', ['component' => 'all'])->assertSuccessful();

        $this->assertFileExists($this->published.'/catalog/section/tiles.blade.php');
        $this->assertFileExists($this->published.'/menu/sections/chips.blade.php');
        $this->assertFileExists($this->published.'/pagination/btnload.blade.php');
        $this->assertDirectoryDoesNotExist($this->published.'/admin');
    }

    public function test_the_listing_explains_how_to_take_everything(): void
    {
        $this->artisan('nexor:component')
            ->expectsOutputToContain('php artisan nexor:component all')
            ->assertSuccessful();
    }

    public function test_site_templates_survive_a_test_that_wipes_the_folder(): void
    {
        $folder = resource_path('views/vendor/nexor-preserve-check');
        File::ensureDirectoryExists($folder);
        File::put($folder.'/site.blade.php', 'шаблон сайта');

        try {
            $this->preserveViews('vendor/nexor-preserve-check');
            File::deleteDirectory($folder);

            $this->restorePreservedViews();

            $this->assertSame('шаблон сайта', File::get($folder.'/site.blade.php'));
        } finally {
            File::deleteDirectory($folder);
        }
    }

    public function test_a_component_is_copied_with_all_its_templates(): void
    {
        $this->artisan('nexor:component', ['component' => 'catalog.section'])->assertSuccessful();

        $this->assertFileExists($this->published.'/catalog/section/default.blade.php');
        $this->assertFileExists($this->published.'/catalog/section/tiles.blade.php');
    }

    public function test_a_template_can_be_created_under_its_own_name(): void
    {
        $this->artisan('nexor:component', ['component' => 'catalog.section', 'template' => 'blog'])
            ->expectsOutputToContain('template="blog"')
            ->assertSuccessful();

        $file = $this->published.'/catalog/section/blog.blade.php';

        $this->assertFileExists($file);
        $this->assertFileDoesNotExist($this->published.'/catalog/section/default.blade.php');
        $this->assertSame($this->packaged('catalog/section/default.blade.php'), File::get($file));
    }

    public function test_a_form_template_takes_its_livewire_variant_along(): void
    {
        $this->artisan('nexor:component', ['component' => 'form', 'template' => 'callback'])->assertSuccessful();

        $this->assertFileExists($this->published.'/form/callback.blade.php');
        $this->assertSame(
            $this->packaged('form/default-livewire.blade.php'),
            File::get($this->published.'/form/callback-livewire.blade.php'),
        );
    }

    public function test_the_livewire_variant_is_not_listed_as_a_template(): void
    {
        $this->artisan('nexor:component')
            ->doesntExpectOutputToContain('default-livewire')
            ->assertSuccessful();
    }

    public function test_a_named_template_can_be_based_on_another_one(): void
    {
        $this->artisan('nexor:component', [
            'component' => 'catalog.section', 'template' => 'shop', '--from' => 'tiles',
        ])->assertSuccessful();

        $this->assertSame(
            $this->packaged('catalog/section/tiles.blade.php'),
            File::get($this->published.'/catalog/section/shop.blade.php'),
        );
    }

    public function test_a_named_template_is_rendered_by_the_component(): void
    {
        Iblock::factory()->create(['code' => 'katalog', 'is_active' => true]);

        $this->artisan('nexor:component', ['component' => 'catalog.section', 'template' => 'blog'])
            ->assertSuccessful();

        File::put($this->published.'/catalog/section/blog.blade.php', 'вёрстка блога');

        $this->assertSame(
            'вёрстка блога',
            trim(Blade::render('<x-nexor::catalog.section iblock="katalog" template="blog" />')),
        );
    }

    public function test_a_named_template_is_not_silently_overwritten(): void
    {
        $this->artisan('nexor:component', ['component' => 'catalog.section', 'template' => 'blog'])
            ->assertSuccessful();

        $file = $this->published.'/catalog/section/blog.blade.php';
        File::put($file, 'моя вёрстка');

        $this->artisan('nexor:component', ['component' => 'catalog.section', 'template' => 'blog'])
            ->assertFailed();

        $this->assertSame('моя вёрстка', File::get($file));

        $this->artisan('nexor:component', ['component' => 'catalog.section', 'template' => 'blog', '--force' => true])
            ->assertSuccessful();

        $this->assertNotSame('моя вёрстка', File::get($file));
    }

    public function test_a_template_name_that_is_not_a_filename_is_refused(): void
    {
        $this->artisan('nexor:component', ['component' => 'catalog.section', 'template' => 'мой шаблон'])
            ->assertFailed();

        $this->assertDirectoryDoesNotExist($this->published.'/catalog/section');
    }

    public function test_an_unknown_source_template_lists_what_there_is(): void
    {
        $this->artisan('nexor:component', [
            'component' => 'catalog.section', 'template' => 'blog', '--from' => 'net-takogo',
        ])->expectsOutputToContain('tiles')->assertFailed();
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

    /** Содержимое пакетного шаблона, с которым сверяется копия. */
    protected function packaged(string $relative): string
    {
        return File::get(dirname(__DIR__, 2).'/packages/nexor-cms/resources/views/components/'.$relative);
    }
}
