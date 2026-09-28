<?php

namespace Nexor\Cms\Console;

use Illuminate\Console\Command;
use Nexor\Cms\Support\Nexor;
use Nexor\Cms\Support\UserModelSetup;

/**
 * Готовит модель пользователя приложения к работе с панелью.
 *
 * Ту же работу делает `nexor:install`, но починка не должна требовать запуска
 * установки целиком: модель могли оставить как есть, и тогда панель падает на
 * первом же `hasPermission()`.
 */
class UserModelCommand extends Command
{
    protected $signature = 'nexor:user-model
                            {--force : Дописать модель без вопроса}';

    protected $description = 'Подготовить модель пользователя приложения к работе с панелью';

    public function handle(): int
    {
        $missing = UserModelSetup::missing();

        if ($missing === []) {
            $this->components->info('Модель '.Nexor::userModel().' готова к работе с панелью.');

            return self::SUCCESS;
        }

        if ($missing === ['class']) {
            $this->components->error('Модель пользователя '.Nexor::userModel().' не найдена — проверьте config/nexor.php.');

            return self::FAILURE;
        }

        $file = UserModelSetup::file();

        if ($file === null) {
            return $this->explain('Модель пользователя недоступна для записи.');
        }

        $relative = str_replace(base_path().DIRECTORY_SEPARATOR, '', $file);

        if (! $this->option('force') && ! $this->components->confirm("Дописать модель {$relative} для работы с панелью?", true)) {
            return $this->explain('Модель оставлена как есть.');
        }

        // Проверяем по файлу, а не по загруженному классу: в этом же процессе
        // класс остался прежним, и отражение показало бы старую модель.
        if (! UserModelSetup::patch($file) || ! UserModelSetup::sourceReady((string) file_get_contents($file))) {
            return $this->explain('Разметка модели непривычная — дописать её автоматически не вышло.');
        }

        $this->components->info("Модель {$relative} дописана: контракт, трейты, колонки и приведения типов.");
        $this->line('  Если кеши собраны, сбросьте их: <fg=cyan>php artisan optimize:clear</>');

        return self::SUCCESS;
    }

    /**
     * Показывает, что дописать руками. Возвращает код неудачи — вызывающему
     * нужен именно он.
     */
    protected function explain(string $reason): int
    {
        $this->components->warn($reason.' Допишите её сами, иначе панель не заработает:');
        $this->newLine();
        $this->line('<fg=cyan>'.UserModelSetup::snippet().'</>');
        $this->newLine();

        return self::FAILURE;
    }
}
