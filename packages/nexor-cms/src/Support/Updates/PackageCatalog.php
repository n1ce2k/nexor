<?php

namespace Nexor\Cms\Support\Updates;

use Composer\InstalledVersions;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\Http;
use Nexor\Cms\Enums\License;
use Nexor\Cms\Support\Nexor;
use Throwable;

/**
 * Что вообще умеет ставиться и обновляться: ядро и модули NEXOR.
 *
 * Список закрытый: в composer уходит только то, что перечислено здесь, —
 * имя пакета из браузера в команду не попадает никогда.
 */
class PackageCatalog
{
    /** Сколько держим ответ Packagist, чтобы не ходить туда на каждый показ. */
    public const CACHE_TTL = 3600;

    public const CACHE_KEY = 'nexor.updates.versions';

    /**
     * @return array<string, array{package: string, name: string, description: string, module: string|null, license: License, installer: string|null}>
     */
    public static function all(): array
    {
        return [
            'cms' => [
                'package' => 'n1ce2k/nexor-cms',
                'name' => 'NEXOR CMS',
                'description' => 'Ядро: инфоблоки, роли, формы, панель управления.',
                'module' => null,
                'license' => License::Lite,
                'installer' => null,
            ],
            'shop' => [
                'package' => 'n1ce2k/nexor-shop',
                'name' => 'Магазин',
                'description' => 'Корзина, заказы, доставка и оплата, промокоды.',
                'module' => 'shop',
                'license' => License::Lite,
                'installer' => 'nexor-shop:install',
            ],
            'pagebuilder' => [
                'package' => 'n1ce2k/nexor-pagebuilder',
                'name' => 'Конструктор страниц',
                'description' => 'Блочная сборка детальных страниц и страниц сайта.',
                'module' => 'pagebuilder',
                'license' => License::Lite,
                'installer' => 'nexor-pagebuilder:install',
            ],
        ];
    }

    /**
     * @return array{package: string, name: string, description: string, module: string|null, license: License, installer: string|null}|null
     */
    public static function find(string $code): ?array
    {
        return self::all()[$code] ?? null;
    }

    /**
     * Установлен ли пакет прямо сейчас.
     */
    public static function installed(string $package): bool
    {
        try {
            return InstalledVersions::isInstalled($package);
        } catch (Throwable) {
            return false;
        }
    }

    public static function version(string $package): ?string
    {
        try {
            return InstalledVersions::isInstalled($package)
                ? InstalledVersions::getPrettyVersion($package)
                : null;
        } catch (Throwable) {
            return null;
        }
    }

    /**
     * Свежая версия пакета по данным Packagist; null — узнать не вышло.
     *
     * Сеть на хостинге клиента может быть закрыта, поэтому неудача здесь —
     * обычное дело: страница просто не покажет «доступно обновление».
     */
    public static function latest(string $package, bool $fresh = false): ?string
    {
        $cached = $fresh ? null : Cache::get(self::CACHE_KEY.':'.$package);

        if ($cached !== null) {
            return $cached === '' ? null : $cached;
        }

        $version = null;

        try {
            $response = Http::timeout(8)->get("https://repo.packagist.org/p2/{$package}.json");

            if ($response->successful()) {
                foreach ((array) $response->json("packages.{$package}", []) as $release) {
                    $candidate = (string) ($release['version'] ?? '');

                    // Ветки разработки и предрелизы не предлагаем.
                    if ($candidate !== '' && ! str_contains($candidate, 'dev') && preg_match('/^v?\d+\.\d+\.\d+$/', $candidate)) {
                        $version = $candidate;
                        break;
                    }
                }
            }
        } catch (Throwable) {
            $version = null;
        }

        Cache::put(self::CACHE_KEY.':'.$package, $version ?? '', self::CACHE_TTL);

        return $version;
    }

    /**
     * Строки версий вида `v0.2.12` и `0.2.12` — одно и то же.
     */
    public static function isNewer(?string $latest, ?string $installed): bool
    {
        if ($latest === null || $installed === null || self::isBranch($installed)) {
            return false;
        }

        return version_compare(ltrim($latest, 'v'), ltrim($installed, 'v'), '>');
    }

    /**
     * Пакет подключён веткой (`dev-main`) или путём — так бывает у разработки.
     * Обновлять такое composer'ом из панели нельзя: версии там не при чём.
     */
    public static function isBranch(?string $version): bool
    {
        return $version !== null && preg_match('/^v?\d+\.\d+\.\d+/', $version) !== 1;
    }

    /**
     * Описание пакета для панели.
     *
     * @return array<string, mixed>
     */
    public static function describe(string $code, bool $fresh = false): array
    {
        $entry = self::find($code);
        $installed = self::version($entry['package']);
        $latest = self::latest($entry['package'], $fresh);

        return [
            'code' => $code,
            'package' => $entry['package'],
            'name' => $entry['name'],
            'description' => $entry['description'],
            'module' => $entry['module'],
            'license' => $entry['license']->value,
            'license_label' => $entry['license']->label(),
            'allowed' => Nexor::license()->allows($entry['license']),
            'installed' => $installed !== null,
            'version' => $installed,
            'branch' => self::isBranch($installed),
            'latest' => $latest,
            'update_available' => self::isNewer($latest, $installed),
        ];
    }
}
