<?php

namespace Nexor\Cms\Support;

use Nexor\Cms\Enums\License;
use Nexor\Cms\Support\License\Host;
use Nexor\Cms\Support\License\LicenseKey;
use Nexor\Cms\Support\License\Signature;

/**
 * Лицензия сайта: разбор ключа и то, что из него следует.
 *
 * Редакцию даёт ключ. Значение `NEXOR_LICENSE` осталось только для разработки
 * (окружения `local` и `testing`) — на боевом сервере оно ни на что не влияет,
 * иначе строкой в `.env` открывался бы любой уровень.
 *
 * Кроме редакции ключ отвечает за домен: ключ, выданный на `site.ru`, на другом
 * домене не действует. Что делать с чужим доменом, решает `nexor.license_guard`
 * и middleware `EnsureLicensedHost` — здесь только состояние.
 */
class Licensing
{
    /** Состояния лицензии для панели. */
    public const OK = 'ok';

    public const NONE = 'none';

    public const INVALID = 'invalid';

    public const EXPIRED = 'expired';

    /** Ключ выдан на другой домен. */
    public const FOREIGN = 'foreign';

    /** База привязана к другой установке. */
    public const MOVED = 'moved';

    protected static ?LicenseKey $parsed = null;

    protected static ?string $parsedFrom = null;

    /**
     * Ключ сайта или null, если его нет или он не прошёл проверку.
     */
    public static function key(): ?LicenseKey
    {
        $raw = trim((string) config('nexor.license_key'));

        if ($raw === '') {
            self::$parsed = null;
            self::$parsedFrom = null;

            return null;
        }

        if (self::$parsedFrom !== $raw) {
            self::$parsed = LicenseKey::parse($raw, self::publicKey());
            self::$parsedFrom = $raw;
        }

        return self::$parsed;
    }

    public static function edition(): License
    {
        $key = self::key();

        if ($key !== null && ! $key->isExpired()) {
            return $key->edition;
        }

        return self::fallback();
    }

    /**
     * Состояние лицензии для домена — по умолчанию для текущего.
     */
    public static function status(?string $host = null): string
    {
        $host = Host::normalise($host ?? Host::current());
        $raw = trim((string) config('nexor.license_key'));
        $key = self::key();

        // Домен проверяется не всегда: на своей машине лицензия мешала бы работать.
        $bound = ! self::exempt($host);

        return match (true) {
            $bound && ! Install::matches($host) => self::MOVED,
            $raw === '' => self::NONE,
            $key === null => self::INVALID,
            $key->isExpired() => self::EXPIRED,
            $bound && ! $key->matches($host) => self::FOREIGN,
            default => self::OK,
        };
    }

    /**
     * Сайт запущен не там, где его лицензировали.
     */
    public static function misplaced(?string $host = null): bool
    {
        return in_array(self::status($host), [self::FOREIGN, self::MOVED], true);
    }

    /**
     * Что показать в панели.
     *
     * @return array{status: string, edition: string, expires_at: string|null, number: string|null, host: string|null, current_host: string, install: string|null, message: string|null}
     */
    public static function state(?string $host = null): array
    {
        $host = Host::normalise($host ?? Host::current());
        $status = self::status($host);
        $key = self::key();

        return [
            'status' => $status,
            'edition' => self::edition()->value,
            'edition_label' => self::edition()->label(),
            'expires_at' => $key?->expiresAt ? date('c', $key->expiresAt) : null,
            'number' => $key?->number(),
            'host' => $key?->host,
            'current_host' => $host,
            'install' => Install::id(),
            'message' => match ($status) {
                self::NONE => 'Лицензионный ключ не введён — доступна редакция '.self::fallback()->label().'.',
                self::INVALID => 'Лицензионный ключ не прошёл проверку. Проверьте, что он скопирован целиком.',
                self::EXPIRED => 'Срок лицензии истёк — доступна редакция '.self::fallback()->label().'.',
                self::FOREIGN => 'Ключ выдан на домен '.($key?->host ?? '—').', а сайт открыт на '.$host.'.',
                self::MOVED => 'Эта база привязана к сайту '.(Install::host() ?? '—').', а открыта на '.$host.'.',
                default => null,
            },
        ];
    }

    /**
     * Публичный ключ издателя: в конфиге он лежит одной строкой base64.
     */
    public static function publicKey(): string
    {
        $value = trim((string) config('nexor.license_public_key'));

        return str_contains($value, 'BEGIN') ? $value : Signature::pem($value);
    }

    /**
     * Привязку к домену не проверяем: своя машина или рабочий домен.
     */
    public static function exempt(?string $host = null): bool
    {
        return app()->environment(['local', 'testing'])
            || Host::isLocal(Host::normalise($host ?? Host::current()));
    }

    /**
     * Редакция без действующего ключа.
     */
    protected static function fallback(): License
    {
        $value = app()->environment(['local', 'testing'])
            ? strtolower(trim((string) config('nexor.license', 'lite')))
            : 'lite';

        return match ($value) {
            'pro' => License::Pro,
            'standart', 'standard' => License::Standart,
            default => License::Lite,
        };
    }

    /**
     * Сбрасывает разобранный ключ — нужно тестам и после смены ключа.
     */
    public static function flush(): void
    {
        self::$parsed = null;
        self::$parsedFrom = null;

        Install::flush();
    }
}
