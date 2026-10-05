<?php

namespace Tests\Feature;

use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Route;
use Illuminate\Testing\TestResponse;
use Nexor\Cms\Support\RootHtaccess;
use Tests\TestCase;

/**
 * Папка `public` не попадает в адреса сайта.
 *
 * На хостинге, где корень сайта — папка проекта, запросы уводит в `public`
 * корневой .htaccess. Запрос, пришедший на `/public/...`, здесь изображается
 * так же, как его видит PHP: скрипт лежит в `/public/index.php`.
 */
class PublicFolderTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();

        Route::middleware('web')->any('/probe', fn () => url('/next'));
    }

    public function test_address_with_public_returns_to_the_clean_one(): void
    {
        RootHtaccess::write();

        $response = $this->throughPublic('GET', '/probe?page=2');

        $response->assertStatus(301);
        $this->assertSame('http://localhost:8000/probe?page=2', $response->headers->get('Location'));
    }

    public function test_form_sent_to_public_still_works_and_links_are_clean(): void
    {
        RootHtaccess::write();

        $response = $this->throughPublic('POST', '/probe');

        $response->assertOk();
        $this->assertSame('http://localhost:8000/next', $response->getContent());
    }

    public function test_naive_rewrite_is_recognised_too(): void
    {
        file_put_contents(RootHtaccess::path(), "RewriteEngine On\nRewriteRule ^$ public/ [L]\nRewriteRule ^(.*)$ public/$1 [L]\n");

        $this->throughPublic('GET', '/probe')->assertStatus(301);
    }

    public function test_site_really_opened_from_public_is_left_alone(): void
    {
        $response = $this->throughPublic('GET', '/probe');

        $response->assertOk();
        $this->assertSame('http://localhost:8000/public/next', $response->getContent());
    }

    public function test_ordinary_request_is_untouched(): void
    {
        RootHtaccess::write();

        $response = $this->get('/probe');

        $response->assertOk();
        $this->assertSame('http://localhost:8000/next', $response->getContent());
    }

    public function test_status_tells_own_file_from_others(): void
    {
        $this->assertSame(RootHtaccess::MISSING, RootHtaccess::status());

        RootHtaccess::write();
        $this->assertSame(RootHtaccess::CURRENT, RootHtaccess::status());

        file_put_contents(RootHtaccess::path(), RootHtaccess::MARKER."\nRewriteEngine On\n");
        $this->assertSame(RootHtaccess::OUTDATED, RootHtaccess::status());

        file_put_contents(RootHtaccess::path(), "RewriteEngine On\nRewriteRule ^(.*)$ public/$1 [L]\n");
        $this->assertSame(RootHtaccess::NAIVE, RootHtaccess::status());

        file_put_contents(RootHtaccess::path(), "Options -Indexes\n");
        $this->assertSame(RootHtaccess::FOREIGN, RootHtaccess::status());
    }

    public function test_command_replaces_naive_file_and_keeps_a_copy(): void
    {
        $naive = "RewriteEngine On\nRewriteRule ^(.*)$ public/$1 [L]\n";
        file_put_contents(RootHtaccess::path(), $naive);

        $this->artisan('nexor:htaccess')->assertSuccessful();

        $this->assertSame(RootHtaccess::CURRENT, RootHtaccess::status());
        $this->assertSame($naive, file_get_contents(RootHtaccess::path().'.bak'));
    }

    public function test_command_leaves_foreign_file_without_force(): void
    {
        file_put_contents(RootHtaccess::path(), "Options -Indexes\n");

        $this->artisan('nexor:htaccess')->assertFailed();
        $this->assertSame("Options -Indexes\n", file_get_contents(RootHtaccess::path()));

        $this->artisan('nexor:htaccess', ['--force' => true])->assertSuccessful();
        $this->assertSame(RootHtaccess::CURRENT, RootHtaccess::status());
        $this->assertSame("Options -Indexes\n", file_get_contents(RootHtaccess::path().'.bak'));
    }

    public function test_written_file_closes_env_and_strips_public(): void
    {
        $content = RootHtaccess::content();

        $this->assertStringContainsString('RewriteRule ^public/?(.*)$ /$1 [L,R=301]', $content);
        $this->assertStringContainsString('RewriteRule ^(.*)$ public/$1 [L]', $content);
        $this->assertStringContainsString('\.env', $content);
    }

    /**
     * Запрос, каким его видит PHP, когда браузер пришёл на `/public/...`.
     */
    protected function throughPublic(string $method, string $uri): TestResponse
    {
        return $this->call($method, '/public'.$uri, server: [
            'SCRIPT_NAME' => '/public/index.php',
            'SCRIPT_FILENAME' => public_path('index.php'),
        ]);
    }
}
