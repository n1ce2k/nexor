<?php

namespace Nexor\Cms\Console;

use Illuminate\Console\Command;
use Nexor\Cms\Models\Permission;
use Nexor\Cms\Support\Permissions;

class SyncPermissionsCommand extends Command
{
    protected $signature = 'nexor:permissions';

    protected $description = 'Пересобрать каталог прав: статические права админки и права всех инфоблоков';

    public function handle(): int
    {
        $before = Permission::query()->count();

        Permissions::syncAll();

        $after = Permission::query()->count();

        $this->components->info("Права синхронизированы: было {$before}, стало {$after}.");

        return self::SUCCESS;
    }
}
