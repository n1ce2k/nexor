<?php

namespace Tests\Feature;

use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Hash;
use Nexor\Cms\Enums\License;
use Nexor\Cms\Models\Role;
use Nexor\Cms\Support\License\LicenseKey;
use Nexor\Cms\Support\Licensing;
use Tests\TestCase;

/**
 * `nexor:install` на месте: учётная запись администратора и её пароль.
 */
class InstallCommandTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();

        // Лицензия — не про эти тесты: пусть сайт считается Lite, пока ключ не введён.
        config(['nexor.license' => 'lite', 'nexor.license_key' => null]);
        Licensing::flush();
    }

    public function test_a_license_key_is_asked_and_stored(): void
    {
        // Установка пишет ключ в env-файл приложения — подсовываем ей временный.
        $envPath = sys_get_temp_dir().'/nexor-license-test';
        $envFile = 'env-'.uniqid();
        file_put_contents($envPath.'/'.$envFile, 'APP_ENV=testing
');
        $this->app->useEnvironmentPath($envPath);
        $this->app->loadEnvironmentFrom($envFile);

        $pair = $this->keyPair();
        config(['nexor.license_public_key' => $pair['public'], 'nexor.license_key' => null]);
        Licensing::flush();

        $key = LicenseKey::issue(License::Pro, null, $pair['private']);

        $this->artisan('nexor:install', ['--no-migrate' => true, '--no-seed' => true, '--license-key' => $key->key])
            ->expectsOutputToContain('Лицензия: Pro')
            ->expectsQuestion('E-mail супер-администратора', '')
            ->assertSuccessful();

        $this->assertStringContainsString('NEXOR_LICENSE_KEY='.$key->key, file_get_contents($envPath.'/'.$envFile));
    }

    public function test_a_forged_license_key_leaves_the_site_on_lite(): void
    {
        config(['nexor.license_public_key' => $this->keyPair()['public'], 'nexor.license_key' => null]);
        Licensing::flush();

        $this->artisan('nexor:install', ['--no-migrate' => true, '--no-seed' => true, '--license-key' => 'nxr-podelka'])
            ->expectsOutputToContain('Ключ не прошёл проверку')
            ->expectsQuestion('E-mail супер-администратора', '')
            ->assertSuccessful();

        $this->assertSame(License::Lite, Licensing::edition());
    }

    /**
     * @return array{private: string, public: string}
     */
    protected function keyPair(): array
    {
        $config = ['private_key_type' => OPENSSL_KEYTYPE_EC, 'curve_name' => 'prime256v1'];

        if (is_file('C:/php/extras/ssl/openssl.cnf')) {
            $config['config'] = 'C:/php/extras/ssl/openssl.cnf';
        }

        $pair = openssl_pkey_new($config);
        openssl_pkey_export($pair, $private, null, $config);

        return ['private' => (string) $private, 'public' => openssl_pkey_get_details($pair)['key']];
    }

    public function test_the_administrator_is_created_with_the_password_from_the_prompt(): void
    {
        Role::factory()->create(['code' => Role::SUPER_ADMIN, 'name' => 'Супер-администратор']);

        $this->artisan('nexor:install', ['--no-migrate' => true, '--no-seed' => true])
            ->expectsQuestion('Лицензионный ключ (Enter — продолжить в редакции Lite)', '')
            ->expectsQuestion('E-mail супер-администратора', 'boss@example.test')
            ->expectsQuestion('Пароль супер-администратора (Enter — задать позже)', 'sekret-parol-123')
            ->expectsQuestion('Повторите пароль', 'sekret-parol-123')
            ->assertSuccessful();

        $user = User::query()->where('email', 'boss@example.test')->sole();

        $this->assertTrue(Hash::check('sekret-parol-123', $user->password));
        $this->assertSame('boss', $user->login);
        $this->assertTrue($user->is_super_admin);
        $this->assertTrue($user->roles->contains('code', Role::SUPER_ADMIN));
    }

    public function test_a_password_that_does_not_match_is_asked_again(): void
    {
        $this->artisan('nexor:install', ['--no-migrate' => true, '--no-seed' => true])
            ->expectsQuestion('Лицензионный ключ (Enter — продолжить в редакции Lite)', '')
            ->expectsQuestion('E-mail супер-администратора', 'boss@example.test')
            ->expectsQuestion('Пароль супер-администратора (Enter — задать позже)', 'sekret-parol-123')
            ->expectsQuestion('Повторите пароль', 'other-parol-123')
            ->expectsQuestion('Пароль супер-администратора (Enter — задать позже)', 'sekret-parol-123')
            ->expectsQuestion('Повторите пароль', 'sekret-parol-123')
            ->assertSuccessful();

        $this->assertTrue(Hash::check('sekret-parol-123', User::query()->where('email', 'boss@example.test')->sole()->password));
    }

    public function test_an_empty_password_leaves_the_account_without_one(): void
    {
        $this->artisan('nexor:install', ['--no-migrate' => true, '--no-seed' => true])
            ->expectsQuestion('Лицензионный ключ (Enter — продолжить в редакции Lite)', '')
            ->expectsQuestion('E-mail супер-администратора', 'boss@example.test')
            ->expectsQuestion('Пароль супер-администратора (Enter — задать позже)', '')
            ->expectsOutputToContain('php artisan nexor:password boss@example.test')
            ->assertSuccessful();

        $this->assertFalse(Hash::check('', User::query()->where('email', 'boss@example.test')->sole()->password));
    }
}
