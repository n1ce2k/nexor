<?php

namespace Tests\Feature;

use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Blade;
use Illuminate\Support\Facades\File;
use Nexor\Cms\Support\SiteAssets;
use Tests\TestCase;
use ZipArchive;

/**
 * Сборка шаблонов сайта, залитая вне `public`.
 *
 * На дешёвом хостинге node нет, поэтому сборку делают у себя и заливают папку
 * в storage — отдаёт её PHP. Проверяется и то, что без сборки страница всё
 * равно открывается: `@vite` в этом случае ронял сайт целиком.
 */
class SiteAssetsTest extends TestCase
{
    use RefreshDatabase;

    protected string $build;

    protected string $public;

    protected function setUp(): void
    {
        parent::setUp();

        $this->build = storage_path('framework/testing/build');
        $this->public = storage_path('framework/testing/public');

        File::deleteDirectory($this->build);
        File::ensureDirectoryExists($this->build);
        File::ensureDirectoryExists($this->public);

        // Свой public: в рабочем проекте рядом лежит настоящая сборка Vite,
        // и она перебивала бы проверяемую.
        $this->app->usePublicPath($this->public);

        config(['nexor.assets.path' => $this->build]);
        SiteAssets::flush();
    }

    protected function tearDown(): void
    {
        File::deleteDirectory($this->build);
        File::deleteDirectory($this->public);

        parent::tearDown();
    }

    /**
     * Сборка, как её оставляет Vite: манифест и файлы с хешем в имени.
     *
     * @param  array<string, mixed>|null  $manifest
     */
    protected function uploaded(?array $manifest = null): void
    {
        $manifest ??= [
            'resources/css/app.css' => ['file' => 'assets/app-1111.css'],
            'resources/js/app.js' => ['file' => 'assets/app-2222.js'],
        ];

        File::ensureDirectoryExists($this->build.'/assets');
        File::put($this->build.'/manifest.json', json_encode($manifest));
        File::put($this->build.'/assets/app-1111.css', '.offcanvas{display:block}');
        File::put($this->build.'/assets/app-2222.js', 'console.log(1);');

        SiteAssets::flush();
    }

    // -------------------------------------------------------------------- теги

    public function test_the_component_writes_tags_of_the_uploaded_build(): void
    {
        $this->uploaded();

        $html = Blade::render('<x-nexor::assets />');

        $this->assertStringContainsString(
            '<link rel="stylesheet" href="'.url('nexor/assets/assets/app-1111.css').'">',
            $html,
        );

        $this->assertStringContainsString(
            '<script type="module" src="'.url('nexor/assets/assets/app-2222.js').'"></script>',
            $html,
        );
    }

    public function test_styles_of_an_entry_come_before_its_script(): void
    {
        $this->uploaded([
            'resources/js/app.js' => ['file' => 'assets/app-2222.js', 'css' => ['assets/app-1111.css']],
        ]);

        $html = SiteAssets::tags(['resources/js/app.js'])->toHtml();

        // Иначе страница мигает: сначала голая разметка, потом стили.
        $this->assertLessThan(strpos($html, 'app-2222.js'), strpos($html, 'app-1111.css'));
    }

    public function test_a_page_opens_without_any_build(): void
    {
        $html = Blade::render('<x-nexor::assets />');

        $this->assertStringNotContainsString('<script', $html);
        $this->assertStringNotContainsString('<link', $html);
    }

    public function test_the_build_in_public_wins(): void
    {
        $this->uploaded();

        File::ensureDirectoryExists($this->public.'/build');
        File::put($this->public.'/build/manifest.json', json_encode([
            'resources/css/app.css' => ['file' => 'assets/local-3333.css', 'src' => 'resources/css/app.css', 'isEntry' => true],
            'resources/js/app.js' => ['file' => 'assets/local-4444.js', 'src' => 'resources/js/app.js', 'isEntry' => true],
        ]));

        $html = Blade::render('<x-nexor::assets />');

        // Своя машина остаётся на обычном Vite.
        $this->assertStringContainsString('/build/assets/local-4444.js', $html);
        $this->assertStringNotContainsString('nexor/assets', $html);
    }

    // ------------------------------------------------------------------ отдача

    public function test_a_file_of_the_build_is_served_with_a_long_cache(): void
    {
        $this->uploaded();

        $response = $this->get('/nexor/assets/assets/app-2222.js')->assertOk();

        $this->assertSame('application/javascript; charset=utf-8', $response->headers->get('Content-Type'));
        $this->assertStringContainsString('immutable', (string) $response->headers->get('Cache-Control'));

        $this->get('/nexor/assets/assets/app-1111.css')->assertOk();
    }

    public function test_nothing_but_the_build_is_served(): void
    {
        $this->uploaded();

        // Манифест и карты исходников наружу не отдаём.
        $this->get('/nexor/assets/manifest.json')->assertNotFound();
        $this->get('/nexor/assets/assets/missing.js')->assertNotFound();

        $this->assertNull(SiteAssets::file('../../../.env'));
        $this->assertNull(SiteAssets::file('manifest.json.bak'));
    }

    // ----------------------------------------------------------------- перенос

    public function test_an_uploaded_zip_is_moved_into_place(): void
    {
        if (! class_exists(ZipArchive::class)) {
            $this->markTestSkipped('Расширение zip недоступно.');
        }

        $archive = storage_path('framework/testing/build.zip');
        @unlink($archive);

        $zip = new ZipArchive;
        $zip->open($archive, ZipArchive::CREATE);

        // Vite кладёт манифест в .vite, а в архив обычно попадает папка build.
        $zip->addFromString('build/.vite/manifest.json', json_encode([
            'resources/js/app.js' => ['file' => 'assets/app-5555.js'],
        ]));
        $zip->addFromString('build/assets/app-5555.js', 'console.log(2);');
        $zip->close();

        $this->artisan('nexor:assets', ['source' => $archive])->assertSuccessful();

        $this->assertSame('assets/app-5555.js', SiteAssets::manifest()['resources/js/app.js']['file']);
        $this->assertNotNull(SiteAssets::file('assets/app-5555.js'));

        @unlink($archive);
    }

    public function test_a_folder_can_be_moved_too(): void
    {
        $source = storage_path('framework/testing/source');

        File::deleteDirectory($source);
        File::ensureDirectoryExists($source.'/assets');
        File::put($source.'/manifest.json', json_encode(['resources/js/app.js' => ['file' => 'assets/app-6666.js']]));
        File::put($source.'/assets/app-6666.js', 'console.log(3);');

        $this->artisan('nexor:assets', ['source' => $source])->assertSuccessful();

        $this->assertNotNull(SiteAssets::file('assets/app-6666.js'));

        File::deleteDirectory($source);
    }

    public function test_a_source_without_a_manifest_is_refused(): void
    {
        $source = storage_path('framework/testing/empty');

        File::deleteDirectory($source);
        File::ensureDirectoryExists($source);

        $this->artisan('nexor:assets', ['source' => $source])->assertFailed();

        File::deleteDirectory($source);
    }
}
