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

        $this->assertSame(ComposerRunner::FAILED, ComposerRunner::status($id)['state']);
    }
}
