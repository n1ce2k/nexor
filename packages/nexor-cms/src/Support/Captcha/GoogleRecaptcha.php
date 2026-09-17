<?php

namespace Nexor\Cms\Support\Captcha;

use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Log;
use Nexor\Cms\Support\Secrets;
use Throwable;

/**
 * Google reCAPTCHA: v2 (галочка «Я не робот») или v3 (невидимая, по баллу).
 *
 * Сервис недоступен — заявка проходит, в лог уходит предупреждение.
 */
class GoogleRecaptcha implements CaptchaDriver
{
    public const VERIFY_URL = 'https://www.google.com/recaptcha/api/siteverify';

    /** Действие v3 — одно на все формы, его же проверяем на сервере. */
    public const ACTION = 'nexor_form';

    public function settings(array $stored): array
    {
        return [
            'site_key' => (string) ($stored['site_key'] ?? ''),
            'secret_key' => $stored['secret_key'] ?? null,
            'version' => in_array($stored['version'] ?? null, ['v2', 'v3'], true) ? $stored['version'] : 'v2',
            'min_score' => (float) ($stored['min_score'] ?? 0.5),
        ];
    }

    public function fromInput(array $input, array $stored): array
    {
        return [
            'site_key' => trim((string) ($input['site_key'] ?? '')),
            'secret_key' => Secrets::fromInput($input['secret_key'] ?? null, $stored['secret_key'] ?? null),
            'version' => ($input['version'] ?? 'v2') === 'v3' ? 'v3' : 'v2',
            'min_score' => round(min(1, max(0, (float) ($input['min_score'] ?? 0.5))), 2),
        ];
    }

    public function forPanel(array $settings): array
    {
        return [...$settings, 'secret_key' => Secrets::mask($settings['secret_key'])];
    }

    public function viewData(array $settings, ?string $challenge): array
    {
        return [
            'key' => $settings['site_key'],
            'version' => $settings['version'],
            'action' => self::ACTION,
        ];
    }

    public function verify(array $settings, array $input, ?string $ip): ?string
    {
        $token = trim((string) ($input['token'] ?? ''));

        if ($token === '') {
            return 'Подтвердите, что вы не робот.';
        }

        $secret = Secrets::decrypt($settings['secret_key']);

        if ($secret === null) {
            Log::warning('Google reCAPTCHA: не задан секретный ключ — проверка пропущена.');

            return null;
        }

        try {
            $response = Http::timeout(5)->asForm()->post(self::VERIFY_URL, array_filter([
                'secret' => $secret,
                'response' => $token,
                'remoteip' => $ip,
            ]));
        } catch (Throwable $exception) {
            Log::warning('Google reCAPTCHA недоступна, заявка пропущена без проверки: '.$exception->getMessage());

            return null;
        }

        if (! $response->successful()) {
            Log::warning('Google reCAPTCHA ответила '.$response->status().', заявка пропущена без проверки.');

            return null;
        }

        $passed = (bool) $response->json('success');

        if ($passed && $settings['version'] === 'v3') {
            $passed = (float) $response->json('score', 0) >= $settings['min_score']
                && in_array($response->json('action'), [null, self::ACTION], true);
        }

        if ($passed) {
            return null;
        }

        Log::info('Google reCAPTCHA отклонила отправку: '.json_encode($response->json(), JSON_UNESCAPED_UNICODE));

        return 'Проверка «я не робот» не пройдена — попробуйте ещё раз.';
    }
}
