<?php

namespace Nexor\Cms\Support;

use Illuminate\Http\Request;
use Nexor\Cms\Models\CookieConsent;
use Nexor\Cms\Models\CookieCounter;
use Nexor\Cms\Models\Setting;

/**
 * Согласие на cookie и счётчики, которые оно разрешает.
 *
 * Правило одно: код счётчика попадает в страницу только после согласия на его
 * категорию. Поэтому решение принимается на сервере, до отдачи страницы, а не
 * скриптом, который посетитель уже загрузил.
 */
class Cookies
{
    /** Куда складываются настройки: своя группа в общих настройках сайта. */
    public const GROUP = 'cookies';

    /**
     * Настройки со значениями по умолчанию.
     *
     * Галочки аналитики и маркетинга сняты нарочно: предотмеченное согласие
     * согласием не считается.
     *
     * @return array<string, mixed>
     */
    public static function defaults(): array
    {
        return [
            'enabled' => false,
            'delay' => 1000,
            'cookie_name' => 'nexor_cookies',
            'lifetime' => 365,
            // Страницу отдаёт кеш целиком — тогда решение переносится в браузер.
            'js_mode' => false,
            'policy_url' => '',
            'cookie_policy_url' => '',
            'accept_color' => '#2563eb',
            'accept_text_color' => '#ffffff',
            'link_color' => '#2563eb',
            'keep_months' => 24,
            'metrika' => '',

            'banner_title' => 'Мы используем cookie',
            'banner_text' => 'Сайт использует cookie, чтобы работать и становиться удобнее. Продолжая, вы соглашаетесь с #POLICY#. Можно #SETTINGS#.',
            'banner_accept' => 'Принять все',
            'banner_decline' => 'Отклонить',
            'banner_settings' => 'выбрать, что разрешить',

            'modal_title' => 'Настройки cookie',
            'modal_text' => 'Часть cookie нужна сайту, чтобы работать, остальные вы разрешаете сами. Подробнее — в #COOKIES#.',
            'modal_subtitle' => 'Категории',
            'modal_accept_all' => 'Принять все',
            'modal_accept_chosen' => 'Принять выбранные',
            'modal_decline' => 'Отклонить',

            'technical_title' => 'Технические',
            'technical_text' => 'Нужны, чтобы сайт работал: вход, корзина, безопасность. Без них страница не откроется как надо, поэтому отключить их нельзя.',

            'analytics_enabled' => true,
            'analytics_checked' => false,
            'analytics_title' => 'Аналитика',
            'analytics_text' => 'Считают посещения и показывают, какие страницы читают. Помогают понять, что на сайте исправить.',

            'marketing_enabled' => true,
            'marketing_checked' => false,
            'marketing_title' => 'Маркетинг',
            'marketing_text' => 'Нужны для рекламы: показывают предложения, которые вам могут подойти, и считают их отдачу.',
        ];
    }

    /**
     * Все настройки: умолчания, поверх которых лежит сохранённое.
     *
     * @return array<string, mixed>
     */
    public static function settings(): array
    {
        $settings = self::defaults();

        foreach ($settings as $key => $default) {
            $stored = Setting::get(self::GROUP.'.'.$key);

            if ($stored === null) {
                continue;
            }

            $settings[$key] = match (true) {
                is_bool($default) => (bool) $stored,
                is_int($default) => (int) $stored,
                default => (string) $stored,
            };
        }

        return $settings;
    }

    public static function get(string $key): mixed
    {
        return self::settings()[$key] ?? null;
    }

    public static function enabled(): bool
    {
        return (bool) self::get('enabled');
    }

    /**
     * Сохраняет настройки, не трогая те, о которых не спрашивали.
     *
     * @param  array<string, mixed>  $values
     */
    public static function save(array $values): void
    {
        $defaults = self::defaults();

        foreach ($values as $key => $value) {
            if (! array_key_exists($key, $defaults)) {
                continue;
            }

            Setting::put(self::GROUP.'.'.$key, match (true) {
                is_bool($defaults[$key]) => $value ? '1' : '0',
                is_int($defaults[$key]) => (string) (int) $value,
                default => (string) $value,
            });
        }
    }

