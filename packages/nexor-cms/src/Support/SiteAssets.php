<?php

namespace Nexor\Cms\Support;

use Illuminate\Foundation\Vite;
use Illuminate\Support\Facades\File;
use Illuminate\Support\HtmlString;
use RuntimeException;
use ZipArchive;

/**
 * Сборка шаблонов сайта, лежащая вне `public`.
 *
 * На дешёвом хостинге node нет вовсе, а `@vite` без манифеста роняет страницу.
 * Поэтому собранную папку можно просто залить в `storage/app/build`: файлы
 * отдаёт маршрут `nexor.asset`, а теги выводит `<x-nexor::assets />`.
 *
 * Если рядом работает Vite (`public/hot`) или сборка лежит в `public/build`,
 * компонент отдаёт их — на своей машине всё остаётся как было.
 */
class SiteAssets
{
    /** Начало адреса, по которому отдаётся сборка. */
    public const PREFIX = 'nexor/assets';

    /** @var array<string, mixed>|null */
    protected static ?array $manifest = null;

    /**
     * Каталог, куда заливают результат `npm run build`.
     */
    public static function path(): string
    {
        $path = (string) config('nexor.assets.path');

        // Разделители в одну сторону: путь уходит в вывод команды и в тесты.
        return str_replace('\\', '/', rtrim($path !== '' ? $path : storage_path('app/build'), '/\\'));
    }

    /**
     * Точки входа сайта — те же, что уходили в `@vite`.
     *
     * @return array<int, string>
     */
    public static function entries(): array
    {
        $entries = config('nexor.assets.entries', ['resources/css/app.css', 'resources/js/app.js']);

        return array_values(array_filter(array_map('strval', (array) $entries)));
    }

    /**
     * Работает обычный Vite: запущен `npm run dev` или сборка лежит в `public`.
     */
    public static function usesVite(): bool
    {
        return is_file(public_path('hot')) || is_file(public_path('build/manifest.json'));
    }

    public static function ready(): bool
    {
        return self::usesVite() || self::manifest() !== [];
    }

    /**
     * Манифест залитой сборки.
     *
     * @return array<string, mixed>
     */
    public static function manifest(): array
    {
        if (self::$manifest !== null) {
            return self::$manifest;
        }

        $file = self::path().'/manifest.json';

        if (! is_file($file)) {
            return self::$manifest = [];
        }

        $decoded = json_decode((string) file_get_contents($file), true);

        return self::$manifest = is_array($decoded) ? $decoded : [];
    }

    /**
     * Теги стилей и скриптов для макета сайта.
     *
     * @param  array<int, string>|null  $entries
     */
    public static function tags(?array $entries = null): HtmlString
    {
        $entries = $entries ?: self::entries();

        if (self::usesVite()) {
            return new HtmlString((string) app(Vite::class)($entries));
        }

        $manifest = self::manifest();
        $tags = [];

        foreach ($entries as $entry) {
            $chunk = $manifest[$entry] ?? null;

            if (! is_array($chunk) || ! isset($chunk['file'])) {
                continue;
            }

            // Стили точки входа идут перед её скриптом: иначе страница мигает.
            foreach ((array) ($chunk['css'] ?? []) as $css) {
                $tags[] = self::style((string) $css);
            }

            $tags[] = str_ends_with((string) $chunk['file'], '.css')
                ? self::style((string) $chunk['file'])
                : self::script((string) $chunk['file']);
        }

        if ($tags === []) {
            // Страницу не роняем: сайт должен открыться и без сборки.
            return new HtmlString(config('app.debug')
                ? '<!-- NEXOR: сборка сайта не найдена в '.e(self::path()).' — залейте её и выполните nexor:assets -->'
                : '');
        }

        return new HtmlString(implode("\n    ", $tags));
    }

    /**
     * Адрес файла сборки.
     *
     * Собирается руками, а не через `route()`: имя файла лежит в папках, а
     * генератор маршрутов заменил бы косые черты на %2F.
     */
    public static function url(string $file): string
    {
        return url(self::PREFIX.'/'.ltrim($file, '/'));
    }

    /**
     * Полный путь к файлу сборки или null, если такого файла в ней нет.
     */
    public static function file(string $path): ?string
    {
        $base = realpath(self::path());
        $file = realpath(self::path().'/'.$path);

        if ($base === false || $file === false || ! is_file($file)) {
            return null;
        }

        // Только внутри сборки: `../` мимо.
        return str_starts_with($file, $base.DIRECTORY_SEPARATOR) ? $file : null;
    }

    /**
     * Переносит сборку в каталог, откуда она отдаётся.
     *
     * Источник — папка `public/build` или zip с ней: заливать по FTP один
     * архив проще, чем сотню файлов.
     *
     * @return int Сколько файлов легло на место
     *
     * @throws RuntimeException
     */
    public static function import(string $source): int
    {
        $source = rtrim($source, '/\\');

        if (is_dir($source)) {
            return self::replace($source);
        }

        if (! is_file($source)) {
            throw new RuntimeException("Сборка не найдена: {$source}");
        }

        if (! class_exists(ZipArchive::class)) {
            throw new RuntimeException('Расширение zip недоступно — залейте сборку папкой.');
        }

        $zip = new ZipArchive;

        if ($zip->open($source) !== true) {
            throw new RuntimeException("Архив не открывается: {$source}");
        }

        $temporary = storage_path('app/nexor-assets-'.uniqid());
        $zip->extractTo($temporary);
        $zip->close();

        // Внутри архива обычно одна папка `build` — берём её, а не обёртку.
        $inner = self::root($temporary);
        $count = self::replace($inner);

        File::deleteDirectory($temporary);

        return $count;
    }

    public static function flush(): void
    {
        self::$manifest = null;
    }

    /**
     * Каталог с манифестом внутри распакованного архива.
     */
    protected static function root(string $directory): string
    {
        if (is_file($directory.'/manifest.json') || is_file($directory.'/.vite/manifest.json')) {
            return $directory;
        }

        foreach (File::directories($directory) as $nested) {
            if (is_file($nested.'/manifest.json') || is_file($nested.'/.vite/manifest.json')) {
                return $nested;
            }
        }

        return $directory;
    }

    /**
     * @throws RuntimeException
     */
    protected static function replace(string $source): int
    {
        // Vite кладёт манифест в .vite/manifest.json — рядом с файлами он
        // привычнее, и маршрут его не отдаёт.
        $manifest = is_file($source.'/manifest.json')
            ? $source.'/manifest.json'
            : $source.'/.vite/manifest.json';

        if (! is_file($manifest)) {
            throw new RuntimeException("В сборке нет manifest.json: {$source}");
        }

        $target = self::path();

        File::deleteDirectory($target);
        File::ensureDirectoryExists($target);
        File::copyDirectory($source, $target);
        File::copy($manifest, $target.'/manifest.json');

        self::flush();

        return count(File::allFiles($target));
    }

    protected static function style(string $file): string
    {
        return '<link rel="stylesheet" href="'.e(self::url($file)).'">';
    }

    protected static function script(string $file): string
    {
        return '<script type="module" src="'.e(self::url($file)).'"></script>';
    }
}
