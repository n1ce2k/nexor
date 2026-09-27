<?php

namespace Nexor\Cms\Console;

use Illuminate\Console\Command;
use Nexor\Cms\Models\CookieConsent;
use Nexor\Cms\Support\Cookies;

/**
 * Чистит журнал согласий.
 *
 * Согласие нужно на случай спора, а не навсегда: срок хранения задаётся в
 * панели, команда удаляет всё, что старше. Ставится в расписание.
 */
class PruneCookieConsentsCommand extends Command
{
    protected $signature = 'nexor:cookies:prune {--months= : Сколько месяцев хранить; по умолчанию — настройка панели}';

    protected $description = 'Удаляет согласия на cookie старше заданного срока';

    public function handle(): int
    {
        $months = (int) ($this->option('months') ?: Cookies::get('keep_months'));

        if ($months < 1) {
            $this->components->info('Срок хранения не задан — журнал не трогаем.');

            return self::SUCCESS;
        }

        $deleted = CookieConsent::query()->where('created_at', '<', now()->subMonths($months))->delete();

        $this->components->info('Удалено записей: '.$deleted.' (старше '.$months.' мес.).');

        return self::SUCCESS;
    }
}
