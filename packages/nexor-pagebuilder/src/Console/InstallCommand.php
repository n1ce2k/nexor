<?php

namespace Nexor\PageBuilder\Console;

use Illuminate\Console\Command;

/**
 * Ставит конструктор на сайт: таблица раскладок.
 */
class InstallCommand extends Command
{
    protected $signature = 'nexor-pagebuilder:install';

    protected $description = 'Устанавливает модуль «Конструктор страниц»: таблица раскладок';

    public function handle(): int
    {
        $this->components->task('Таблица раскладок', fn () => $this->callSilently('migrate', ['--force' => true]) === 0);

        $this->newLine();
        $this->line('  Дальше: инфоблок → «Параметры» → «Использовать конструктор детальной страницы».');
        $this->line('  Свой шаблон блока: <fg=cyan>php artisan nexor-pagebuilder:component text</>');
        $this->line('  Стили блоков (классы pb-*) подключаются сами; свои — NEXOR_PAGEBUILDER_ASSETS=false.');

        return self::SUCCESS;
    }
}
