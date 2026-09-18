<?php

namespace Nexor\Cms\Console;

use Illuminate\Console\Command;
use Nexor\Cms\Support\EnvFile;
use Nexor\Cms\Support\License\LicenseKey;
use Nexor\Cms\Support\Licensing;

/**
 * Лицензия сайта: показать текущую или ввести новый ключ.
 */
class LicenseCommand extends Command
{
    protected $signature = 'nexor:license
                            {key? : Новый лицензионный ключ вида nxr-...}';

    protected $description = 'Показать лицензию сайта или ввести новый ключ';

    public function handle(): int
    {
        $key = (string) $this->argument('key');

        if ($key !== '') {
            return $this->store($key);
        }

        $this->show();

        return self::SUCCESS;
    }

    protected function store(string $key): int
    {
        $parsed = LicenseKey::parse($key, Licensing::publicKey());

        if ($parsed === null) {
            $this->components->error('Ключ не прошёл проверку — убедитесь, что он скопирован целиком.');

            return self::FAILURE;
        }

        if ($parsed->isExpired()) {
            $this->components->error('Срок этого ключа истёк '.date('d.m.Y', (int) $parsed->expiresAt).'.');

            return self::FAILURE;
        }

        if (! EnvFile::set('NEXOR_LICENSE_KEY', $parsed->key)) {
            $this->components->error('Не удалось записать ключ в .env — впишите NEXOR_LICENSE_KEY вручную.');

            return self::FAILURE;
        }

        config(['nexor.license_key' => $parsed->key]);
        Licensing::flush();

        $this->components->info('Лицензия '.$parsed->edition->label().' записана, номер ключа '.$parsed->number().'.');
        $this->line('  Если включён кеш конфигурации, обновите его: <fg=cyan>php artisan config:cache</>');

        return self::SUCCESS;
    }

    protected function show(): void
    {
        $state = Licensing::state();

        $this->components->twoColumnDetail('Редакция', $state['edition_label']);
        $this->components->twoColumnDetail('Состояние', match ($state['status']) {
            Licensing::OK => '<fg=green>ключ в порядке</>',
            Licensing::NONE => '<fg=yellow>ключа нет</>',
            Licensing::EXPIRED => '<fg=yellow>срок истёк</>',
            default => '<fg=red>ключ не прошёл проверку</>',
        });

        if ($state['number']) {
            $this->components->twoColumnDetail('Номер ключа', $state['number']);
        }

        if ($state['expires_at']) {
            $this->components->twoColumnDetail('Действует до', date('d.m.Y', strtotime($state['expires_at'])));
        }

        if ($state['message']) {
            $this->newLine();
            $this->components->warn($state['message']);
            $this->line('  Ввести ключ: <fg=cyan>php artisan nexor:license nxr-...</>');
        }
    }
}
