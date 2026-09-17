<?php

namespace Nexor\Cms\Console;

use Illuminate\Console\Command;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Support\Facades\Artisan;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\File;
use Illuminate\Support\Facades\Validator;
use Illuminate\Validation\Rules\Password;
use Nexor\Cms\Database\Seeders\IblockSeeder;
use Nexor\Cms\Database\Seeders\MenuSeeder;
use Nexor\Cms\Database\Seeders\RoleSeeder;
use Nexor\Cms\Database\Seeders\SettingSeeder;
use Nexor\Cms\Models\Role;
use Nexor\Cms\Support\Nexor;
use Nexor\Cms\Support\Permissions;
use Nexor\Cms\Support\TailwindSources;
use Nexor\Cms\Support\UserModelSetup;

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
            $this->components->task('Меню', fn () => $this->seed(MenuSeeder::class));
        }

        $this->components->task('Синхронизация прав', function (): bool {
            Permissions::syncAll();

            return true;
        });

        $this->components->task('Шаблоны сайта', fn () => $this->publishSiteViews() >= 0);

        $this->components->task('Tailwind видит шаблоны компонентов', function (): bool {
            TailwindSources::add(dirname(__DIR__, 2).'/resources/views/components');
            TailwindSources::add(dirname(__DIR__, 2).'/resources/views/site');

            return true;
        });

        $this->prepareUserModel();

        $this->createAdministrator();

        $this->newLine();
        $this->components->info('Готово. Панель управления: /'.Nexor::routePrefix());
        $this->line('  Админка приходит в пакете уже собранной — Node для неё не нужен.');
        $this->line('  Для стилей сайта соберите его CSS как обычно: <fg=cyan>npm install && npm run build</>');

        return self::SUCCESS;
    }

    /**
     * Стартовые шаблоны сайта: макет, главная, страница, поиск и 404.
     *
     * Кладутся только недостающие — свой макет установка не перезапишет никогда.
     *
     * @return int Сколько файлов добавлено
     */
    public static function publishSiteViews(): int
    {
        $stubs = dirname(__DIR__, 2).'/stubs/site/views';
        $added = 0;

        foreach (File::allFiles($stubs) as $file) {
            $target = resource_path('views/'.str_replace('\\', '/', $file->getRelativePathname()));

            if (File::exists($target)) {
                continue;
            }

            File::ensureDirectoryExists(dirname($target));
            File::copy($file->getPathname(), $target);
            $added++;
        }

        return $added;
    }

    protected function seed(string $class): bool
    {
        Artisan::call('db:seed', ['--class' => $class, '--force' => true]);

        return true;
    }

    /**
     * Дописывает модель пользователя приложения: контракт, трейты и колонки CMS.
     *
     * Без этого панель не знает ни про роли, ни про свои поля, а установка
     * падала на первой же учётной записи.
     */
    protected function prepareUserModel(): void
    {
        $missing = UserModelSetup::missing();

        if ($missing === []) {
            $this->components->task('Модель пользователя', fn () => true);

            return;
        }

        if ($missing === ['class']) {
            $this->components->error('Модель пользователя '.Nexor::userModel().' не найдена — проверьте config/nexor.php.');

            return;
        }

        $file = UserModelSetup::file();

        if ($file === null) {
            $this->explainUserModel('Модель пользователя недоступна для записи.');

            return;
        }

        $relative = str_replace(base_path().DIRECTORY_SEPARATOR, '', $file);

        if (! $this->option('force') && ! $this->components->confirm("Дописать модель {$relative} для работы с панелью?", true)) {
            $this->explainUserModel('Модель оставлена как есть.');

            return;
        }

        if (! UserModelSetup::patch($file) || UserModelSetup::missing() !== []) {
            $this->explainUserModel('Разметка модели непривычная — дописать её автоматически не вышло.');

            return;
        }

        $this->components->task('Модель пользователя: '.$relative, fn () => true);
    }

    protected function explainUserModel(string $reason): void
    {
        $this->components->warn($reason.' Допишите её сами, иначе панель не заработает:');
        $this->newLine();
        $this->line('<fg=cyan>'.UserModelSetup::snippet().'</>');
        $this->newLine();
    }

    /**
     * Логин из адреса почты; занятый дополняется числом.
     */
    protected function loginFor(string $email, Builder $query): string
    {
        $base = str($email)->before('@')->lower()->replaceMatches('/[^a-z0-9._-]+/', '')->limit(90, '')->value() ?: 'admin';
        $login = $base;

        for ($index = 2; $query->clone()->where('login', $login)->exists(); $index++) {
            $login = $base.$index;
        }

        return $login;
    }

    /**
     * Первый супер-администратор, если его ещё нет.
     *
     * Пароль не приходит аргументом и не придумывается молча: его вводит сам
     * оператор скрытым вводом, поэтому он не оседает ни в истории команд, ни в логах.
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
        $user->login = $user->login ?: $this->loginFor($email, $query);
        $user->is_active = true;
        $user->is_super_admin = true;

        $password = $user->exists ? null : $this->askPassword();

        if ($password !== null) {
            $user->password = $password;
        } elseif (! $user->exists) {
            // Пароль не задали (например, `--force`): вход будет невозможен,
            // пока его не поставят командой nexor:password.
            $user->password = str()->random(32);
        }

        $user->save();

        $role = Role::query()->where('code', Role::SUPER_ADMIN)->first();

        if ($role && method_exists($user, 'roles')) {
            $user->roles()->syncWithoutDetaching([$role->id]);
        } elseif ($role) {
            // Трейт HasRoles ещё не подключён — связываем напрямую.
            DB::table('role_user')->updateOrInsert(['user_id' => $user->getKey(), 'role_id' => $role->id]);
        }

        $this->components->info("Учётная запись {$email} готова, логин: {$user->login}.");

        if ($password === null) {
            $this->components->warn('Пароль не задан. Поставьте свой командой:');
            $this->line('  php artisan nexor:password '.$email);
        }
    }

    /**
     * Пароль задаёт оператор прямо здесь: ввод скрытый, значение никуда не
     * пишется, кроме самой учётной записи. Пустой ответ — пропустить шаг.
     */
    protected function askPassword(): ?string
    {
        if ($this->option('force')) {
            return null;
        }

        for ($attempt = 0; $attempt < 3; $attempt++) {
            $password = (string) $this->secret('Пароль супер-администратора (Enter — задать позже)');

            if ($password === '') {
                return null;
            }

            $validator = Validator::make([
                'password' => $password,
                'password_confirmation' => (string) $this->secret('Повторите пароль'),
            ], [
                'password' => ['required', 'confirmed', Password::default()],
            ], [], ['password' => 'пароль']);

            if ($validator->passes()) {
                return $password;
            }

            foreach ($validator->errors()->all() as $message) {
                $this->components->error($message);
            }
        }

        return null;
    }
}
