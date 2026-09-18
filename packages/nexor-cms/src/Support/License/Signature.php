<?php

namespace Nexor\Cms\Support\License;

use RuntimeException;

/**
 * Подпись лицензионного ключа: ECDSA на кривой P-256 поверх openssl.
 *
 * Не sodium: расширение включено не везде, а openssl есть у всех — его требует
 * сам Laravel. Подпись хранится «сырыми» 64 байтами (r и s по 32), а не в DER:
 * так ключ короче, а в openssl она уходит и приходит уже развёрнутой.
 */
class Signature
{
    public const LENGTH = 64;

    /**
     * @throws RuntimeException
     */
    public static function sign(string $payload, string $privateKeyPem): string
    {
        $key = openssl_pkey_get_private($privateKeyPem);

        if ($key === false) {
            throw new RuntimeException('Приватный ключ не читается: '.openssl_error_string());
        }

        if (! openssl_sign($payload, $der, $key, OPENSSL_ALGO_SHA256)) {
            throw new RuntimeException('Не удалось подписать ключ: '.openssl_error_string());
        }

        return self::derToRaw($der);
    }

    public static function verify(string $payload, string $signature, string $publicKeyPem): bool
    {
        if (strlen($signature) !== self::LENGTH) {
            return false;
        }

        $key = openssl_pkey_get_public($publicKeyPem);

        if ($key === false) {
            return false;
        }

        return openssl_verify($payload, self::rawToDer($signature), $key, OPENSSL_ALGO_SHA256) === 1;
    }

    /**
     * Публичный ключ хранится одной строкой base64 — так он помещается в конфиг.
     */
    public static function pem(string $base64, bool $private = false): string
    {
        $label = $private ? 'EC PRIVATE KEY' : 'PUBLIC KEY';

        return "-----BEGIN {$label}-----\n".chunk_split($base64, 64, "\n")."-----END {$label}-----\n";
    }

    /**
     * SEQUENCE { INTEGER r, INTEGER s } → r и s по 32 байта.
     *
     * @throws RuntimeException
     */
    protected static function derToRaw(string $der): string
    {
        $offset = 0;

        if (($der[$offset++] ?? '') !== "\x30") {
            throw new RuntimeException('Подпись повреждена: нет SEQUENCE.');
        }

        // Длина последовательности нам не нужна: разбираем оба числа подряд.
        $length = ord($der[$offset++]);

        if ($length > 0x80) {
            $offset += $length - 0x80;
        }

        $raw = '';

        foreach ([0, 1] as $ignored) {
            if (($der[$offset++] ?? '') !== "\x02") {
                throw new RuntimeException('Подпись повреждена: нет INTEGER.');
            }

            $size = ord($der[$offset++]);
            $value = substr($der, $offset, $size);
            $offset += $size;

            // Старший бит числа openssl прикрывает нулём — он не часть значения.
            $value = ltrim($value, "\0");
            $raw .= str_pad($value, 32, "\0", STR_PAD_LEFT);
        }

        return $raw;
    }

    protected static function rawToDer(string $raw): string
    {
        $numbers = '';

        foreach ([substr($raw, 0, 32), substr($raw, 32, 32)] as $value) {
            $value = ltrim($value, "\0");

            if ($value === '' || ord($value[0]) > 0x7F) {
                $value = "\0".$value;
            }

            $numbers .= "\x02".chr(strlen($value)).$value;
        }

        return "\x30".chr(strlen($numbers)).$numbers;
    }
}
