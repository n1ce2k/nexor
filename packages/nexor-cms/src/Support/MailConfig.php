<?php

namespace Nexor\Cms\Support;

use Illuminate\Support\Facades\Config;
use Nexor\Cms\Models\Setting;

/**
 * Applies the SMTP settings stored in the panel over the app's mail config.
 *
 * Runs on every request through the service provider, so changing the mailer in
 * the admin takes effect without touching `.env`.
 */
class MailConfig
{
    public static function apply(): void
    {
        if (! Setting::get('mail.enabled', false)) {
            return;
        }

        $host = Setting::get('mail.host');

        if (blank($host)) {
            return;
        }

        Config::set('mail.default', 'nexor_smtp');

        Config::set('mail.mailers.nexor_smtp', [
            'transport' => 'smtp',
            'host' => $host,
            'port' => (int) (Setting::get('mail.port') ?: 587),
            'username' => Setting::get('mail.username') ?: null,
            'password' => Setting::get('mail.password') ?: null,
            'encryption' => self::encryption(),
            'timeout' => 15,
            'local_domain' => parse_url((string) config('app.url'), PHP_URL_HOST) ?: null,
        ]);

        if ($from = Setting::get('mail.from_address')) {
            Config::set('mail.from.address', $from);
        }

        if ($name = Setting::get('mail.from_name')) {
            Config::set('mail.from.name', $name);
        }
    }

    /**
     * `none` is stored as a real choice, but Laravel expects null for it.
     */
    protected static function encryption(): ?string
    {
        $value = Setting::get('mail.encryption', 'tls');

        return in_array($value, ['ssl', 'tls'], true) ? $value : null;
    }

    /**
     * Definitions of the SMTP settings, seeded on install.
     *
     * @return array<int, array<string, mixed>>
     */
    public static function definitions(): array
    {
        return [
            ['key' => 'mail.enabled', 'type' => 'boolean', 'name' => 'Использовать свой SMTP', 'hint' => 'Пока выключено, письма уходят через настройки .env.', 'sort' => 100, 'value' => '0'],
            ['key' => 'mail.host', 'type' => 'string', 'name' => 'SMTP-сервер', 'hint' => 'Например: smtp.yandex.ru', 'sort' => 110, 'value' => ''],
            ['key' => 'mail.port', 'type' => 'integer', 'name' => 'Порт', 'hint' => '587 для TLS, 465 для SSL, 25 без шифрования.', 'sort' => 120, 'value' => '587'],
            ['key' => 'mail.encryption', 'type' => 'select', 'name' => 'Шифрование', 'sort' => 130, 'value' => 'tls',
                'options' => [
                    ['value' => 'tls', 'label' => 'TLS'],
                    ['value' => 'ssl', 'label' => 'SSL'],
                    ['value' => 'none', 'label' => 'Без шифрования'],
                ]],
            ['key' => 'mail.username', 'type' => 'string', 'name' => 'Логин', 'sort' => 140, 'value' => ''],
            ['key' => 'mail.password', 'type' => 'password', 'name' => 'Пароль', 'hint' => 'Хранится в зашифрованном виде.', 'sort' => 150, 'value' => ''],
            ['key' => 'mail.from_address', 'type' => 'string', 'name' => 'Отправитель: e-mail', 'sort' => 160, 'value' => ''],
            ['key' => 'mail.from_name', 'type' => 'string', 'name' => 'Отправитель: имя', 'sort' => 170, 'value' => ''],
        ];
    }
}
