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
 * `nexor:install`: лицензионный ключ, учётная запись администратора и пароль.
 *
 * Без действующего ключа установка не идёт — это проверяется отдельно.
 */
class InstallCommandTest extends TestCase
{
    use RefreshDatabase;

    protected string $privateKey;

    protected function setUp(): void
    {
        parent::setUp();

        $pair = $this->keyPair();
        $this->privateKey = $pair['private'];

        config(['nexor.license_public_key' => $pair['public'], 'nexor.license' => 'lite', 'nexor.license_key' => null]);
        Licensing::flush();
    }

    /**
     * Ключ, который установка примет.
     */
    protected function key(?int $expiresAt = null): string
    {
        return LicenseKey::issue(License::Pro, $expiresAt, $this->privateKey)->key;
    }

    /**
     * Сайт с уже введённым ключом — про лицензию установка тогда не спрашивает.
     */
    protected function licensed(): void
    {
        config(['nexor.license_key' => $this->key()]);
        Licensing::flush();
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

    public function test_without_a_key_nothing_is_installed(): void
    {
        $this->artisan('nexor:install', ['--no-migrate' => true, '--no-seed' => true])
            ->expectsQuestion('Лицензионный ключ', '')
            ->expectsOutputToContain('Без лицензионного ключа NEXOR CMS не устанавливается.')
            ->assertFailed();

        // Ни администратора, ни настроек: установка остановилась на первом шаге.
        $this->assertSame(0, User::query()->count());
    }

    public function test_a_forged_or_expired_key_is_refused(): void
    {
        $this->artisan('nexor:install', ['--no-migrate' => true, '--no-seed' => true, '--license-key' => 'nxr-podelka'])
            ->expectsOutputToContain('Ключ не прошёл проверку')
            ->assertFailed();

        $this->artisan('nexor:install', ['--no-migrate' => true, '--no-seed' => true, '--license-key' => $this->key(time() - 60)])
            ->expectsOutputToContain('Срок этого ключа истёк')
            ->assertFailed();

        $this->assertSame(0, User::query()->count());
    }

    public function test_a_mistyped_key_can_be_entered_again(): void
    {
        $envPath = sys_get_temp_dir().'/nexor-license-test';
        $envFile = 'env-'.uniqid();

        @mkdir($envPath, 0777, true);
        file_put_contents($envPath.'/'.$envFile, "APP_ENV=testing\n");
        $this->app->useEnvironmentPath($envPath);
        $this->app->loadEnvironmentFrom($envFile);

        $key = $this->key();

        $this->artisan('nexor:install', ['--no-migrate' => true, '--no-seed' => true])
            ->expectsQuestion('Лицензионный ключ', 'nxr-opechatka')
            ->expectsOutputToContain('Ключ не прошёл проверку')
            ->expectsQuestion('Лицензионный ключ', $key)
            ->expectsOutputToContain('Лицензия: Pro')
            ->expectsQuestion('E-mail супер-администратора', '')
            ->assertSuccessful();

        $this->assertStringContainsString('NEXOR_LICENSE_KEY='.$key, (string) file_get_contents($envPath.'/'.$envFile));
    }

    public function test_an_unattended_install_needs_the_key_beforehand(): void
    {
        $this->artisan('nexor:install', ['--force' => true, '--no-migrate' => true, '--no-seed' => true])
            ->expectsOutputToContain('Без лицензионного ключа NEXOR CMS не устанавливается.')
            ->assertFailed();

        $this->licensed();

        $this->artisan('nexor:install', ['--force' => true, '--no-migrate' => true, '--no-seed' => true])
            ->assertSuccessful();
    }

    public function test_the_administrator_is_created_with_the_password_from_the_prompt(): void
    {
        $this->licensed();
        Role::factory()->create(['code' => Role::SUPER_ADMIN, 'name' => 'Супер-администратор']);

        $this->artisan('nexor:install', ['--no-migrate' => true, '--no-seed' => true])
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
        $this->licensed();

        $this->artisan('nexor:install', ['--no-migrate' => true, '--no-seed' => true])
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
        $this->licensed();

        $this->artisan('nexor:install', ['--no-migrate' => true, '--no-seed' => true])
            ->expectsQuestion('E-mail супер-администратора', 'boss@example.test')
            ->expectsQuestion('Пароль супер-администратора (Enter — задать позже)', '')
            ->expectsOutputToContain('php artisan nexor:password boss@example.test')
            ->assertSuccessful();

        $this->assertFalse(Hash::check('', User::query()->where('email', 'boss@example.test')->sole()->password));
    }
}
