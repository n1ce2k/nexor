<?php

namespace Nexor\Shop\Console;

use Illuminate\Console\Command;
use Illuminate\Support\Facades\File;
use Nexor\Cms\Support\TailwindSources;

/**
 * Ставит магазин на сайт: таблицы, права и корзину в макет.
 *
 *     nexor-shop:install                        таблицы и права
 *     nexor-shop:install --layout=site.layout   плюс корзина в макет
 */
class InstallCommand extends Command
{
    protected $signature = 'nexor-shop:install
                            {--layout= : Макет, в который добавить корзину, например site.layout}';

    protected $description = 'Устанавливает модуль «Магазин»: миграции, права и корзину в макет';

    protected const BUTTON = '<livewire:nexor-shop::cart-button />';

    protected const OFFCANVAS = '<livewire:nexor-shop::cart-offcanvas />';

    /** Метка, которую можно поставить в шапке, чтобы кнопка встала точно туда. */
    protected const BUTTON_MARKER = '{{-- nexor-shop:cart-button --}}';

    public function handle(): int
    {
        $this->components->task('Таблицы магазина', fn () => $this->callSilently('migrate', ['--force' => true]) === 0);
        $this->components->task('Права ролей', fn () => $this->callSilently('nexor:permissions') === 0);
        $this->components->task('Tailwind видит шаблоны корзины', function (): bool {
            TailwindSources::add(dirname(__DIR__, 2).'/resources/views');

            return true;
        });

        if ($layout = $this->option('layout')) {
            return $this->installIntoLayout((string) $layout);
        }

        $this->newLine();
        $this->line('  Добавьте корзину в макет сайта — вручную или командой:');
        $this->line('  <fg=cyan>php artisan nexor-shop:install --layout=site.layout</>');
        $this->newLine();
        $this->line('  '.self::BUTTON.'      — в шапку');
        $this->line('  '.self::OFFCANVAS.'   — перед </body>');

        return self::SUCCESS;
    }

    /**
     * Панель ставится перед </body> всегда. Кнопка — только на место метки:
     * где в шапке ей стоять, знает лишь тот, кто верстал шапку.
     */
    protected function installIntoLayout(string $layout): int
    {
        $path = resource_path('views/'.str_replace('.', '/', $layout).'.blade.php');

        if (! File::exists($path)) {
            $this->components->error("Макет не найден: {$path}");

            return self::FAILURE;
        }

        $content = File::get($path);
        $changed = false;

        if (! str_contains($content, 'nexor-shop::cart-offcanvas')) {
            if (! str_contains($content, '</body>')) {
                $this->components->error('В макете нет </body> — выезжающую корзину некуда поставить.');

                return self::FAILURE;
            }

            $content = preg_replace('~</body>~', '    '.self::OFFCANVAS."\n</body>", $content, 1);
            $changed = true;

            $this->components->twoColumnDetail('Выезжающая корзина', '<fg=green>добавлена перед </body></>');
        } else {
            $this->components->twoColumnDetail('Выезжающая корзина', '<fg=yellow>уже есть</>');
        }

        if (str_contains($content, 'nexor-shop::cart-button')) {
            $this->components->twoColumnDetail('Кнопка корзины', '<fg=yellow>уже есть</>');
        } elseif (str_contains($content, self::BUTTON_MARKER)) {
            $content = str_replace(self::BUTTON_MARKER, self::BUTTON, $content);
            $changed = true;

            $this->components->twoColumnDetail('Кнопка корзины', '<fg=green>поставлена на место метки</>');
        } else {
            $this->components->twoColumnDetail('Кнопка корзины', '<fg=yellow>нужно поставить самому</>');
            $this->newLine();
            $this->line('  Вставьте в шапку '.self::BUTTON);
            $this->line('  или поставьте там метку '.self::BUTTON_MARKER.' и запустите команду ещё раз.');
        }

        if ($changed) {
            File::put($path, $content);
        }

        return self::SUCCESS;
    }
}
