<?php

namespace Nexor\Cms\Support;

use Illuminate\Support\Facades\File;
use Illuminate\Support\Str;

/**
 * Подключает шаблоны пакета к Tailwind сайта.
 *
 * Шаблоны компонентов (карточки, корзина) рисуются Tailwind-классами, а
 * Tailwind сам не смотрит в vendor/ — он в .gitignore. Без строки `@source`
 * в resources/css/app.css классы из пакета просто не попадут в CSS сайта.
 */
class TailwindSources
{
    /**
     * Дописывает `@source` на папку шаблонов, если её там ещё нет.
     *
     * @return string|null Добавленная строка или null, если добавлять не нужно или некуда
     */
    public static function add(string $viewsDirectory, ?string $css = null): ?string
    {
        $css ??= resource_path('css/app.css');

        if (! File::exists($css)) {
            return null;
        }

        $line = "@source '".self::relativeFromCss($viewsDirectory)."';";
        $content = File::get($css);

        if (str_contains($content, $line)) {
            return null;
        }

        // Рядом с остальными @source, а если их нет — сразу после импорта Tailwind.
        if (preg_match_all('/^@source .*;$/m', $content, $matches, PREG_OFFSET_CAPTURE)) {
            $last = end($matches[0]);
            $at = $last[1] + strlen($last[0]);
            $content = substr($content, 0, $at)."\n".$line.substr($content, $at);
        } elseif (preg_match('/^@import [\'"]tailwindcss[\'"];$/m', $content, $match, PREG_OFFSET_CAPTURE)) {
            $at = $match[0][1] + strlen($match[0][0]);
            $content = substr($content, 0, $at)."\n\n".$line.substr($content, $at);
        } else {
            $content = $line."\n".$content;
        }

        File::put($css, $content);

        return $line;
    }

    /**
     * Путь от resources/css до папки шаблонов пакета: vendor/n1ce2k/… на
     * обычном сайте, packages/… в монорепо CMS.
     */
    protected static function relativeFromCss(string $viewsDirectory): string
    {
        $views = str_replace('\\', '/', $viewsDirectory);
        $base = str_replace('\\', '/', base_path()).'/';

        // Пакет вне проекта (редкий случай) — ищем его копию в vendor/.
        $relative = Str::startsWith($views, $base)
            ? Str::after($views, $base)
            : 'vendor/'.Str::after($views, '/vendor/');

        return '../../'.$relative;
    }
}
