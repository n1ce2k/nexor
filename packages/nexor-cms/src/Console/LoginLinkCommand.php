<?php

namespace Nexor\Cms\Console;

use Illuminate\Console\Command;
use Illuminate\Support\Facades\URL;
use Nexor\Cms\Http\Controllers\Auth\LoginLinkController;
use Nexor\Cms\Support\Nexor;

class LoginLinkCommand extends Command
{
    protected $signature = 'nexor:login-link
                            {email? : E-mail учётной записи, по умолчанию первый супер-администратор}
                            {--minutes=10 : Сколько минут ссылка действительна}';

    protected $description = 'Одноразовая ссылка для входа в панель без пароля (только для локальной разработки)';

    public function handle(): int
    {
        if (! LoginLinkController::enabled()) {
            $this->components->error('Ссылки для входа выключены. Включите NEXOR_LOGIN_LINK=true вне production.');

            return self::FAILURE;
        }

        $query = Nexor::newUser()->newQuery();

        $user = $this->argument('email')
            ? $query->where('email', $this->argument('email'))->first()
            : $query->where('is_super_admin', true)->orderBy('id')->first();

        if (! $user) {
            $this->components->error('Пользователь не найден.');

            return self::FAILURE;
        }

        $minutes = max(1, (int) $this->option('minutes'));

        $url = URL::temporarySignedRoute(
            'admin.login-link',
            now()->addMinutes($minutes),
            ['account' => $user->getKey()],
        );

        $this->components->info("Ссылка для {$user->email}, действительна {$minutes} мин.");
        $this->line('  '.$url);

        return self::SUCCESS;
    }
}
