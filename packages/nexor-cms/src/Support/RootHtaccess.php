<?php

namespace Nexor\Cms\Support;

use Illuminate\Support\Facades\File;

/**
 * Корневой .htaccess для хостинга, где сайт открывается из папки проекта.
 *
 * На виртуальном хостинге корневую папку сайта часто нельзя направить в
 * `public`, и запросы уводят туда правилом в .htaccess. Наивное правило из
 * двух строк работает, пока сервер не сделает редирект уже изнутри `public`:
 * адрес он строит из подменённого пути, браузер попадает на `/public/...`, и
 * Laravel начинает дописывать `/public` во все ссылки.
 *
 * Этот файл возвращает такие адреса на чистые и закрывает `.env`, если
 * mod_rewrite вдруг выключат. Когда корень сайта смотрит прямо в `public`,
 * файл лежит выше него и ни на что не влияет.
 */
class RootHtaccess
{
    /** По этой строке узнаём свой файл и обновляем его, не спрашивая. */
    public const MARKER = '# NEXOR: запросы уходят в public';

    /** Файла нет. */
    public const MISSING = 'missing';

    /** Наш файл, актуальный. */
    public const CURRENT = 'current';

    /** Наш файл прежней версии. */
    public const OUTDATED = 'outdated';

    /** Чужой файл, который тоже уводит запросы в public. */
    public const NAIVE = 'naive';

    /** Чужой файл про что-то другое — его не трогаем. */
    public const FOREIGN = 'foreign';

    protected static ?string $path = null;

    public static function path(): string
    {
        return self::$path ?? base_path('.htaccess');
    }

    /**
     * Подменить путь — для тестов: настоящий корень проекта они не трогают.
     */
    public static function usePath(?string $path): void
    {
        self::$path = $path;
    }

    /**
     * Имя публичной папки: обычно `public`, но приложение могло её переименовать.
     */
    public static function folder(): string
    {
        return basename(public_path());
    }

    public static function status(): string
    {
        if (! is_file(self::path())) {
            return self::MISSING;
        }

        $content = (string) file_get_contents(self::path());

        if (str_contains($content, self::MARKER)) {
            return self::normalise($content) === self::normalise(self::content()) ? self::CURRENT : self::OUTDATED;
        }

        return self::rewritesIntoPublic($content) ? self::NAIVE : self::FOREIGN;
    }

    /**
     * Уводит ли корневой .htaccess запросы в публичную папку.
     *
     * Только тогда `/public` в адресе — утечка, а не настоящая подпапка сайта.
     */
    public static function rewritesIntoPublic(?string $content = null): bool
    {
        $content ??= is_file(self::path()) ? (string) file_get_contents(self::path()) : '';

        return preg_match('~^\s*RewriteRule\s+\S+\s+/?'.preg_quote(self::folder(), '~').'/~mi', $content) === 1;
    }

    /**
     * Записывает файл. Чужой сначала сохраняется рядом как `.htaccess.bak`.
     */
    public static function write(): bool
    {
        $path = self::path();

        if (is_file($path) && ! str_contains((string) file_get_contents($path), self::MARKER)) {
            File::copy($path, $path.'.bak');
        }

        return File::put($path, self::content()) !== false;
    }

    public static function content(): string
    {
        $folder = self::folder();
        $marker = self::MARKER;

        return <<<HTACCESS
{$marker}
#
# Нужен, только если корневая папка сайта на хостинге — папка проекта, а не
# {$folder}. Обновляется командой: php artisan nexor:htaccess

<IfModule mod_rewrite.c>
    RewriteEngine On

    # Адрес с /{$folder} наружу не показываем: возвращаем на чистый.
    # THE_REQUEST — то, что запросил браузер, поэтому подмена ниже сюда не попадает.
    RewriteCond %{THE_REQUEST} \\s/+{$folder}(/|\\s|\\?) [NC]
    RewriteRule ^{$folder}/?(.*)$ /$1 [L,R=301]

    RewriteRule ^(.*)$ {$folder}/$1 [L]
</IfModule>

# Если mod_rewrite выключат, блок выше молча перестанет работать, и эти файлы
# стали бы доступны по прямой ссылке.
<FilesMatch "^(\\.env.*|composer\\.(json|lock)|artisan)$">
    <IfModule mod_authz_core.c>
        Require all denied
    </IfModule>
    <IfModule !mod_authz_core.c>
        Order allow,deny
        Deny from all
    </IfModule>
</FilesMatch>

HTACCESS;
    }

    /** Переводы строк у файла с Windows другие — на сравнение это не влияет. */
    protected static function normalise(string $content): string
    {
        return trim(str_replace("\r\n", "\n", $content));
    }
}
