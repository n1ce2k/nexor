<?php

namespace App\Console\Commands;

use Illuminate\Console\Command;
use Nexor\Cms\Enums\License;
use Nexor\Cms\Support\License\LicenseKey;
use Nexor\Cms\Support\Licensing;
use Throwable;

/**
 * Выпуск лицензионного ключа NEXOR.
 *
 * Команда живёт в монорепозитории и в пакет не попадает: клиенту приватный
 * ключ не нужен, а без него ключи не выпустить. Сам приватный ключ лежит вне
 * репозитория — путь задаётся `NEXOR_LICENSE_PRIVATE_KEY`.
 */
class IssueLicenseKey extends Command
{
    protected $signature = 'nexor:license:make
                            {--edition=pro : Редакция: lite, standart или pro}
                            {--days= : Срок в днях; без него ключ бессрочный}
                            {--for= : Кому выдан — только для журнала выпуска}
                            {--private= : Путь к приватному ключу}';

    protected $description = 'Выпустить лицензионный ключ NEXOR';

    public function handle(): int
    {
        $edition = License::tryFrom(strtolower((string) $this->option('edition')));

        if ($edition === null) {
            $this->components->error('Редакция должна быть lite, standart или pro.');

            return self::FAILURE;
        }

        $path = $this->privateKeyPath();

        if (! is_file($path)) {
            $this->components->error("Приватный ключ не найден: {$path}");
            $this->line('  Путь задаётся в .env: <fg=cyan>NEXOR_LICENSE_PRIVATE_KEY=C:/Users/andre/.nexor/license-private.pem</>');

            return self::FAILURE;
        }

        $days = $this->option('days') === null ? null : (int) $this->option('days');
        $expiresAt = $days === null ? null : time() + $days * 86400;

        try {
            $key = LicenseKey::issue($edition, $expiresAt, (string) file_get_contents($path));
        } catch (Throwable $exception) {
            $this->components->error($exception->getMessage());

            return self::FAILURE;
        }

        // Ключ проверяется тем же публичным ключом, что и на сайте клиента:
        // так опечатка в конфигурации видна сразу, а не у первого покупателя.
        if (LicenseKey::parse($key->key, Licensing::publicKey()) === null) {
            $this->components->error('Ключ выпущен, но не проходит проверку публичным ключом из конфига.');

            return self::FAILURE;
        }

        $this->newLine();
        $this->components->twoColumnDetail('Редакция', $edition->label());
        $this->components->twoColumnDetail('Номер ключа', $key->number());
        $this->components->twoColumnDetail('Действует до', $expiresAt === null ? 'бессрочно' : date('d.m.Y', $expiresAt));

        if ($this->option('for')) {
            $this->components->twoColumnDetail('Кому', (string) $this->option('for'));
        }

        $this->newLine();
        $this->line('<fg=cyan>'.$key->key.'</>');
        $this->newLine();
        $this->line('  Установка у клиента: <fg=cyan>php artisan nexor:license '.substr($key->key, 0, 12).'...</>');

        return self::SUCCESS;
    }

    protected function privateKeyPath(): string
    {
        $path = (string) env('NEXOR_LICENSE_PRIVATE_KEY');

        if ($this->option('private')) {
            $path = (string) $this->option('private');
        }

        if ($path === '') {
            $home = (string) (getenv('USERPROFILE') ?: getenv('HOME'));
            $path = rtrim(str_replace('\\', '/', $home), '/').'/.nexor/license-private.pem';
        }

        return $path;
    }
}
