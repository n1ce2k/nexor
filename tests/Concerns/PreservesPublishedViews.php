<?php

namespace Tests\Concerns;

use Illuminate\Support\Facades\File;

/**
 * Тесты команд и шаблонов пишут в настоящую resources/views/vendor/... — туда
 * же, где лежат шаблоны сайта. Раньше они стирали эту папку целиком вместе со
 * своими файлами и чужими.
 *
 * Теперь папка перед тестом копируется в storage, а после теста возвращается
 * как была. Упади тест на полпути — копия останется в
 * storage/framework/testing/views-backup.
 */
trait PreservesPublishedViews
{
    /** @var array<string, string|null> папка → копия, null — папки не было */
    private array $preservedViews = [];

    protected function preserveViews(string $relative): string
    {
        $path = resource_path('views/'.$relative);
        $backup = null;

        if (File::isDirectory($path)) {
            $backup = storage_path('framework/testing/views-backup/'.str_replace('/', '_', $relative));

            File::deleteDirectory($backup);
            File::ensureDirectoryExists(dirname($backup));
            File::copyDirectory($path, $backup);
        }

        $this->preservedViews[$path] = $backup;

        return $path;
    }

    protected function restorePreservedViews(): void
    {
        foreach ($this->preservedViews as $path => $backup) {
            File::deleteDirectory($path);

            if ($backup !== null) {
                File::copyDirectory($backup, $path);
                File::deleteDirectory($backup);
            }
        }

        $this->preservedViews = [];
    }
}
