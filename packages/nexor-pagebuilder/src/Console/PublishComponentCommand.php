<?php

namespace Nexor\PageBuilder\Console;

use Illuminate\Console\Command;
use Illuminate\Support\Facades\File;
use Nexor\PageBuilder\Blocks\Block;
use Nexor\PageBuilder\PageBuilder;

/**
 * Копирует шаблоны блоков в проект, чтобы их переверстать.
 *
 *     nexor-pagebuilder:component            список блоков
 *     nexor-pagebuilder:component text       один блок
 *     nexor-pagebuilder:component all        все блоки и обёртка
 *
 * Копия лежит в resources/views/vendor/nexor-pagebuilder и всегда побеждает
 * шаблон модуля; обновление модуля её не трогает.
 */
class PublishComponentCommand extends Command
{
    protected $signature = 'nexor-pagebuilder:component
                            {block? : Тип блока (header, text, …), content — обёртка, или all}
                            {--force : Перезаписать уже скопированные файлы}';

    protected $description = 'Копирует шаблоны блоков конструктора в resources/views/vendor/nexor-pagebuilder';

    /** Обёртка вокруг всех блоков. */
    protected const WRAPPER = 'content';

    public function handle(): int
    {
        $name = $this->argument('block');
        $blocks = $this->packagedBlocks();

        if (! $name) {
            $this->listBlocks($blocks);

            return self::SUCCESS;
        }

        if ($name !== 'all' && $name !== self::WRAPPER && ! isset($blocks[$name])) {
            $this->components->error("Блок «{$name}» не найден.");
            $this->listBlocks($blocks);

            return self::FAILURE;
        }

        $files = match ($name) {
            'all' => ['content.blade.php', ...array_map(fn (string $type) => "blocks/{$type}.blade.php", array_keys($blocks))],
            self::WRAPPER => ['content.blade.php'],
            default => ["blocks/{$name}.blade.php"],
        };

        $copied = 0;

        foreach ($files as $file) {
            $target = resource_path('views/vendor/nexor-pagebuilder/'.$file);

            if (File::exists($target) && ! $this->option('force')) {
                $this->components->twoColumnDetail($file, '<fg=yellow>уже есть</>');

                continue;
            }

            File::ensureDirectoryExists(dirname($target));
            File::copy(dirname(__DIR__, 2).'/resources/views/'.$file, $target);

            $this->components->twoColumnDetail($file, '<fg=green>скопирован</>');
            $copied++;
        }

        $this->newLine();

        $copied > 0
            ? $this->components->info('Шаблоны лежат в resources/views/vendor/nexor-pagebuilder — правьте как угодно.')
            : $this->components->warn('Всё уже скопировано. Перезаписать: --force');

        return self::SUCCESS;
    }

    /**
     * Блоки, у которых шаблон лежит в самом модуле.
     *
     * @return array<string, Block>
     */
    protected function packagedBlocks(): array
    {
        return array_filter(
            PageBuilder::blocks()->all(),
            fn (Block $block) => File::exists(dirname(__DIR__, 2).'/resources/views/blocks/'.$block->type().'.blade.php'),
        );
    }

    /**
     * @param  array<string, Block>  $blocks
     */
    protected function listBlocks(array $blocks): void
    {
        $this->newLine();
        $this->components->info('Блоки конструктора:');

        $this->components->twoColumnDetail('<fg=cyan>'.self::WRAPPER.'</>', 'Обёртка вокруг всех блоков');

        foreach ($blocks as $type => $block) {
            $this->components->twoColumnDetail('<fg=cyan>'.$type.'</>', $block->label());
        }

        $this->newLine();
        $this->line('  Забрать шаблон:  <fg=cyan>php artisan nexor-pagebuilder:component text</>');
        $this->line('  Забрать все:     <fg=cyan>php artisan nexor-pagebuilder:component all</>');
    }
}
