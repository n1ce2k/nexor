<?php

namespace Nexor\Shop\Console;

use Illuminate\Console\Command;
use Illuminate\Support\Facades\File;

/**
 * Копирует шаблоны компонентов магазина в проект, чтобы их переверстать.
 *
 *     nexor-shop:component                 список компонентов
 *     nexor-shop:component cart-page       корзина страницей с её частями
 *     nexor-shop:component all             всё сразу
 *
 * Копия лежит в resources/views/vendor/nexor-shop и всегда побеждает пакетный
 * шаблон; обновление модуля её не трогает.
 */
class PublishComponentCommand extends Command
{
    protected $signature = 'nexor-shop:component
                            {component? : add-to-cart, cart-button, cart-offcanvas, cart-page, checkout или all}
                            {--force : Перезаписать уже скопированные файлы}';

    protected $description = 'Копирует шаблоны компонентов магазина в resources/views/vendor/nexor-shop';

    /**
     * Компонент → файлы, из которых он собран. Общие части (строки корзины,
     * форма заказа) едут вместе с каждым, кто их подключает.
     *
     * @var array<string, array{label: string, tag: string, files: array<int, string>}>
     */
    protected const COMPONENTS = [
        'add-to-cart' => [
            'label' => 'Кнопка «В корзину»',
            'tag' => '<livewire:nexor-shop::add-to-cart :element-id="$element->id" />',
            'files' => ['livewire/add-to-cart.blade.php', 'partials/tab-sync.blade.php'],
        ],
        'cart-button' => [
            'label' => 'Иконка корзины в шапке',
            'tag' => '<livewire:nexor-shop::cart-button />',
            'files' => ['livewire/cart-button.blade.php', 'partials/cart-icon.blade.php'],
        ],
        'cart-offcanvas' => [
            'label' => 'Выезжающая корзина',
            'tag' => '<livewire:nexor-shop::cart-offcanvas />',
            'files' => [
                'livewire/cart-offcanvas.blade.php', 'partials/lines.blade.php', 'partials/order-form.blade.php',
                'partials/totals.blade.php', 'partials/success.blade.php',
            ],
        ],
        'cart-page' => [
            'label' => 'Корзина страницей',
            'tag' => '<livewire:nexor-shop::cart-page />',
            'files' => [
                'pages/cart.blade.php', 'livewire/cart-page.blade.php', 'partials/lines.blade.php',
                'partials/order-form.blade.php', 'partials/totals.blade.php', 'partials/success.blade.php',
            ],
        ],
        'checkout' => [
            'label' => 'Оформление заказа',
            'tag' => '<livewire:nexor-shop::checkout />',
            'files' => [
                'pages/checkout.blade.php', 'livewire/checkout.blade.php', 'partials/order-form.blade.php',
                'partials/totals.blade.php', 'partials/success.blade.php',
            ],
        ],
    ];

    public function handle(): int
    {
        $name = $this->argument('component');

        if (! $name) {
            $this->listComponents();

            return self::SUCCESS;
        }

        if ($name !== 'all' && ! isset(self::COMPONENTS[$name])) {
            $this->components->error("Компонент «{$name}» не найден.");
            $this->listComponents();

            return self::FAILURE;
        }

        $files = $name === 'all'
            ? collect(self::COMPONENTS)->pluck('files')->flatten()->unique()->values()->all()
            : self::COMPONENTS[$name]['files'];

        $copied = 0;

        foreach ($files as $file) {
            $target = resource_path('views/vendor/nexor-shop/'.$file);

            if (File::exists($target) && ! $this->option('force')) {
                $this->components->twoColumnDetail($file, '<fg=yellow>уже есть</>');

                continue;
            }

            File::ensureDirectoryExists(dirname($target));
            File::copy($this->packagePath($file), $target);

            $this->components->twoColumnDetail($file, '<fg=green>скопирован</>');
            $copied++;
        }

        $this->newLine();

        $copied > 0
            ? $this->components->info('Шаблоны лежат в resources/views/vendor/nexor-shop — правьте как угодно.')
            : $this->components->warn('Всё уже скопировано. Перезаписать: --force');

        return self::SUCCESS;
    }

    protected function listComponents(): void
    {
        $this->newLine();
        $this->components->info('Компоненты магазина:');

        foreach (self::COMPONENTS as $name => $component) {
            $this->components->twoColumnDetail('<fg=cyan>'.$name.'</>', $component['label']);
            $this->line('    '.$component['tag']);
        }

        $this->newLine();
        $this->line('  Забрать шаблон:  <fg=cyan>php artisan nexor-shop:component cart-page</>');
        $this->line('  Забрать все:     <fg=cyan>php artisan nexor-shop:component all</>');
    }

    protected function packagePath(string $file): string
    {
        return dirname(__DIR__, 2).'/resources/views/'.$file;
    }
}