    /**
     * Выбор посетителя или null, если он ещё не отвечал.
     *
     * @return array<string, bool>|null
     */
    public static function consent(?Request $request = null): ?array
    {
        $request ??= request();
        $raw = $request->cookie(self::get('cookie_name'));

        if (! is_string($raw) || $raw === '') {
            return null;
        }

        $decoded = json_decode($raw, true);

        if (! is_array($decoded)) {
            return null;
        }

        return [
            'technical' => true,
            'analytics' => (bool) ($decoded['analytics'] ?? false),
            'marketing' => (bool) ($decoded['marketing'] ?? false),
        ];
    }

    /**
     * Разрешена ли категория прямо сейчас.
     */
    public static function allows(string $category, ?Request $request = null): bool
    {
        return (bool) (self::consent($request)[$category] ?? false);
    }

    /**
     * Коды счётчиков для места на странице.
     *
     * @return array<int, string>
     */
    public static function codes(string $placement, ?Request $request = null): array
    {
        $allowed = collect(array_keys(CookieCounter::CATEGORIES))
            ->filter(fn (string $category) => self::allows($category, $request))
            ->all();

        return self::codesFor($allowed, $placement);
    }

    /**
     * Коды перечисленных категорий — без оглядки на согласие.
     *
     * Нужны в двух местах: когда согласие уже проверено и когда коды уходят в
     * браузер для JS-режима, где решение принимает скрипт.
     *
     * @param  array<int, string>  $categories
     * @return array<int, string>
     */
    public static function codesFor(array $categories, string $placement): array
    {
        if ($categories === []) {
            return [];
        }

        $codes = CookieCounter::query()
            ->active()
            ->ordered()
            ->whereIn('category', $categories)
            ->where('placement', $placement)
            ->pluck('code')
            ->all();

        // Метрика — отдельной настройкой: вводится номер, код собирается сам.
        if ($placement === 'head' && in_array('analytics', $categories, true) && ($counter = trim((string) self::get('metrika'))) !== '') {
            array_unshift($codes, self::metrika($counter));
        }

        return $codes;
    }

    /**
     * Записывает согласие. Время ставит сервер, адрес — усечённый.
     *
     * @param  array<string, bool>  $preferences
     */
    public static function record(array $preferences, Request $request): CookieConsent
    {
        return CookieConsent::query()->create([
            'preferences' => [
                'technical' => true,
                'analytics' => (bool) ($preferences['analytics'] ?? false),
                'marketing' => (bool) ($preferences['marketing'] ?? false),
            ],
            'ip' => self::anonymise($request->ip()),
            'user_agent' => mb_substr((string) $request->userAgent(), 0, 255) ?: null,
            'user_id' => $request->user()?->id,
        ]);
    }

    /**
     * Адрес без последней части: подсеть для спора, а не человек для слежки.
     */
    public static function anonymise(?string $ip): ?string
    {
        if (! $ip) {
            return null;
        }

        if (filter_var($ip, FILTER_VALIDATE_IP, FILTER_FLAG_IPV4)) {
            $parts = explode('.', $ip);
            $parts[3] = '0';

            return implode('.', $parts);
        }

        if (filter_var($ip, FILTER_VALIDATE_IP, FILTER_FLAG_IPV6)) {
            $parts = explode(':', $ip);

            return implode(':', array_slice($parts, 0, 4)).'::';
        }

        return null;
    }

    /**
     * Код Яндекс.Метрики по номеру счётчика.
     */
    public static function metrika(string $counter): string
    {
        $counter = preg_replace('/\D/', '', $counter);

        return <<<HTML
            <script>
                (function(m,e,t,r,i,k,a){m[i]=m[i]||function(){(m[i].a=m[i].a||[]).push(arguments)};
                m[i].l=1*new Date();k=e.createElement(t),a=e.getElementsByTagName(t)[0],k.async=1,k.src=r,a.parentNode.insertBefore(k,a)})
                (window, document, "script", "https://mc.yandex.ru/metrika/tag.js", "ym");
                ym({$counter}, "init", { clickmap: true, trackLinks: true, accurateTrackBounce: true, webvisor: true });
            </script>
            <noscript><div><img src="https://mc.yandex.ru/watch/{$counter}" style="position:absolute; left:-9999px;" alt=""></div></noscript>
            HTML;
    }
}
