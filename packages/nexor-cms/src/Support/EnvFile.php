<?php

namespace Nexor\Cms\Support;

/**
 * Правка `.env` приложения: одна строка, не трогая остальные.
 */
class EnvFile
{
    /**
     * Ставит значение переменной; если её ещё нет — дописывает в конец.
     *
     * false — файла нет или он недоступен для записи.
     */
    public static function set(string $name, string $value, ?string $path = null): bool
    {
        $path ??= app()->environmentFilePath();

        if (! is_file($path) || ! is_writable($path)) {
            return false;
        }

        $contents = (string) file_get_contents($path);
        $eol = str_contains($contents, "\r\n") ? "\r\n" : "\n";
        $line = $name.'='.self::quote($value);
        $pattern = '/^'.preg_quote($name, '/').'=.*$/m';

        $contents = preg_match($pattern, $contents) === 1
            ? preg_replace($pattern, $line, $contents, 1)
            : rtrim($contents, "\r\n").$eol.$line.$eol;

        return file_put_contents($path, $contents) !== false;
    }

    /**
     * Значения с пробелами и решётками уходят в кавычки, остальные — как есть.
     */
    protected static function quote(string $value): string
    {
        return preg_match('/^[A-Za-z0-9_\-.:\/\\\\]*$/', $value) === 1 ? $value : '"'.addcslashes($value, '"\\').'"';
    }
}
