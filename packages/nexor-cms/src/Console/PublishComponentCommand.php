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
 *     nexor:component all                          шаблоны всех компонентов
 *     nexor:component catalog.section              все шаблоны компонента под своими именами
 *     nexor:component catalog.section blog         свой шаблон blog на основе default
 *     nexor:component catalog.section blog --from=tiles
 *
 * Скопированный файл всегда побеждает пакетный, и обновление CMS его не трогает.
 */
class PublishComponentCommand extends Command
{
    protected $signature = 'nexor:component
                            {component? : Название компонента, например catalog.section, или all}
                            {template? : Имя вашего шаблона, например blog}
                            {--from=default : Шаблон пакета, с которого снимается копия}
                            {--force : Перезаписать уже скопированные файлы}';

    protected $description = 'Копирует шаблоны компонента в resources/views/vendor/nexor/components';

    /** Служебные папки, которые компонентами не являются. */
    protected const SKIP = ['admin', 'partials'];

    /** Части шаблона, которые копируются вместе с ним. */
    protected const PARTIALS = ['partials'];

    /** Имя шаблона становится именем файла, поэтому набор символов узкий. */
    protected const NAME = '/^[A-Za-z0-9_-]+$/';

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

        if ($name === 'all') {
            return $this->copyEverything($available);
        }

        // Принимаем и `catalog.section`, и `catalog/section`.
        $name = str_replace(['/', ':'], '.', trim((string) $name, ' /.'));

        if (! in_array($name, $available, true)) {
            $this->components->error("Компонент «{$name}» не найден.");
            $this->listComponents($available);

            return self::FAILURE;
        }

        return $this->argument('template')
            ? $this->copyAs($name, (string) $this->argument('template'))
            : $this->copyAll($name);
    }

    /**
     * Свой шаблон под своим именем — копия одного пакетного файла.
     */
    protected function copyAs(string $component, string $template): int
    {
        if (! preg_match(self::NAME, $template)) {
            $this->components->error('В имени шаблона можно использовать латиницу, цифры, дефис и подчёркивание.');

            return self::FAILURE;
        }

        $relative = str_replace('.', '/', $component);
        $from = $this->packagePath().'/'.$relative;
        $source = $from.'/'.$this->option('from').'.blade.php';

        if (! File::exists($source)) {
            $this->components->error("У компонента «{$component}» нет шаблона «{$this->option('from')}».");
            $this->components->bulletList($this->templateNames($from));

            return self::FAILURE;
        }

        $target = resource_path('views/vendor/nexor/components/'.$relative.'/'.$template.'.blade.php');

        if (File::exists($target) && ! $this->option('force')) {
            $this->components->warn("Шаблон «{$template}» уже есть. Перезаписать: --force");

            return self::FAILURE;
        }

        File::ensureDirectoryExists(dirname($target));
        File::copy($source, $target);

        $this->components->info('Шаблон создан: resources/views/vendor/nexor/components/'.$relative.'/'.$template.'.blade.php');
        $this->newLine();
        $this->line('  Подключается так:');
        $this->line('  <fg=cyan><x-nexor::'.$component.' template="'.$template.'" /></>');

        return self::SUCCESS;
    }

    /**
     * `all` — шаблоны всех компонентов разом.
     *
     * @param  array<int, string>  $available
     */
    protected function copyEverything(array $available): int
    {
        $copied = 0;

        foreach ($available as $component) {
            $this->newLine();
            $this->components->info($component);

            $copied += $this->copyFiles($component);
        }

        $this->newLine();

        $copied > 0
            ? $this->components->info('Все шаблоны лежат в resources/views/vendor/nexor/components — правьте их как угодно.')
            : $this->components->warn('Всё уже скопировано. Перезаписать: --force');

        return self::SUCCESS;
    }

    /**
     * Все шаблоны компонента под их собственными именами.
     */
    protected function copyAll(string $component): int
    {
        $relative = str_replace('.', '/', $component);
        $copied = $this->copyFiles($component);

        $this->newLine();

        if ($copied > 0) {
            $this->components->info('Шаблоны лежат в resources/views/vendor/nexor/components/'.$relative);
            $this->line('  Правьте их как угодно — обновление CMS их не тронет.');
            $this->newLine();
            $this->line('  Свой шаблон отдельным именем:');
            $this->line('  <fg=cyan>php artisan nexor:component '.$component.' blog</>');
        } else {
            $this->components->warn('Всё уже скопировано. Перезаписать: --force');
        }

        return self::SUCCESS;
    }

    /**
     * Копирует файлы одного компонента; уже скопированные без --force не трогает.
     *
     * @return int Сколько файлов скопировано
     */
    protected function copyFiles(string $component): int
    {
        $relative = str_replace('.', '/', $component);
        $from = $this->packagePath().'/'.$relative;
        $to = resource_path('views/vendor/nexor/components/'.$relative);

        File::ensureDirectoryExists($to);

        $copied = 0;

        foreach ($this->templatesIn($from) as $file) {
            $target = $to.'/'.$file;

            if (File::exists($target) && ! $this->option('force')) {
                $this->components->twoColumnDetail($file, '<fg=yellow>уже есть</>');

                continue;
            }

            File::ensureDirectoryExists(dirname($target));
            File::copy($from.'/'.$file, $target);

            $this->components->twoColumnDetail($file, '<fg=green>скопирован</>');
            $copied++;
        }

        return $copied;
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
        $this->line('  Забрать все компоненты:     <fg=cyan>php artisan nexor:component all</>');
        $this->line('  Все шаблоны компонента:     <fg=cyan>php artisan nexor:component catalog.section</>');
        $this->line('  Свой шаблон:                <fg=cyan>php artisan nexor:component catalog.section blog</>');
        $this->line('  На основе другого:          <fg=cyan>php artisan nexor:component catalog.section blog --from=tiles</>');
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

        return collect(File::directories($root))
            ->flatMap(fn (string $directory) => $this->scan($directory, basename($directory)))
            ->sort()
            ->values()
            ->all();
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
     * Вложенная папка — это либо служебные части (`partials`), которые нужно
     * забрать вместе с шаблоном, либо соседний компонент (`menu/sections`);
     * второй трогать нельзя.
     *
     * @return array<int, string>
     */
    protected function templatesIn(string $directory): array
    {
        $files = collect(File::files($directory))
            ->filter(fn (SplFileInfo $file) => str_ends_with($file->getFilename(), '.blade.php'))
            ->map(fn (SplFileInfo $file) => $file->getFilename());

        foreach (File::directories($directory) as $child) {
            if (! in_array(basename($child), self::PARTIALS, true)) {
                continue;
            }

            $files = $files->merge(
                collect(File::allFiles($child))
                    ->filter(fn (SplFileInfo $file) => str_ends_with($file->getFilename(), '.blade.php'))
                    ->map(fn (SplFileInfo $file) => basename($child).'/'.$file->getFilename()),
            );
        }

        return $files->values()->all();
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
