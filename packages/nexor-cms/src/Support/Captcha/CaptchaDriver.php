<?php

namespace Nexor\Cms\Support\Captcha;

/**
 * Одна капча вкладки «Защита».
 *
 * Ответ посетителя приходит в `$input`: `token` — у внешних сервисов,
 * `id` и `answer` — у Nexor Captcha.
 */
interface CaptchaDriver
{
    /**
     * Настройки с умолчаниями; секреты — зашифрованные.
     *
     * @param  array<string, mixed>  $stored
     * @return array<string, mixed>
     */
    public function settings(array $stored): array;

    /**
     * Что сохранить из панели.
     *
     * @param  array<string, mixed>  $input
     * @param  array<string, mixed>  $stored
     * @return array<string, mixed>
     */
    public function fromInput(array $input, array $stored): array;

    /**
     * Настройки для панели: секреты маской.
     *
     * @param  array<string, mixed>  $settings
     * @return array<string, mixed>
     */
    public function forPanel(array $settings): array;

    /**
     * Данные для шаблона на сайте — только то, что можно показать посетителю.
     *
     * @param  array<string, mixed>  $settings
     * @return array<string, mixed>
     */
    public function viewData(array $settings, ?string $challenge): array;

    /**
     * null — проверка пройдена, иначе текст ошибки для посетителя.
     *
     * @param  array<string, mixed>  $settings
     * @param  array{token?: string|null, id?: string|null, answer?: string|null}  $input
     */
    public function verify(array $settings, array $input, ?string $ip): ?string;
}
