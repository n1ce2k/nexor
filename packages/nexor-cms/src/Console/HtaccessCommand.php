<?php

namespace Nexor\Cms\Console;

use Illuminate\Console\Command;
use Nexor\Cms\Support\RootHtaccess;

/**
 * Корневой .htaccess для хостинга, где сайт открывается из папки проекта.
 *
 * Ставит правила, которые уводят запросы в `public` и не дают этой папке
 * попасть в адреса. Если корень сайта смотрит прямо в `public`, файл не нужен,
 * но и не мешает.
 */
class HtaccessCommand extends Command
{
    protected $signature = 'nexor:htaccess
                            {--force : Заменить и чужой файл; прежний останется как .htaccess.bak}';

    protected $description = 'Поставить корневой .htaccess, уводящий запросы в public';

    public function handle(): int
    {
        $status = RootHtaccess::status();

        if ($status === RootHtaccess::CURRENT) {
            $this->components->info('Корневой .htaccess уже на месте и актуален.');

            return self::SUCCESS;
        }

        if ($status === RootHtaccess::FOREIGN && ! $this->option('force')) {
            $this->components->warn('В корне проекта лежит свой .htaccess, и запросы в public он не уводит — оставляю как есть.');
            $this->line('  Заменить его: <fg=cyan>php artisan nexor:htaccess --force</> (прежний сохранится как .htaccess.bak)');

            return self::FAILURE;
        }

        if (! RootHtaccess::write()) {
            $this->components->error('Не удалось записать '.RootHtaccess::path().'.');

            return self::FAILURE;
        }

        $this->components->info(match ($status) {
            RootHtaccess::MISSING => 'Корневой .htaccess создан.',
            RootHtaccess::OUTDATED => 'Корневой .htaccess обновлён.',
            default => 'Корневой .htaccess заменён, прежний сохранён как .htaccess.bak.',
        });

        return self::SUCCESS;
    }
}
