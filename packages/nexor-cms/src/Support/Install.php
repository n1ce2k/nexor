<?php

namespace Nexor\Cms\Support;

use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Str;
use Nexor\Cms\Models\LicenseBinding;
use Nexor\Cms\Support\License\Host;
use Throwable;

/**
 * Привязка установки к домену.
 *
 * Запись появляется при установке или при первом успешном запуске сайта и
 * дальше отвечает на один вопрос: эта база — от этого сайта? Ключ отвечает
 * только за свой домен, а базу можно перенести и вовсе без ключа.
 *
 * Класс зовут и до миграций (из `nexor:install`), поэтому любое обращение к
 * базе прикрыто: нет таблицы — значит привязки ещё нет.
 */
class Install
{
    public const CACHE_KEY = 'nexor.install';

    protected static ?LicenseBinding $binding = null;

    protected static bool $loaded = false;

    /**
     * Привязка сайта или null, если её ещё нет.
     */
    public static function binding(): ?LicenseBinding
    {
        if (self::$loaded) {
            return self::$binding;
        }

        self::$loaded = true;

        try {
            // Привязка читается на каждой странице, поэтому лежит в кеше —
            // как и настройки. Сбрасывает его только сама привязка.
            $stored = Cache::rememberForever(
                self::CACHE_KEY,
                fn () => LicenseBinding::query()->orderBy('id')->first()?->only(['install_id', 'host', 'key_serial']) ?: [],
            );

            self::$binding = $stored === [] ? null : new LicenseBinding($stored);
        } catch (Throwable) {
            // Базы или таблицы ещё нет — установка не привязана.
            self::$binding = null;
        }

        return self::$binding;
    }

    public static function id(): ?string
    {
        return self::binding()?->install_id;
    }

    public static function host(): ?string
    {
        return self::binding()?->host;
    }

    public static function serial(): ?int
    {
        return self::binding()?->key_serial;
    }

    /**
     * Домен совпадает с тем, к которому привязана база.
     *
     * Непривязанная установка подходит любому домену: это первый запуск.
     */
    public static function matches(?string $host = null): bool
    {
        $binding = self::binding();

        return $binding === null || Host::same($binding->host, $host ?? Host::current());
    }

    /**
     * Закрепляет установку при первом успешном запуске.
     *
     * Номер ключа обновляется и потом: продление выдаёт новый ключ на тот же
     * домен, и блокировать из-за него сайт было бы дико.
     */
    public static function remember(?string $host = null, ?int $serial = null): ?LicenseBinding
    {
        $host = Host::normalise($host ?? Host::current());

        if ($host === '') {
            return null;
        }

        try {
            $binding = self::binding();

            if ($binding === null) {
                return self::write($host, $serial);
            }

            if ($serial !== null && $binding->key_serial !== $serial && Host::same($binding->host, $host)) {
                return self::write($host, $serial);
            }

            return $binding;
        } catch (Throwable) {
            // Запись не обязательна: не получилось — проверим на следующем запросе.
            return null;
        }
    }

    /**
     * Переносит привязку на текущий домен. Зовётся при установке и командой
     * `nexor:license:bind` — сам по себе перенос не происходит никогда.
     */
    public static function bind(?string $host = null, ?int $serial = null): ?LicenseBinding
    {
        $host = Host::normalise($host ?? Host::current());

        if ($host === '') {
            return null;
        }

        return self::write($host, $serial);
    }

    public static function flush(): void
    {
        self::$binding = null;
        self::$loaded = false;

        Cache::forget(self::CACHE_KEY);
    }

    protected static function write(string $host, ?int $serial): LicenseBinding
    {
        // Запись берётся из базы, а не из кеша: в кеше лежит только её слепок.
        $binding = LicenseBinding::query()->orderBy('id')->first()
            ?? new LicenseBinding(['install_id' => (string) Str::uuid()]);

        $binding->forceFill(['host' => $host, 'key_serial' => $serial])->save();

        self::flush();

        return $binding;
    }
}
