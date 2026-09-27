<?php

namespace Nexor\Cms\Support\License;

use Illuminate\Support\Str;

/**
 * Домен установки: приведение к одному виду и локальные исключения.
 *
 * Ключ подписывается на домен, поэтому `site.ru`, `WWW.Site.ru:8080` и
 * `https://site.ru/каталог` обязаны давать одну и ту же строку — иначе
 * лицензия разваливалась бы от способа записи адреса.
 */
class Host
{
    /** Адреса, на которых привязка не проверяется. */
    protected const LOCAL = ['localhost', '127.0.0.1', '::1', '0.0.0.0'];

    /** Окончания рабочих доменов: разработка не должна упираться в лицензию. */
    protected const LOCAL_SUFFIXES = ['.localhost', '.test', '.local', '.internal', '.example'];

    /**
     * Домен в том виде, в каком он попадает в ключ: нижний регистр, без схемы,
     * пути, порта и `www.`. Пустая строка — домена нет.
     */
    public static function normalise(?string $value): string
    {
        $value = strtolower(trim((string) $value));

        if ($value === '') {
            return '';
        }

        if (str_contains($value, '//')) {
            $value = (string) (parse_url($value, PHP_URL_HOST) ?: $value);
        }

        // Путь, запрос и логин к домену не относятся.
        $value = (string) preg_replace('~[/?#].*$~', '', $value);
        $value = (string) preg_replace('~^.*@~', '', $value);

        // IPv6 пишется в скобках, у остальных после двоеточия идёт порт.
        if (str_starts_with($value, '[')) {
            $end = strpos($value, ']');
            $value = $end === false ? ltrim($value, '[') : substr($value, 1, $end - 1);
        } elseif (str_contains($value, ':')) {
            $value = (string) strstr($value, ':', true);
        }

        // Кириллический домен приводится к punycode: подписывать надо то, что
        // придёт из запроса, а браузер присылает уже xn--.
        if (preg_match('~[^\x20-\x7f]~', $value) === 1 && function_exists('idn_to_ascii')) {
            $value = idn_to_ascii($value) ?: $value;
        }

        $value = trim($value, '.');

        return str_starts_with($value, 'www.') ? substr($value, 4) : $value;
    }

    /**
     * Домен текущего сайта.
     *
     * В консоли запроса нет, поэтому домен берётся из `APP_URL` — команды
     * привязки должны работать и из cron.
     */
    public static function current(): string
    {
        $host = app()->runningInConsole() ? '' : self::normalise(request()->getHost());

        return $host !== '' ? $host : self::normalise(config('app.url'));
    }

    /**
     * Рабочий адрес: localhost, частный IP или домен вида `site.test`.
     */
    public static function isLocal(string $host): bool
    {
        if ($host === '' || in_array($host, self::LOCAL, true)) {
            return true;
        }

        if (Str::endsWith($host, self::LOCAL_SUFFIXES)) {
            return true;
        }

        // Публичный IP — это боевой адрес, частный и зарезервированный — свой.
        return filter_var($host, FILTER_VALIDATE_IP) !== false
            && filter_var($host, FILTER_VALIDATE_IP, FILTER_FLAG_NO_PRIV_RANGE | FILTER_FLAG_NO_RES_RANGE) === false;
    }

    public static function same(?string $one, ?string $other): bool
    {
        return self::normalise($one) === self::normalise($other);
    }
}
