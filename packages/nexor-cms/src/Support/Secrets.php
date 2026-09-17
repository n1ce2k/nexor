<?php

namespace Nexor\Cms\Support;

use Illuminate\Contracts\Encryption\DecryptException;
use Illuminate\Support\Facades\Crypt;

/**
 * Секреты в настройках (токены, серверные ключи): в базе — зашифрованы
 * ключом приложения, в панель уходит только маска.
 */
class Secrets
{
    /** То, что панель видит вместо сохранённого секрета. */
    public const MASK = '••••••••';

    public static function encrypt(?string $value): ?string
    {
        $value = trim((string) $value);

        return $value === '' ? null : Crypt::encryptString($value);
    }

    public static function decrypt(?string $stored): ?string
    {
        if ($stored === null || $stored === '') {
            return null;
        }

        try {
            return Crypt::decryptString($stored);
        } catch (DecryptException) {
            // Сменили APP_KEY — старый секрет уже не прочитать, его вводят заново.
            return null;
        }
    }

    /**
     * Что сохранить из формы панели: маска — оставить как было, пусто — стереть.
     */
    public static function fromInput(?string $submitted, ?string $stored): ?string
    {
        if ($submitted === self::MASK) {
            return $stored;
        }

        return self::encrypt($submitted);
    }

    /**
     * Секрет для отправки в панель — маска, если он задан.
     */
    public static function mask(?string $stored): string
    {
        return self::decrypt($stored) !== null ? self::MASK : '';
    }

    /**
     * Значение для проверки из панели: маска или пусто — берём сохранённое.
     */
    public static function resolve(?string $submitted, ?string $stored): ?string
    {
        $submitted = trim((string) $submitted);

        return $submitted === '' || $submitted === self::MASK ? self::decrypt($stored) : $submitted;
    }
}
