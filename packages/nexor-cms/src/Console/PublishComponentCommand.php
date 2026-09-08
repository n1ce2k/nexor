<?php

namespace Nexor\Cms\Console;

use Illuminate\Console\Command;
use Illuminate\Support\Facades\File;
use Illuminate\Support\Str;
use SplFileInfo;

/**
 * Копирует шаблоны компонента в проект — тот же жест, что копирование папки
 * шаблона в свой шаблон сайта в Битриксе.
 *
 * Скопированный файл всегда побеждает пакетный, и обновление CMS его не трогает.
 */
class PublishComponentCommand extends Command
{
    protected $signature = 'nexor:component
                            {component? : Название компонента, например catalog.section}
                            {--template= : Забрать только один шаблон, например tiles}
                            {--force : Перезаписать уже скопированные файлы}';

    protected $description = 'Копирует шаблоны компонента в resources/views/vendor/nexor/components';

    /** Служебные папки, которые компонентами не являются. */
    protected const SKIP = ['admin', 'partials'];

    public function handle(): int
    {
        $available = $this->available();

        if ($available === []) {
            $this->components->error('В пакете нет ни одного компонента.');

            return self::FAILURE;
        }

        $name = $this->argument('component');

        if (! $name) {
            $this->listComponents($available);

            return self::SUCCESS;
        }

        // Принимаем и `catalog.section`, и `catalog/section`.
        $name = str_replace(['/', ':'], '.', trim((string) $name, ' /.'));

        if (! in_array($name, $available, true)) {
            $this->components->error("Компонент «{$name}» не найден.");
            $this->listComponents($available);

            return self::FAILURE;
        }

        return $this->publish($name);
    }

    protected function publish(string $name): int
    {
        $relative = str_replace('.', '/', $name);
        $from = $this->packagePath().'/'.$relative;
        $to = resource_path('views/vendor/nexor/components/'.$relative);

        $files = $this->templatesIn($from);

        if ($template = $this->option('template')) {
            $files = array_values(array_filter(
                $files,
                fn (string $file) => $file === $template.'.blade.php' || str_starts_with($file, $template.'/'),
            ));

            if ($files === []) {
                $this->components->error("У компонента «{$name}» нет шаблона «{$template}».");
                $this->components->bulletList($this->templateNames($from));

                return self::FAILURE;
            }
        }

        File::ensureDirectoryExists($to);

        $copied = 0;
        $skipped = [];

        foreach ($files as $file) {
            $target = $to.'/'.$file;

            if (File::exists($target) && ! $this->option('force')) {
                $skipped[] = $file;

                continue;
            }

            File::ensureDirectoryExists(dirname($target));
            File::copy($from.'/'.$file, $target);

            $this->components->twoColumnDetail($file, '<fg=green>скопирован</>');
            $copied++;
        }

        foreach ($skipped as $file) {
            $this->components->twoColumnDetail($file, '<fg=yellow>уже есть</>');
        }

        $this->newLine();

        if ($copied > 0) {
            $this->components->info('Шаблоны лежат в resources/views/vendor/nexor/components/'.$relative);
            $this->line('  Правьте их как угодно — обновление CMS их не тронет.');
        }

        if ($skipped !== [] && $copied === 0) {
            $this->components->warn('Всё уже скопировано. Перезаписать: --force');
        }

        return self::SUCCESS;
    }

    /**
     * @param  array<int, string>  $available
     */
    protected function listComponents(array $available): void
    {
        $this->newLine();
        $this->components->info('Компоненты пакета:');

        foreach ($available as $name) {
            $templates = implode(', ', $this->templateNames($this->packagePath().'/'.str_replace('.', '/', $name)));

            $this->components->twoColumnDetail('<fg=cyan>'.$name.'</>', $templates);
        }

        $this->newLine();
        $this->line('  Забрать шаблоны: <fg=cyan>php artisan nexor:component catalog.section</>');
    }

    /**
     * Компонент — это папка, в которой лежат сами файлы шаблонов.
     *
     * @return array<int, string>
     */
    protected function available(): array
    {
        $root = $this->packagePath();

        if (! File::isDirectory($root)) {
            return [];
        }

        $names = collect(File::directories($root))
            ->flatMap(fn (string $directory) => $this->scan($directory, basename($directory)))
            ->values();

        return $names->sort()->values()->all();
    }

    /**
     * @return array<int, string>
     */
    protected function scan(string $directory, string $name): array
    {
        if (in_array(basename($directory), self::SKIP, true) || Str::startsWith($name, 'admin.')) {
            return [];
        }

        $found = [];

        if (collect(File::files($directory))->contains(fn (SplFileInfo $file) => $file->getExtension() === 'php')) {
            $found[] = $name;
        }

        foreach (File::directories($directory) as $child) {
            $found = array_merge($found, $this->scan($child, $name.'.'.basename($child)));
        }

        return $found;
    }

    /**
     * Файлы шаблонов компонента, относительно его папки.
     *
     * @return array<int, string>
     */
    protected function templatesIn(string $directory): array
    {
        return collect(File::allFiles($directory))
            ->filter(fn (SplFileInfo $file) => str_ends_with($file->getFilename(), '.blade.php'))
            ->map(fn (SplFileInfo $file) => str_replace('\\', '/', $file->getRelativePathname()))
            ->values()
            ->all();
    }

    /**
     * Имена шаблонов без служебных вложенных файлов: default, tiles.
     *
     * @return array<int, string>
     */
    protected function templateNames(string $directory): array
    {
        return collect(File::files($directory))
            ->filter(fn (SplFileInfo $file) => str_ends_with($file->getFilename(), '.blade.php'))
            ->map(fn (SplFileInfo $file) => Str::before($file->getFilename(), '.blade.php'))
            ->sort()
            ->values()
            ->all();
    }

    protected function packagePath(): string
    {
        return dirname(__DIR__, 2).'/resources/views/components';
    }
}
