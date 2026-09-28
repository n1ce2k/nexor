<?php

namespace Tests\Feature;

use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\File;
use Illuminate\Support\Facades\Http;
use Nexor\Cms\Support\Updates\ComposerRunner;
use Nexor\Cms\Support\Updates\PackageCatalog;
use Nexor\Cms\Support\Updates\UpdatePlan;
use Tests\Concerns\CreatesAdminUsers;
use Tests\TestCase;

/**
 * Раздел «Обновления»: что показывается и что запускается.
 *
 * Сам composer в тестах не запускается — проверяется то, что решает, какую
 * команду собрать и кому это вообще позволено.
 */
class UpdatesTest extends TestCase
{
    use CreatesAdminUsers, RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();

        Http::preventStrayRequests();
        File::deleteDirectory(storage_path('app/nexor/updates'));
    }

    public function test_the_page_lists_the_core_and_the_modules(): void
    {
        Http::fake(['repo.packagist.org/*' => Http::response(['packages' => []])]);

        $response = $this->actingAs($this->adminWith(['updates.manage']))
            ->getJson('/admin/api/updates')
            ->assertOk();

        $codes = array_column($response->json('packages'), 'code');

        $this->assertSame(['cms', 'shop', 'pagebuilder'], $codes);
        $this->assertTrue($response->json('packages.0.installed'));
        $this->assertNotNull($response->json('packages.0.version'));
    }

    public function test_an_update_is_offered_only_for_a_newer_version(): void
    {
        $this->assertTrue(PackageCatalog::isNewer('v0.3.0', '0.2.12'));
        $this->assertFalse(PackageCatalog::isNewer('v0.2.12', 'v0.2.12'));
        $this->assertFalse(PackageCatalog::isNewer(null, '0.2.12'));
        $this->assertFalse(PackageCatalog::isNewer('v0.3.0', null));

        // Пакет из path-репозитория стоит веткой — обновлять его composer'ом нечем.
        $this->assertFalse(PackageCatalog::isNewer('v0.3.0', 'dev-main'));
        $this->assertTrue(PackageCatalog::isBranch('dev-main'));
        $this->assertFalse(PackageCatalog::isBranch('v0.2.12'));
    }

    public function test_the_section_is_closed_without_the_right(): void
    {
        $this->actingAs($this->adminWith())->getJson('/admin/api/updates')->assertForbidden();
        $this->actingAs($this->adminWith())->postJson('/admin/api/updates/update')->assertForbidden();
        $this->actingAs($this->adminWith(['users.view']))->postJson('/admin/api/updates/install', ['package' => 'shop'])->assertForbidden();
    }

    public function test_updating_is_refused_when_it_is_switched_off(): void
    {
        config(['nexor.updates.enabled' => false]);

        $this->actingAs($this->adminWith(['updates.manage']))
            ->postJson('/admin/api/updates/update')
            ->assertForbidden();
    }

    public function test_an_unknown_package_is_refused(): void
    {
        $admin = $this->adminWith(['updates.manage']);

        $this->actingAs($admin)
            ->postJson('/admin/api/updates/update', ['package' => 'laravel/framework'])
            ->assertUnprocessable()
            ->assertJsonPath('message', 'Неизвестный пакет.');

        $this->actingAs($admin)
            ->postJson('/admin/api/updates/install', ['package' => 'cms'])
            ->assertUnprocessable()
            ->assertJsonPath('message', 'Такого модуля нет в списке.');
    }

    public function test_an_installed_module_is_not_installed_twice(): void
    {
        $this->actingAs($this->adminWith(['updates.manage']))
            ->postJson('/admin/api/updates/install', ['package' => 'shop'])
            ->assertUnprocessable()
            ->assertJsonPath('message', 'Модуль «Магазин» уже установлен.');
    }

    public function test_the_plan_of_an_update_is_composer_then_artisan(): void
    {
        $plan = UpdatePlan::update(['n1ce2k/nexor-cms']);

        $this->assertSame('Обновление: n1ce2k/nexor-cms', $plan['title']);
        $this->assertSame('composer', $plan['steps'][0]['type']);
        $this->assertSame(['update', 'n1ce2k/nexor-cms', '--with-dependencies', '--no-interaction', '--prefer-dist', '--no-ansi'], $plan['steps'][0]['arguments']);
        $this->assertSame(['migrate', '--force', '--no-ansi'], $plan['steps'][1]['arguments']);
        $this->assertContains('nexor:permissions', array_column($plan['steps'], 'arguments')[2]);
    }

    public function test_the_plan_of_an_install_runs_the_module_installer(): void
    {
        $plan = UpdatePlan::install(PackageCatalog::find('pagebuilder'));

        $this->assertSame(['require', 'n1ce2k/nexor-pagebuilder', '--no-interaction', '--prefer-dist', '--no-ansi'], $plan['steps'][0]['arguments']);
        $this->assertSame(['nexor-pagebuilder:install', '--no-interaction'], $plan['steps'][1]['arguments']);
    }

    public function test_a_finished_task_keeps_its_log(): void
    {
        $id = 'test-task';

        ComposerRunner::state($id, [
            'id' => $id,
            'title' => 'Обновление',
            'state' => ComposerRunner::DONE,
            'steps' => [],
            'started_at' => now()->toIso8601String(),
            'finished_at' => now()->toIso8601String(),
            'exit_code' => 0,
        ]);
        ComposerRunner::append($id, 'всё хорошо');

        $status = $this->actingAs($this->adminWith(['updates.manage']))
            ->getJson('/admin/api/updates/status/'.$id)
            ->assertOk();

        $status->assertJsonPath('state', ComposerRunner::DONE);
        $status->assertJsonPath('output', 'всё хорошо');
    }

    public function test_control_codes_are_stripped_from_the_output(): void
    {
        $this->assertSame('Laravel 13', ComposerRunner::plain("Laravel \e[32m13\e[39m"));
    }

    public function test_a_background_process_that_never_started_is_reported(): void
    {
        $id = 'not-started';

        ComposerRunner::state($id, [
            'id' => $id,
            'title' => 'Установка модуля',
            'state' => ComposerRunner::RUNNING,
            'steps' => [],
            'started_at' => now()->subSeconds(ComposerRunner::STARTUP + 10)->toIso8601String(),
            'finished_at' => null,
            'exit_code' => null,
        ]);
        ComposerRunner::append($id, 'Установка модуля');

        $status = ComposerRunner::status($id);

        // Ни одного шага в логе — значит, фоновый процесс не поднялся.
        $this->assertSame(ComposerRunner::FAILED, $status['state']);
        $this->assertStringContainsString('Фоновый процесс не запустился', $status['output']);
    }

    public function test_a_task_that_only_started_is_still_running(): void
    {
        $id = 'just-started';

        ComposerRunner::state($id, [
            'id' => $id,
            'title' => 'Установка модуля',
            'state' => ComposerRunner::RUNNING,
            'steps' => [],
            'started_at' => now()->toIso8601String(),
            'finished_at' => null,
            'exit_code' => null,
        ]);
        ComposerRunner::append($id, 'Установка модуля');

        $this->assertSame(ComposerRunner::RUNNING, ComposerRunner::status($id)['state']);
    }

    public function test_a_task_that_hangs_is_closed_by_time(): void
    {
        $id = 'stuck-task';

        ComposerRunner::state($id, [
            'id' => $id,
            'title' => 'Обновление',
            'state' => ComposerRunner::RUNNING,
            'steps' => [],
            'started_at' => now()->subSeconds(ComposerRunner::TIMEOUT + 60)->toIso8601String(),
            'finished_at' => null,
            'exit_code' => null,
        ]);
        ComposerRunner::append($id, PHP_EOL.'$ composer update'.PHP_EOL);

        $this->assertSame(ComposerRunner::FAILED, ComposerRunner::status($id)['state']);
    }

    // ------------------------------------------------------- composer хостинга

    /** Composer в том виде, в каком его ставят на хостинг: phar без расширения. */
    protected function composerScript(): string
    {
        $path = storage_path('framework/testing/bin/composer');

        File::ensureDirectoryExists(dirname($path));
        File::put($path, "#!/usr/bin/env php\n<?php echo 'composer';\n");

        return $path;
    }

    public function test_a_composer_without_the_phar_extension_runs_with_our_php(): void
    {
        config(['nexor.updates.composer' => $this->composerScript(), 'nexor.updates.php' => '/opt/php/8.3/bin/php']);

        // Запущенный сам, он взял бы php из первой строки — консольный, 8.2.
        $this->assertSame(
            ['/opt/php/8.3/bin/php', $this->composerScript(), 'update', 'n1ce2k/nexor-cms'],
            ComposerRunner::command(['type' => 'composer', 'arguments' => ['update', 'n1ce2k/nexor-cms']]),
        );

        $this->assertSame('/opt/php/8.3/bin/php', ComposerRunner::command(['type' => 'artisan', 'arguments' => ['migrate']])[0]);
    }

    public function test_a_tilde_in_the_path_means_the_home_folder(): void
    {
        $home = getenv('HOME');
        putenv('HOME=/var/www/u0756649/data');

        try {
            config(['nexor.updates.composer' => '~/bin/composer']);

            // Процесс идёт без оболочки: не развернуть ~ — это код выхода 127.
            $this->assertSame('/var/www/u0756649/data/bin/composer', ComposerRunner::composer());
        } finally {
            putenv($home === false ? 'HOME' : 'HOME='.$home);
        }
    }

    public function test_a_composer_path_to_nowhere_is_reported_before_the_run(): void
    {
        config(['nexor.updates.composer' => '/nowhere/bin/composer']);

        $availability = ComposerRunner::availability();

        if (! $availability['ok'] && str_contains((string) $availability['reason'], 'отключена функция')) {
            $this->markTestSkipped('На этой машине запуск процессов закрыт.');
        }

        $this->assertFalse($availability['ok']);
        $this->assertStringContainsString('/nowhere/bin/composer', (string) $availability['reason']);
    }
}
