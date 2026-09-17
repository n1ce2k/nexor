<?php

namespace Nexor\Cms\Support;

use Nexor\Cms\Models\FeedbackForm;
use Nexor\Cms\Support\Captcha\CaptchaDriver;
use Nexor\Cms\Support\Captcha\GoogleRecaptcha;
use Nexor\Cms\Support\Captcha\NexorCaptcha;
use Nexor\Cms\Support\Captcha\YandexSmartCaptcha;

/**
 * Вкладка «Защита» формы: одна из капч — Yandex SmartCaptcha, Google
 * reCAPTCHA или своя Nexor Captcha. Включить можно только одну.
 *
 * Хранится в `feedback_forms.protection`:
 * `{ captcha: none|yandex|google|nexor, yandex: {...}, google: {...}, nexor: {...} }` —
 * настройки выключенных капч сохраняются, чтобы переключение их не стирало.
 */
class FormCaptcha
{
    public const NONE = 'none';

    /** Ошибка капчи в сообщениях валидации. */
    public const ERROR_KEY = 'captcha';

    /**
     * @return array<string, class-string<CaptchaDriver>>
     */
    public static function drivers(): array
    {
        return [
            'yandex' => YandexSmartCaptcha::class,
            'google' => GoogleRecaptcha::class,
            'nexor' => NexorCaptcha::class,
        ];
    }

    public static function driver(string $name): CaptchaDriver
    {
        return app(self::drivers()[$name]);
    }

    /**
     * Включённая капча формы или null.
     */
    public static function active(FeedbackForm $form): ?string
    {
        $name = $form->protection['captcha'] ?? self::NONE;

        return array_key_exists($name, self::drivers()) ? $name : null;
    }

    /**
     * @return array<string, mixed>
     */
    public static function settings(FeedbackForm $form, string $name): array
    {
        return self::driver($name)->settings($form->protection[$name] ?? []);
    }

    /**
     * @return array<string, mixed>
     */
    public static function forPanel(FeedbackForm $form): array
    {
        $panel = ['captcha' => self::active($form) ?? self::NONE];

        foreach (array_keys(self::drivers()) as $name) {
            $panel[$name] = self::driver($name)->forPanel(self::settings($form, $name));
        }

        return $panel;
    }

    /**
     * @param  array<string, mixed>  $input
     * @param  array<string, mixed>|null  $stored
     * @return array<string, mixed>
     */
    public static function fromInput(array $input, ?array $stored): array
    {
        $captcha = $input['captcha'] ?? self::NONE;
        $result = ['captcha' => array_key_exists($captcha, self::drivers()) ? $captcha : self::NONE];

        foreach (array_keys(self::drivers()) as $name) {
            $result[$name] = self::driver($name)->fromInput(
                (array) ($input[$name] ?? []),
                (array) ($stored[$name] ?? []),
            );
        }

        return $result;
    }

    /**
     * Данные для шаблона на сайте или null, если капчи нет.
     *
     * @return array{provider: string, options: array<string, mixed>, errorKey: string}|null
     */
    public static function viewData(FeedbackForm $form, ?string $challenge = null): ?array
    {
        $name = self::active($form);

        if ($name === null) {
            return null;
        }

        return [
            'provider' => $name,
            'options' => self::driver($name)->viewData(self::settings($form, $name), $challenge),
            'errorKey' => self::ERROR_KEY,
            'refreshUrl' => $name === 'nexor' ? route('nexor.captcha.new', ['form' => $form->id]) : null,
        ];
    }

    /**
     * Новая задача Nexor Captcha для формы; null — у формы другая капча.
     */
    public static function challenge(FeedbackForm $form): ?string
    {
        return self::active($form) === 'nexor'
            ? NexorCaptcha::challenge(self::settings($form, 'nexor'))
            : null;
    }

    /**
     * null — проверка пройдена (или капчи нет), иначе текст ошибки.
     *
     * @param  array{token?: string|null, id?: string|null, answer?: string|null}  $input
     */
    public static function verify(FeedbackForm $form, array $input, ?string $ip): ?string
    {
        $name = self::active($form);

        return $name === null ? null : self::driver($name)->verify(self::settings($form, $name), $input, $ip);
    }
}
