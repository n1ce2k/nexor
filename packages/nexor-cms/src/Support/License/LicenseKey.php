<?php

namespace Nexor\Cms\Support\License;

use Nexor\Cms\Enums\License;
use RuntimeException;

/**
 * Лицензионный ключ NEXOR.
 *
 * Ключ — это `nxr-` и сплошная строка из латиницы и цифр, внутри которой лежат
 * редакция, срок и номер, подписанные приватным ключом издателя. Проверяется
 * он офлайн публичным ключом из конфига, поэтому сервер для этого не нужен;
 * подделать ключ, не зная приватного, нельзя.
 */
class LicenseKey
{
    public const PREFIX = 'nxr-';

    /** Формат полезной части: версия, редакция, срок, номер. */
    public const VERSION = 1;

    /** @var array<int, string> */
    protected const EDITIONS = [1 => 'lite', 2 => 'standart', 3 => 'pro'];

    public function __construct(
        public readonly License $edition,
        public readonly ?int $expiresAt,
        public readonly int $serial,
        public readonly string $key,
    ) {}

    /**
     * Выпускает новый ключ. Живёт у издателя — клиенту приватный ключ не нужен.
     *
     * @param  int|null  $expiresAt  Unix-время окончания; null — бессрочно
     *
     * @throws RuntimeException
     */
    public static function issue(License $edition, ?int $expiresAt, string $privateKeyPem, ?int $serial = null): self
    {
        $serial ??= random_int(1, 0xFFFFFFFF);
        $payload = self::payload($edition, $expiresAt, $serial);
        $signature = Signature::sign($payload, $privateKeyPem);

        return new self($edition, $expiresAt, $serial, self::PREFIX.Base62::encode($payload.$signature));
    }

    /**
     * Разбирает ключ и проверяет подпись. null — ключ чужой или испорчен.
     *
     * Срок не проверяется здесь: просроченный ключ надо отличать от поддельного,
     * чтобы панель могла сказать об этом человеку.
     */
    public static function parse(?string $key, string $publicKeyPem): ?self
    {
        $key = trim((string) $key);

        if (! str_starts_with($key, self::PREFIX)) {
            return null;
        }

        $binary = Base62::decode(substr($key, strlen(self::PREFIX)));

        if ($binary === null || strlen($binary) !== 10 + Signature::LENGTH) {
            return null;
        }

        $payload = substr($binary, 0, 10);
        $signature = substr($binary, 10);

        if (! Signature::verify($payload, $signature, $publicKeyPem)) {
            return null;
        }

        $parts = unpack('Cversion/Cedition/Nexpires/Nserial', $payload);

        if ($parts['version'] !== self::VERSION || ! isset(self::EDITIONS[$parts['edition']])) {
            return null;
        }

        return new self(
            License::from(self::EDITIONS[$parts['edition']]),
            $parts['expires'] === 0 ? null : $parts['expires'],
            $parts['serial'],
            $key,
        );
    }

    public function isExpired(): bool
    {
        return $this->expiresAt !== null && $this->expiresAt < time();
    }

    /**
     * Номер ключа для человека: по нему ключ ищется в учёте издателя.
     */
    public function number(): string
    {
        return strtoupper(str_pad(dechex($this->serial), 8, '0', STR_PAD_LEFT));
    }

    protected static function payload(License $edition, ?int $expiresAt, int $serial): string
    {
        $code = array_search($edition->value, self::EDITIONS, true);

        if ($code === false) {
            throw new RuntimeException('Неизвестная редакция: '.$edition->value);
        }

        return pack('CCNN', self::VERSION, $code, $expiresAt ?? 0, $serial);
    }
}
