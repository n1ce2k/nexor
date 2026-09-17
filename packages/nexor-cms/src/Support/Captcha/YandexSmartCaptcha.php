<?php

namespace Nexor\Cms\Support\Captcha;

use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Log;
use Nexor\Cms\Support\Secrets;
use Throwable;

/**
 * Yandex SmartCaptcha.
 *
 * Виджет на сайте кладёт токен в форму, сервер проверяет его у Яндекса.
 * Сервис недоступен — заявка проходит (так советует документация Яндекса:
 * сбой капчи не должен терять клиентов), в лог уходит предупреждение.
 */
class YandexSmartCaptcha implements CaptchaDriver
{
    public const VALIDATE_URL = 'https://smartcaptcha.cloud.yandex.ru/validate';

    public function settings(array $stored): array
    {
        return [
            'client_key' => (string) ($stored['client_key'] ?? ''),
            'server_key' => $stored['server_key'] ?? null,
            'webview' => (bool) ($stored['webview'] ?? false),
            'invisible' => (bool) ($stored['invisible'] ?? false),
            'hide_shield' => (bool) ($stored['hide_shield'] ?? false),
        ];
    }

    public function fromInput(array $input, array $stored): array
    {
        return [
            'client_key' => trim((string) ($input['client_key'] ?? '')),
            'server_key' => Secrets::fromInput($input['server_key'] ?? null, $stored['server_key'] ?? null),
            'webview' => (bool) ($input['webview'] ?? false),
            'invisible' => (bool) ($input['invisible'] ?? false),
            'hide_shield' => (bool) ($input['hide_shield'] ?? false),
        ];
    }

    public function forPanel(array $settings): array
    {
        return [...$settings, 'server_key' => Secrets::mask($settings['server_key'])];
    }

    public function viewData(array $settings, ?string $challenge): array
    {
        return [
            'key' => $settings['client_key'],
            'webview' => $settings['webview'],
            'invisible' => $settings['invisible'],
            // Плашку «обработка данных» Яндекс разрешает прятать только у невидимой капчи.
            'hideShield' => $settings['invisible'] && $settings['hide_shield'],
        ];
    }

    public function verify(array $settings, array $input, ?string $ip): ?string
    {
        $token = trim((string) ($input['token'] ?? ''));

        if ($token === '') {
            return 'Подтвердите, что вы не робот.';
        }

        $secret = Secrets::decrypt($settings['server_key']);

        if ($secret === null) {
            Log::warning('Yandex SmartCaptcha: не задан ключ сервера — проверка пропущена.');

            return null;
        }

        try {
            $response = Http::timeout(5)->asForm()->post(self::VALIDATE_URL, array_filter([
                'secret' => $secret,
                'token' => $token,
                'ip' => $ip,
            ]));
        } catch (Throwable $exception) {
            Log::warning('Yandex SmartCaptcha недоступна, заявка пропущена без проверки: '.$exception->getMessage());

            return null;
        }

        if (! $response->successful()) {
            Log::warning('Yandex SmartCaptcha ответила '.$response->status().', заявка пропущена без проверки: '.$response->body());

            return null;
        }

        if ($response->json('status') === 'ok') {
            return null;
        }

        Log::info('Yandex SmartCaptcha отклонила отправку: '.$response->json('message', 'без пояснений'));

        return 'Проверка «я не робот» не пройдена — попробуйте ещё раз.';
    }
}
