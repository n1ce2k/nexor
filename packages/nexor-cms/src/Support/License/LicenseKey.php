<?php

namespace Nexor\Cms\Support\License;

use Nexor\Cms\Enums\License;
use RuntimeException;

/**
 * Лицензионный ключ NEXOR.
 *
 * Ключ — это `nxr-` и сплошная строка из латиницы и цифр, внутри которой лежат
 * редакция, срок, номер и домен, подписанные приватным ключом издателя.
 * Проверяется он офлайн публичным ключом из конфига, поэтому сервер для этого
 * не нужен; подделать ключ, не зная приватного, нельзя.
 *
 * Домен хранится открытым текстом, а не хешем: панель показывает человеку, на
 * какой домен выдан ключ, а хеш пришлось бы брать длинным, чтобы его нельзя
 * было подобрать поддоменом.
 */
class LicenseKey
{
    public const PREFIX = 'nxr-';

    /** Версия формата: 1 — без домена, 2 — с доменом. */
    public const VERSION = 2;

    /** Голова полезной части: версия, редакция, срок, номер. */
    protected const HEAD = 10;

    /** @var array<int, string> */
    protected const EDITIONS = [1 => 'lite', 2 => 'standart', 3 => 'pro'];

    public function __construct(
        public readonly License $edition,
        public readonly ?int $expiresAt,
        public readonly int $serial,
        public readonly string $key,
        public readonly ?string $host = null,
    ) {}

    /**
     * Выпускает новый ключ. Живёт у издателя — клиенту приватный ключ не нужен.
     *
     * @param  int|null  $expiresAt  Unix-время окончания; null — бессрочно
     * @param  string|null  $host  Домен сайта; null — ключ подходит любому
     *
     * @throws RuntimeException
     */
    public static function issue(
        License $edition,
        ?int $expiresAt,
        string $privateKeyPem,
        ?int $serial = null,
        ?string $host = null,
    ): self {
        $serial ??= random_int(1, 0xFFFFFFFF);
        $host = Host::normalise($host) ?: null;

        $payload = self::payload($edition, $expiresAt, $serial, $host);
        $signature = Signature::sign($payload, $privateKeyPem);

        return new self($edition, $expiresAt, $serial, self::PREFIX.Base62::encode($payload.$signature), $host);
    }

    /**
     * Разбирает ключ и проверяет подпись. null — ключ чужой или испорчен.
     *
     * Срок и домен здесь не проверяются: просроченный и чужой ключ надо
     * отличать от поддельного, чтобы панель могла сказать об этом человеку.
     */
    public static function parse(?string $key, string $publicKeyPem): ?self
    {
        $key = trim((string) $key);

        if (! str_starts_with($key, self::PREFIX)) {
            return null;
        }

        $binary = Base62::decode(substr($key, strlen(self::PREFIX)));

        if ($binary === null || strlen($binary) < self::HEAD + Signature::LENGTH) {
            return null;
        }

        $length = self::payloadLength($binary);

        if ($length === null || strlen($binary) !== $length + Signature::LENGTH) {
            return null;
        }

        $payload = substr($binary, 0, $length);
        $signature = substr($binary, $length);

        if (! Signature::verify($payload, $signature, $publicKeyPem)) {
            return null;
        }

        $parts = unpack('Cversion/Cedition/Nexpires/Nserial', $payload);

        if (! isset(self::EDITIONS[$parts['edition']])) {
            return null;
        }

        $host = substr($payload, self::HEAD + 1);

        return new self(
            License::from(self::EDITIONS[$parts['edition']]),
            $parts['expires'] === 0 ? null : $parts['expires'],
            $parts['serial'],
            $key,
            $host === '' ? null : $host,
        );
    }

    public function isExpired(): bool
    {
        return $this->expiresAt !== null && $this->expiresAt < time();
    }

    /**
     * Ключ подходит домену: выдан именно на него или вовсе без привязки.
     */
    public function matches(?string $host): bool
    {
        return $this->host === null || Host::same($this->host, $host);
    }

    /**
     * Номер ключа для человека: по нему ключ ищется в учёте издателя.
     */
    public function number(): string
    {
        return strtoupper(str_pad(dechex($this->serial), 8, '0', STR_PAD_LEFT));
    }

    /**
     * Длина полезной части: у второй версии за головой идёт домен со своей
     * длиной. Неизвестная версия — null, такой ключ разбирать нечем.
     */
    protected static function payloadLength(string $binary): ?int
    {
        return match (ord($binary[0])) {
            1 => self::HEAD,
            2 => strlen($binary) > self::HEAD ? self::HEAD + 1 + ord($binary[self::HEAD]) : null,
            default => null,
        };
    }

    /**
     * @throws RuntimeException
     */
    protected static function payload(License $edition, ?int $expiresAt, int $serial, ?string $host): string
    {
        $code = array_search($edition->value, self::EDITIONS, true);

        if ($code === false) {
            throw new RuntimeException('Неизвестная редакция: '.$edition->value);
        }

        $host = (string) $host;

        if (strlen($host) > 255) {
            throw new RuntimeException('Домен длиннее 255 байт: '.$host);
        }

        return pack('CCNNC', self::VERSION, $code, $expiresAt ?? 0, $serial, strlen($host)).$host;
    }
}
