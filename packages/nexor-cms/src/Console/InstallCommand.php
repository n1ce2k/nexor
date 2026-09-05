<?php

namespace Nexor\Cms\Console;

use Illuminate\Console\Command;
use Illuminate\Support\Facades\Artisan;
use Nexor\Cms\Database\Seeders\IblockSeeder;
use Nexor\Cms\Database\Seeders\RoleSeeder;
use Nexor\Cms\Database\Seeders\SettingSeeder;
use Nexor\Cms\Models\Role;
use Nexor\Cms\Support\Nexor;
use Nexor\Cms\Support\Permissions;

class InstallCommand extends Command
{
    protected $signature = 'nexor:install
                            {--force : Run without asking anything}
                            {--no-migrate : Skip running migrations}
                            {--no-seed : Skip seeding roles, settings and infoblock types}
                            {--admin-email= : E-mail of the super administrator to create}';

    protected $description = 'Установить NEXOR CMS: миграции, роли, права и учётная запись администратора';

    public function handle(): int
    {
        $this->components->info('Установка NEXOR CMS');

        if (! $this->option('no-migrate')) {
            $this->components->task('Миграции', fn () => Artisan::call('migrate', ['--force' => true]) === 0);
        }

        if (! $this->option('no-seed')) {
            $this->components->task('Роли и права', fn () => $this->seed(RoleSeeder::class));
            $this->components->task('Настройки', fn () => $this->seed(SettingSeeder::class));
            $this->components->task('Типы инфоблоков', fn () => $this->seed(IblockSeeder::class));
        }

        $this->components->task('Синхронизация прав', function (): bool {
            Permissions::syncAll();

            return true;
        });

        $this->createAdministrator();

        $this->newLine();
        $this->components->info('Готово. Панель управления: /'.Nexor::routePrefix());

        return self::SUCCESS;
    }

    protected function seed(string $class): bool
    {
        Artisan::call('db:seed', ['--class' => $class, '--force' => true]);

        return true;
    }

    /**
     * Creates the first super administrator when the site has none.
     *
     * The password is never taken from arguments or generated silently — the
     * operator sets it themselves through `nexor:install` prompts or the app's
     * own tooling, so it never ends up in shell history or logs.
     */
    protected function createAdministrator(): void
    {
        $query = Nexor::newUser()->newQuery();

        if ($query->clone()->where('is_super_admin', true)->exists()) {
            $this->components->info('Супер-администратор уже есть — пропускаю.');

            return;
        }

        $email = $this->option('admin-email');

        if (! $email && ! $this->option('force')) {
            $email = $this->components->ask('E-mail супер-администратора');
        }

        if (! $email) {
            $this->components->warn('Учётная запись не создана. Создайте её вручную и выставьте is_super_admin = true.');

            return;
        }

        $user = $query->clone()->firstOrNew(['email' => $email]);
        $user->name = $user->name ?: 'Администратор';
        $user->is_active = true;
        $user->is_super_admin = true;

        if (! $user->exists) {
            $user->password = str()->random(32);
        }

        $user->save();

        if ($role = Role::query()->where('code', Role::SUPER_ADMIN)->first()) {
            $user->roles()->syncWithoutDetaching([$role->id]);
        }

        $this->components->info("Учётная запись {$email} готова.");
        $this->components->warn('Пароль сгенерирован случайным. Задайте свой командой:');
        $this->line('  php artisan nexor:password '.$email);
    }
}
