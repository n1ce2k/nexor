<?php

namespace Nexor\Cms\Support\License;

/**
 * Двоичные данные в сплошную строку из латиницы и цифр — и обратно.
 *
 * Своя реализация, потому что ключ не должен зависеть ни от gmp, ни от bcmath:
 * их нет на части хостингов. Число делится и умножается столбиком по байтам.
 */
class Base62
{
    public const ALPHABET = '0123456789ABCDEFGHIJKLMNOPQRSTUVWXYZabcdefghijklmnopqrstuvwxyz';

    public static function encode(string $binary): string
    {
        if ($binary === '') {
            return '';
        }

        $bytes = array_values(unpack('C*', $binary));
        $zeros = 0;

        while (isset($bytes[$zeros]) && $bytes[$zeros] === 0) {
            $zeros++;
        }

        $digits = array_slice($bytes, $zeros);
        $encoded = '';

        while ($digits !== []) {
            $remainder = 0;
            $next = [];

            foreach ($digits as $byte) {
                $current = ($remainder << 8) | $byte;
                $quotient = intdiv($current, 62);
                $remainder = $current % 62;

                if ($next !== [] || $quotient !== 0) {
                    $next[] = $quotient;
                }
            }

            $encoded = self::ALPHABET[$remainder].$encoded;
            $digits = $next;
        }

        return str_repeat(self::ALPHABET[0], $zeros).$encoded;
    }

    /**
     * null — в строке есть символ не из алфавита.
     */
    public static function decode(string $text): ?string
    {
        if ($text === '') {
            return '';
        }

        $zeros = 0;

        while (isset($text[$zeros]) && $text[$zeros] === self::ALPHABET[0]) {
            $zeros++;
        }

        /** @var array<int, int> $bytes Число от старшего байта к младшему */
        $bytes = [];

        foreach (str_split(substr($text, $zeros)) as $char) {
            $carry = strpos(self::ALPHABET, $char);

            if ($carry === false) {
                return null;
            }

            for ($index = count($bytes) - 1; $index >= 0; $index--) {
                $current = $bytes[$index] * 62 + $carry;
                $bytes[$index] = $current & 0xFF;
                $carry = $current >> 8;
            }

            while ($carry > 0) {
                array_unshift($bytes, $carry & 0xFF);
                $carry >>= 8;
            }
        }

        return str_repeat("\0", $zeros).implode('', array_map('chr', $bytes));
    }
}
