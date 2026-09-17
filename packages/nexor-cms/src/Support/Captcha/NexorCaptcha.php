<?php

namespace Nexor\Cms\Support\Captcha;

use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Str;

/**
 * Своя капча: код на картинке, без внешних сервисов и cookie.
 *
 * Каждый показ — отдельная задача в кэше на 10 минут. Проверить задачу можно
 * один раз: верный или нет ответ, она удаляется, и посетитель получает новую.
 * Картинку рисует GD; без него — упрощённая SVG, чтобы форма не ломалась.
 */
class NexorCaptcha implements CaptchaDriver
{
    public const TTL = 600;

    public const CHARSETS = [
        'digits' => '23456789',
        // Без похожих друг на друга 0/O/Q, 1/I/L.
        'mixed' => 'ABCDEFGHJKMNPRSTUVWXYZ23456789',
    ];

    public function settings(array $stored): array
    {
        return [
            'length' => max(4, min(8, (int) ($stored['length'] ?? 5))),
            'chars' => array_key_exists($stored['chars'] ?? null, self::CHARSETS) ? $stored['chars'] : 'mixed',
        ];
    }

    public function fromInput(array $input, array $stored): array
    {
        return $this->settings($input);
    }

    public function forPanel(array $settings): array
    {
        return [...$settings, 'gd' => self::hasGd()];
    }

    public function viewData(array $settings, ?string $challenge): array
    {
        $challenge ??= self::challenge($settings);

        return [
            'id' => $challenge,
            'image' => route('nexor.captcha.image', $challenge),
            'length' => $settings['length'],
            'digits' => $settings['chars'] === 'digits',
        ];
    }

    public function verify(array $settings, array $input, ?string $ip): ?string
    {
        $id = (string) ($input['id'] ?? '');
        $answer = mb_strtoupper(preg_replace('/\s+/', '', (string) ($input['answer'] ?? '')));

        if ($answer === '') {
            return 'Введите код с картинки.';
        }

        $stored = self::isId($id) ? Cache::pull(self::key($id)) : null;

        if (! is_array($stored)) {
            return 'Код устарел — введите новый код с картинки.';
        }

        return hash_equals($stored['code'], $answer) ? null : 'Код с картинки введён неверно — попробуйте новый.';
    }

    /**
     * Новая задача: возвращает её id для картинки и скрытого поля.
     *
     * @param  array{length: int, chars: string}  $settings
     */
    public static function challenge(array $settings): string
    {
        $chars = self::CHARSETS[$settings['chars']] ?? self::CHARSETS['mixed'];
        $code = '';

        for ($index = 0; $index < $settings['length']; $index++) {
            $code .= $chars[random_int(0, strlen($chars) - 1)];
        }

        $id = Str::random(40);

        Cache::put(self::key($id), ['code' => $code], self::TTL);

        return $id;
    }

    public static function isId(string $id): bool
    {
        return (bool) preg_match('/^[A-Za-z0-9]{40}$/', $id);
    }

    /**
     * Картинка задачи; null — задачи нет или она истекла.
     *
     * @return array{body: string, type: string}|null
     */
    public static function image(string $id): ?array
    {
        $stored = self::isId($id) ? Cache::get(self::key($id)) : null;

        if (! is_array($stored)) {
            return null;
        }

        return self::hasGd()
            ? ['body' => self::png($stored['code']), 'type' => 'image/png']
            : ['body' => self::svg($stored['code']), 'type' => 'image/svg+xml'];
    }

    public static function hasGd(): bool
    {
        return function_exists('imagecreatetruecolor') && function_exists('imagepng');
    }

    protected static function key(string $id): string
    {
        return 'nexor-captcha:'.$id;
    }

    /**
     * Буквы встроенным шрифтом GD, каждая увеличена, повёрнута и сдвинута,
     * поверх — шум из линий и точек.
     */
    protected static function png(string $code): string
    {
        $length = strlen($code);
        $width = $length * 30 + 24;
        $height = 56;

        $image = imagecreatetruecolor($width, $height);
        imagefilledrectangle($image, 0, 0, $width, $height, imagecolorallocate($image, random_int(235, 250), random_int(238, 250), random_int(240, 255)));

        for ($line = 0; $line < 5; $line++) {
            imagesetthickness($image, random_int(1, 2));
            imageline(
                $image,
                random_int(0, $width), random_int(0, $height),
                random_int(0, $width), random_int(0, $height),
                imagecolorallocate($image, random_int(150, 210), random_int(150, 210), random_int(150, 210)),
            );
        }

        imagealphablending($image, true);

        foreach (str_split($code) as $index => $char) {
            // Буква на прозрачном фоне: альфа-канал переживает и поворот, и увеличение.
            $glyph = imagecreatetruecolor(14, 18);
            imagealphablending($glyph, false);
            imagesavealpha($glyph, true);
            $transparent = imagecolorallocatealpha($glyph, 0, 0, 0, 127);
            imagefill($glyph, 0, 0, $transparent);
            imagestring($glyph, 5, 3, 1, $char, imagecolorallocate($glyph, random_int(10, 90), random_int(10, 90), random_int(40, 130)));

            $rotated = imagerotate($glyph, random_int(-25, 25), $transparent);
            imagesavealpha($rotated, true);

            $scale = random_int(24, 30) / 10;
            imagecopyresampled(
                $image,
                $rotated,
                10 + $index * 30 + random_int(-3, 3),
                random_int(0, 6),
                0,
                0,
                (int) (imagesx($rotated) * $scale),
                (int) (imagesy($rotated) * $scale),
                imagesx($rotated),
                imagesy($rotated),
            );

            imagedestroy($glyph);
            imagedestroy($rotated);
        }

        for ($dot = 0; $dot < $width * 2; $dot++) {
            imagesetpixel($image, random_int(0, $width - 1), random_int(0, $height - 1), imagecolorallocate($image, random_int(90, 200), random_int(90, 200), random_int(90, 200)));
        }

        imagesetthickness($image, 2);
        imagearc($image, random_int(0, $width), random_int(0, $height), $width, random_int(40, 90), random_int(0, 90), random_int(180, 360), imagecolorallocate($image, 90, 90, 140));

        ob_start();
        imagepng($image);
        imagedestroy($image);

        return (string) ob_get_clean();
    }

    protected static function svg(string $code): string
    {
        $width = strlen($code) * 30 + 24;
        $letters = '';

        foreach (str_split($code) as $index => $char) {
            $x = 14 + $index * 30;
            $letters .= '<text x="'.$x.'" y="'.random_int(36, 44).'" transform="rotate('.random_int(-20, 20).' '.$x.' 30)">'.$char.'</text>';
        }

        return '<svg xmlns="http://www.w3.org/2000/svg" width="'.$width.'" height="56" viewBox="0 0 '.$width.' 56">'
            .'<rect width="100%" height="100%" fill="#eef1f6"/>'
            .'<path d="M0 '.random_int(10, 46).' C '.($width / 3).' 0, '.($width / 2).' 56, '.$width.' '.random_int(10, 46).'" stroke="#8a93a8" fill="none" stroke-width="2"/>'
            .'<g font-family="monospace" font-size="30" font-weight="bold" fill="#2a3150">'.$letters.'</g></svg>';
    }
}
