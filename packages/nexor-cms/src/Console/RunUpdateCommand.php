<?php

namespace Nexor\Cms\Console;

use Illuminate\Console\Command;
use Nexor\Cms\Support\Updates\ComposerRunner;

/**
 * Выполняет задачу обновления, поставленную из панели.
 *
 * Команда служебная: её запускает сама панель в фоне, руками звать не нужно.
 */
class RunUpdateCommand extends Command
{
    protected $signature = 'nexor:updates:run {id : Идентификатор задачи}';

    protected $description = 'Выполнить задачу обновления, поставленную из панели';

    protected $hidden = true;

    public function handle(): int
    {
        return ComposerRunner::execute((string) $this->argument('id')) === 0
            ? self::SUCCESS
            : self::FAILURE;
    }
}
