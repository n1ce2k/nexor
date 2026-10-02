<?php

namespace Nexor\Shop\Console;

use Illuminate\Console\Command;
use Nexor\Cms\Support\Nexor;
use Nexor\Shop\Support\Delivery\Tracking;

/**
 * Проверка статусов посылок у службы доставки.
 *
 * Запускается расписанием раз в полчаса (нужна строка cron с schedule:run).
 * Берёт пачку заказов, давно не проверявшихся, и спрашивает о каждом. Не
 * дошёл ответ — заказ просто проверится в следующий раз.
 */
class SyncShipmentsCommand extends Command
{
    protected $signature = 'shop:shipments:sync
                            {--limit=50 : Сколько заказов проверить за один запуск}';

    protected $description = 'Проверить статусы отправленных заказов у службы доставки';

    public function handle(): int
    {
        if (! Nexor::feature('shop')) {
            $this->components->info('Модуль «Магазин» выключен — проверять нечего.');

            return self::SUCCESS;
        }

        $orders = Tracking::due(max(1, (int) $this->option('limit')));
        $changed = 0;

        foreach ($orders as $order) {
            if ($event = Tracking::refresh($order)) {
                $changed++;
                $this->line("  Заказ №{$order->number}: {$event->current}");
            }
        }

        $this->components->info("Проверено заказов: {$orders->count()}, статус сменился у {$changed}.");

        return self::SUCCESS;
    }
}
