<?php

namespace Nexor\Cms\Console;

use Illuminate\Console\Command;
use Nexor\Cms\Support\SiteAssets;
use Throwable;

/**
 * Сборка шаблонов сайта: показать состояние или перенести залитую на место.
 *
 * На хостинге без node сборку делают у себя, заливают папку или zip куда
 * удобно, а команда кладёт её туда, откуда отдаёт маршрут `nexor.asset`.
 */
class SiteAssetsCommand extends Command
{
    protected $signature = 'nexor:assets
                            {source? : Папка или zip со сборкой (например storage/app/build.zip)}';

    protected $description = 'Показать сборку шаблонов сайта или перенести залитую на место';

    public function handle(): int
    {
        $source = (string) $this->argument('source');

        if ($source !== '' && ! $this->import($source)) {
            return self::FAILURE;
        }

        $this->show();

        return self::SUCCESS;
    }

    protected function import(string $source): bool
    {
        $path = is_file($source) || is_dir($source) ? $source : base_path($source);

        try {
            $count = SiteAssets::import($path);
        } catch (Throwable $exception) {
            $this->components->error($exception->getMessage());

            return false;
        }

        $this->components->info("Сборка перенесена: {$count} файлов в ".SiteAssets::path().'.');

        return true;
    }

    /**
     * Манифест обычной сборки: её показываем, когда своя папка пуста.
     *
     * @return array<string, mixed>
     */
    protected function publicManifest(): array
    {
        $file = public_path('build/manifest.json');

        if (! is_file($file)) {
            return [];
        }

        $decoded = json_decode((string) file_get_contents($file), true);

        return is_array($decoded) ? $decoded : [];
    }

    protected function show(): void
    {
        $manifest = SiteAssets::manifest() ?: $this->publicManifest();

        $this->components->twoColumnDetail('Каталог', SiteAssets::path());
        $this->components->twoColumnDetail('Откуда отдаётся', match (true) {
            is_file(public_path('hot')) => '<fg=yellow>Vite в режиме разработки</>',
            is_file(public_path('build/manifest.json')) => 'public/build',
            $manifest !== [] => 'маршрут nexor/assets',
            default => '<fg=red>сборки нет</>',
        });

        if (is_file(public_path('hot'))) {
            $this->newLine();
            $this->line('  Идёт <fg=cyan>npm run dev</> — страницы берут стили и скрипты у него.');

            return;
        }

        foreach (SiteAssets::entries() as $entry) {
            $chunk = $manifest[$entry] ?? null;

            $this->components->twoColumnDetail(
                $entry,
                is_array($chunk) && isset($chunk['file'])
                    ? '<fg=green>'.$chunk['file'].'</>'
                    : '<fg=yellow>нет в манифесте</>',
            );
        }

        if (! SiteAssets::ready()) {
            $this->newLine();
            $this->components->warn('Сборки нет — сайт откроется без стилей и скриптов.');
            $this->line('  Соберите у себя <fg=cyan>npm run build</>, залейте папку public/build или архив с ней');
            $this->line('  и выполните <fg=cyan>php artisan nexor:assets путь/к/build.zip</>');
        }
    }
}
