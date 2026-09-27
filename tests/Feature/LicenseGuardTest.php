<?php

namespace Tests\Feature;

use Illuminate\Foundation\Testing\RefreshDatabase;
use Nexor\Cms\Models\LicenseBinding;
use Nexor\Cms\Support\Install;
use Nexor\Cms\Support\Licensing;
use Tests\Concerns\IssuesLicenseKeys;
use Tests\TestCase;

/**
 * Привязка установки к домену.
 *
 * Копию, поднятую не на своём домене, закрывает middleware. Проверяется это на
 * живых запросах с чужим хостом: подделать домен в запросе — ровно то, что
 * сделает тот, кто унёс папку с базой.
 */
class LicenseGuardTest extends TestCase
{
    use IssuesLicenseKeys, RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();

        $this->useIssuer();

        // В окружении testing привязка не проверяется вовсе — иначе половина
        // набора тестов упиралась бы в лицензию.
        $this->app->detectEnvironment(fn () => 'production');
    }

    // ------------------------------------------------------------- блокировка

    public function test_a_key_issued_for_another_domain_closes_the_panel(): void
    {
        $this->useKey(host: 'site.ru');

        $this->get('http://vasya.ru/admin')
            ->assertForbidden()
            ->assertSee('Установка не активирована')
            ->assertSee('vasya.ru');

        // Сайт при этом работает: ронять чужой бизнес из-за лицензии нельзя.
        $this->get('http://vasya.ru/')->assertOk();
    }

    public function test_the_whole_site_can_be_closed(): void
    {
        config(['nexor.license_guard' => 'site']);
        $this->useKey(host: 'site.ru');

        $this->get('http://vasya.ru/')
            ->assertStatus(503)
            ->assertSee('Эта копия сайта не активирована')
            // На публичной странице подробностей нет.
            ->assertDontSee('site.ru');
    }

    public function test_a_moved_database_is_noticed_even_without_a_key(): void
    {
        $this->useKey(host: 'site.ru');
        $this->get('http://site.ru/')->assertOk();

        // Ключ убрали из .env — база всё равно помнит свой сайт.
        config(['nexor.license_key' => null]);
        Licensing::flush();

        $this->get('http://vasya.ru/admin')->assertForbidden()->assertSee('site.ru');
        $this->assertSame(Licensing::MOVED, Licensing::status('vasya.ru'));
    }

    // ---------------------------------------------------------------- проход

    public function test_the_right_domain_passes_and_is_remembered(): void
    {
        $key = $this->useKey(host: 'site.ru');

        $this->get('http://site.ru/')->assertOk();
        $this->get('http://www.site.ru/')->assertOk();

        $binding = LicenseBinding::query()->sole();

        $this->assertSame('site.ru', $binding->host);
        $this->assertSame($key->serial, $binding->key_serial);
    }

    public function test_a_renewed_key_does_not_close_the_site(): void
    {
        $this->useKey(host: 'site.ru');
        $this->get('http://site.ru/')->assertOk();

        // Продление — это новый ключ с новым номером на тот же домен.
        $renewed = $this->useKey(host: 'site.ru');

        $this->get('http://site.ru/admin')->assertRedirect(route('admin.login'));
        $this->assertSame($renewed->serial, Install::serial());
    }

    public function test_a_key_without_a_domain_fits_any_site(): void
    {
        $this->useKey();

        $this->get('http://vasya.ru/')->assertOk();
        $this->get('http://vasya.ru/admin')->assertRedirect(route('admin.login'));
    }

    public function test_local_addresses_are_never_closed(): void
    {
        $this->useKey(host: 'site.ru');

        $this->get('http://localhost/')->assertOk();
        $this->get('http://nexor.test/')->assertOk();

        // Локальный запуск и привязку не оставляет.
        $this->assertSame(0, LicenseBinding::query()->count());
    }

    public function test_the_guard_can_be_switched_off(): void
    {
        config(['nexor.license_guard' => 'off']);
        $this->useKey(host: 'site.ru');

        $this->get('http://vasya.ru/admin')->assertRedirect(route('admin.login'));
    }

    // -------------------------------------------------------------- привязка

    public function test_binding_moves_only_with_a_matching_key(): void
    {
        $this->useKey(host: 'site.ru');

        $this->artisan('nexor:license:bind', ['--host' => 'vasya.ru'])->assertFailed();
        $this->assertNull(Install::host());

        $this->artisan('nexor:license:bind', ['--host' => 'site.ru'])->assertSuccessful();
        $this->assertSame('site.ru', Install::host());
        $this->assertNotNull(Install::id());
    }

    public function test_the_panel_is_told_what_is_wrong(): void
    {
        $key = $this->useKey(host: 'site.ru');
        $state = Licensing::state('vasya.ru');

        $this->assertSame(Licensing::FOREIGN, $state['status']);
        $this->assertSame('site.ru', $state['host']);
        $this->assertSame('vasya.ru', $state['current_host']);
        $this->assertStringContainsString('site.ru', (string) $state['message']);
        $this->assertStringContainsString('vasya.ru', (string) $state['message']);
        $this->assertSame($key->number(), $state['number']);
    }
}
