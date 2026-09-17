<?php

namespace Tests\Feature;

use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Hash;
use Nexor\Cms\Models\Role;
use Tests\TestCase;

/**
 * `nexor:install` на месте: учётная запись администратора и её пароль.
 */
class InstallCommandTest extends TestCase
{
    use RefreshDatabase;

    public function test_the_administrator_is_created_with_the_password_from_the_prompt(): void
    {
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
        $this->artisan('nexor:install', ['--no-migrate' => true, '--no-seed' => true])
            ->expectsQuestion('E-mail супер-администратора', 'boss@example.test')
            ->expectsQuestion('Пароль супер-администратора (Enter — задать позже)', '')
            ->expectsOutputToContain('php artisan nexor:password boss@example.test')
            ->assertSuccessful();

        $this->assertFalse(Hash::check('', User::query()->where('email', 'boss@example.test')->sole()->password));
    }
}
