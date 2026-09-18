<?php

namespace Nexor\Cms\Support;

use Nexor\Cms\Enums\License;
use Nexor\Cms\Support\License\LicenseKey;
use Nexor\Cms\Support\License\Signature;

/**
 * Лицензия сайта: разбор ключа и то, что из него следует.
 *
 * Редакцию даёт ключ. Значение `NEXOR_LICENSE` осталось только для разработки
 * (окружения `local` и `testing`) — на боевом сервере оно ни на что не влияет,
 * иначе строкой в `.env` открывался бы любой уровень.
 */
class Licensing
{
    /** Состояния лицензии для панели. */
    public const OK = 'ok';

    public const NONE = 'none';

    public const INVALID = 'invalid';

    public const EXPIRED = 'expired';

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

    public static function status(): string
    {
        $raw = trim((string) config('nexor.license_key'));

        if ($raw === '') {
            return self::NONE;
        }

        $key = self::key();

        return match (true) {
            $key === null => self::INVALID,
            $key->isExpired() => self::EXPIRED,
            default => self::OK,
        };
    }

    /**
     * Что показать в панели.
     *
     * @return array{status: string, edition: string, expires_at: string|null, number: string|null, message: string|null}
     */
    public static function state(): array
    {
        $status = self::status();
        $key = self::key();

        return [
            'status' => $status,
            'edition' => self::edition()->value,
            'edition_label' => self::edition()->label(),
            'expires_at' => $key?->expiresAt ? date('c', $key->expiresAt) : null,
            'number' => $key?->number(),
            'message' => match ($status) {
                self::NONE => 'Лицензионный ключ не введён — доступна редакция '.self::fallback()->label().'.',
                self::INVALID => 'Лицензионный ключ не прошёл проверку. Проверьте, что он скопирован целиком.',
                self::EXPIRED => 'Срок лицензии истёк — доступна редакция '.self::fallback()->label().'.',
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
    }
}
