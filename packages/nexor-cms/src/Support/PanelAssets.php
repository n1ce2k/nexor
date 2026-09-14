<?php

namespace Nexor\Cms\Support;

use Illuminate\Foundation\Vite;
use Illuminate\Support\HtmlString;
use Illuminate\Support\Str;
use RuntimeException;

/**
 * Скрипты и стили админки.
 *
 * По умолчанию (`dist`) панель приходит уже собранной внутри пакета и
 * отдаётся маршрутом `/admin/nexor-assets/...` прямо из vendor: сайту не нужен
 * Node, а `composer update` сразу приносит новую админку.
 *
 * `vite` — для разработки самой CMS в монорепо: исходники из packages/ через
 * Vite сайта, с горячей перезагрузкой. NEXOR_PANEL_ASSETS=vite.
 */
class PanelAssets
{
    /** Пакет ядра в маршруте ассетов. */
    public const CORE = 'nexor';

    /** Тема и утилиты Tailwind — общие для Vue-панели и Blade-админки. */
    protected const STYLES_ENTRY = 'resources/css/admin.css';

    protected const PANEL_ENTRY = 'resources/js/panel/main.js';

    protected const CLASSIC_ENTRIES = [self::STYLES_ENTRY, 'resources/js/admin.js'];

    /** @var array<string, array<string, mixed>> */
    protected static array $manifests = [];

    public static function mode(): string
    {
        return config('nexor.panel.assets', 'dist') === 'vite' ? 'vite' : 'dist';
    }

    /**
     * Папки со сборками: ядро и модули, у которых есть страницы в панели.
     *
     * @return array<string, string>
     */
    public static function distributions(): array
    {
        $dists = [self::CORE => self::corePath('dist')];

        foreach (Nexor::modules()->all() as $module) {
            if ($panel = $module->panelAssets()) {
                $dists[$module->code()] = $panel['dist'];
            }
        }

        return $dists;
    }

    /**
     * Vue-панель ядра.
     */
    public static function panel(): HtmlString
    {
        if (self::mode() === 'vite') {
            return app(Vite::class)([
                self::sourcePath(self::corePath(self::STYLES_ENTRY)),
                self::sourcePath(self::corePath(self::PANEL_ENTRY)),
            ]);
        }

        return new HtmlString(self::entryTags(self::STYLES_ENTRY).self::entryTags(self::PANEL_ENTRY));
    }

    /**
     * Blade-админка: стили и Alpine.
     */
    public static function classic(): HtmlString
    {
        if (self::mode() === 'vite') {
            return app(Vite::class)(array_map(
                fn (string $entry) => self::sourcePath(self::corePath($entry)),
                self::CLASSIC_ENTRIES,
            ));
        }

        return new HtmlString(implode('', array_map(self::entryTags(...), self::CLASSIC_ENTRIES)));
    }

    /**
     * Страницы модулей и бандлы приложения — после панели, но до её запуска.
     */
    public static function extensions(): HtmlString
    {
        $html = '';
        $vite = [];

        foreach (Nexor::modules()->all() as $module) {
            $panel = $module->panelAssets();

            if (! $panel || ! Nexor::feature($module->code())) {
                continue;
            }

            if (self::mode() === 'vite') {
                $vite[] = self::sourcePath($panel['source']);

                continue;
            }

            if (! empty($panel['style'])) {
                $html .= '<link rel="stylesheet" href="'.e(self::url($module->code(), $panel['style'], true)).'">';
            }

            // Модуль — после скрипта панели: он берёт Vue и UI-кит из window.Nexor.
            $html .= '<script type="module" src="'.e(self::url($module->code(), $panel['script'], true)).'"></script>';
        }

        // Бандлы самого сайта всегда собирает его Vite.
        $vite = [...$vite, ...(array) config('nexor.panel_extensions', [])];

        if ($vite !== []) {
            $html .= app(Vite::class)($vite)->toHtml();
        }

        return new HtmlString($html);
    }

    /**
     * Адрес файла сборки. У файлов без хеша в имени версия идёт query-строкой,
     * чтобы долгий кеш браузера не держал старую панель после обновления.
     */
    public static function url(string $package, string $file, bool $versioned = false): string
    {
        $url = url(Nexor::routePrefix().'/nexor-assets/'.$package.'/'.ltrim($file, '/'));

        if (! $versioned) {
            return $url;
        }

        $path = (self::distributions()[$package] ?? '').'/'.$file;

        return $url.'?v='.(is_file($path) ? substr(md5((string) filemtime($path).filesize($path)), 0, 10) : Nexor::VERSION);
    }

    protected static function entryTags(string $entry): string
    {
        $chunk = self::manifest()[$entry] ?? throw new RuntimeException(
            "В сборке панели нет «{$entry}». Соберите её: npm run build:packages.",
        );

        $html = '';

        foreach ($chunk['css'] ?? [] as $css) {
            $html .= '<link rel="stylesheet" href="'.e(self::url(self::CORE, $css)).'">';
        }

        if (str_ends_with($chunk['file'], '.css')) {
            return $html.'<link rel="stylesheet" href="'.e(self::url(self::CORE, $chunk['file'])).'">';
        }

        return $html.'<script type="module" src="'.e(self::url(self::CORE, $chunk['file'])).'"></script>';
    }

    /**
     * @return array<string, array<string, mixed>>
     */
    protected static function manifest(): array
    {
        $path = self::corePath('dist/manifest.json');

        if (! isset(self::$manifests[$path])) {
            if (! is_file($path)) {
                throw new RuntimeException('Сборка панели не найдена: '.$path.'. Соберите её: npm run build:packages.');
            }

            self::$manifests[$path] = json_decode((string) file_get_contents($path), true) ?: [];
        }

        return self::$manifests[$path];
    }

    protected static function corePath(string $relative = ''): string
    {
        return dirname(__DIR__, 2).($relative !== '' ? '/'.$relative : '');
    }

    /**
     * Путь для Vite сайта — относительно корня проекта.
     */
    protected static function sourcePath(string $absolute): string
    {
        $absolute = str_replace('\\', '/', $absolute);
        $base = str_replace('\\', '/', base_path()).'/';

        return Str::startsWith($absolute, $base) ? Str::after($absolute, $base) : $absolute;
    }
}
