<?php

namespace Nexor\Cms\Console;

use Illuminate\Console\Command;
use Illuminate\Support\Facades\File;
use Nexor\Cms\Support\ContentBlocks;
use SplFileInfo;

/**
 * Собирает блоки `<x-nexor::edit>` из шаблонов сайта в хранилище.
 *
 * Открывать каждую страницу в режиме правки, чтобы блок появился в списке,
 * — работа для машины. Команда проходит по шаблонам, вынимает ключ, тип и
 * значение по умолчанию и дописывает то, чего в хранилище ещё нет.
 *
 * Значения записываются с пометкой «не правлен»: пока блок не тронули на
 * сайте, главным остаётся шаблон, и правка текста в `.blade.php` продолжает
 * действовать.
 */
class ScanContentCommand extends Command
{
    protected $signature = 'nexor:content:scan
        {--path=* : Где искать; по умолчанию resources/views}
        {--prune : Убрать записи, ключей которых в шаблонах больше нет}
        {--dry-run : Показать, что нашлось, и ничего не записывать}';

    protected $description = 'Собирает блоки <x-nexor::edit> из шаблонов в хранилище';

    public function handle(): int
    {
        $paths = $this->option('path') ?: [resource_path('views')];
        $found = [];

        foreach ($paths as $path) {
            if (! File::isDirectory($path)) {
                $this->components->warn('Не папка: '.$path);

                continue;
            }

            foreach (File::allFiles($path) as $file) {
                if (str_ends_with($file->getFilename(), '.blade.php')) {
                    $found = [...$found, ...$this->blocksIn($file)];
                }
            }
        }

        if ($found === []) {
            $this->components->info('Блоков в шаблонах не нашлось.');

            return self::SUCCESS;
        }

        $stored = ContentBlocks::all();
        $new = array_diff_key($found, $stored);
        $gone = $this->option('prune') ? array_diff_key($stored, $found) : [];

        $this->components->info('Блоков в шаблонах: '.count($found).', из них новых: '.count($new).'.');

        if ($this->option('dry-run')) {
            $this->table(
                ['Ключ', 'Тип', 'Значение из шаблона'],
                array_map(
                    fn (string $key, array $block) => [$key, $block['type'], mb_strimwidth($block['value'], 0, 60, '…')],
                    array_keys($new),
                    $new,
                ),
            );

            if ($gone !== []) {
                $this->components->warn('Лишних записей: '.count($gone).' — '.implode(', ', array_keys($gone)));
            }

            return self::SUCCESS;
        }

        foreach ($new as $key => $block) {
            ContentBlocks::seed($key, $block['type'], $block['value']);
        }

        foreach (array_keys($gone) as $key) {
            ContentBlocks::forget($key);
        }

        $this->components->info('Записано: '.count($new).($gone === [] ? '.' : ', убрано: '.count($gone).'.'));

        return self::SUCCESS;
    }

    /**
     * Блоки одного шаблона.
     *
     * Разбор нарочно простой: ключ должен быть написан строкой, а не выражением
     * `key="{{ $code }}"` — такой блок команда пропускает и говорит об этом.
     * Собранное из переменных всё равно не перечислить, не запустив страницу.
     *
     * @return array<string, array{type: string, value: string}>
     */
    protected function blocksIn(SplFileInfo $file): array
    {
        $source = (string) File::get($file->getPathname());
        $blocks = [];

        // Текст: <x-nexor::edit ...>значение</x-nexor::edit>
        preg_match_all('#<x-nexor::edit\b([^>]*)>(.*?)</x-nexor::edit>#su', $source, $matches, PREG_SET_ORDER);

        foreach ($matches as $match) {
            $key = $this->attribute($match[1], 'key');

            if ($key === null) {
                $this->skipped($file, $match[0]);

                continue;
            }

            $blocks[$key] = [
                'type' => str_contains($match[1], 'html') ? 'html' : 'text',
                'value' => trim($match[2]),
            ];
        }

        // Картинка: <x-nexor::edit.image ... />
        preg_match_all('#<x-nexor::edit\.image\b([^>]*?)/?>#su', $source, $images, PREG_SET_ORDER);

        foreach ($images as $match) {
            $key = $this->attribute($match[1], 'key');

            if ($key === null) {
                $this->skipped($file, $match[0]);

                continue;
            }

            $blocks[$key] = ['type' => 'image', 'value' => (string) $this->attribute($match[1], 'src')];
        }

        return $blocks;
    }

    /**
     * Значение атрибута, если оно написано простой строкой.
     */
    protected function attribute(string $attributes, string $name): ?string
    {
        if (! preg_match('#\b'.preg_quote($name, '#').'\s*=\s*"([^"]*)"#', $attributes, $match)) {
            return null;
        }

        // Значение, собранное на лету, командой не берётся.
        return str_contains($match[1], '{{') ? null : $match[1];
    }

    protected function skipped(SplFileInfo $file, string $tag): void
    {
        $this->components->warn(
            'Пропущен блок с вычисляемым ключом: '.$file->getRelativePathname().' — '.mb_strimwidth(trim($tag), 0, 70, '…'),
        );
    }
}
