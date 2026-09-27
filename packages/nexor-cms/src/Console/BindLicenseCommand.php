<?php

namespace Nexor\Cms\Console;

use Illuminate\Console\Command;
use Nexor\Cms\Support\Install;
use Nexor\Cms\Support\License\Host;
use Nexor\Cms\Support\Licensing;

/**
 * Закрепляет установку за доменом.
 *
 * Нужна при законном переносе сайта: база помнит прежний домен и без привязки
 * панель на новом остаётся закрытой. Ключ при этом должен быть выдан на новый
 * домен — иначе перенос делается не здесь, а у издателя.
 */
class BindLicenseCommand extends Command
{
    protected $signature = 'nexor:license:bind
                            {--host= : Домен, за которым закрепить установку}';

    protected $description = 'Закрепить установку за текущим доменом';

    public function handle(): int
    {
        $host = Host::normalise($this->option('host') ?: Host::current());

        if ($host === '') {
            $this->components->error('Домен не определён — укажите --host=site.ru или задайте APP_URL.');

            return self::FAILURE;
        }

        $key = Licensing::key();

        if ($key !== null && ! $key->matches($host)) {
            $this->components->error("Ключ выдан на домен {$key->host}, привязать его к {$host} нельзя.");
            $this->line('  Нужен ключ на этот домен: <fg=cyan>php artisan nexor:license nxr-...</>');

            return self::FAILURE;
        }

        $previous = Install::host();

        Install::bind($host, $key?->serial);

        $this->components->twoColumnDetail('Домен', $host);
        $this->components->twoColumnDetail('Установка', (string) Install::id());

        if ($key !== null) {
            $this->components->twoColumnDetail('Ключ', $key->number().', '.$key->edition->label());
        }

        if ($previous !== null && ! Host::same($previous, $host)) {
            $this->components->warn("Прежняя привязка была к домену {$previous}.");
        }

        if ($key === null) {
            $this->components->warn('Лицензионного ключа нет — сайт работает в редакции Lite.');
        }

        if (Host::isLocal($host)) {
            $this->components->warn('Это локальный адрес: на нём привязка всё равно не проверяется.');
        }

        return self::SUCCESS;
    }
}
