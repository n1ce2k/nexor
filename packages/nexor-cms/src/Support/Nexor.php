<?php

namespace Nexor\Cms\Support;

use Illuminate\Database\Eloquent\Model;
use Nexor\Cms\Contracts\NexorUser;
use Nexor\Cms\Enums\License;
use Nexor\Cms\Enums\PropertyType;
use Nexor\Cms\Support\Modules\ModuleManager;

/**
 * Entry points into the package's configuration.
 *
 * The CMS never names the application's user model directly — the host app
 * declares it in `config/nexor.php`, and everything here resolves through that.
 */
class Nexor
{
    /**
     * Version of the CMS itself.
     *
     * The release is the git tag; this constant is what the running code can
     * print — in the panel, in a bug report, in an upgrade check.
     */
    public const VERSION = '0.2.12';

    /**
     * @return class-string<Model&NexorUser>
     */
    public static function userModel(): string
    {
        return config('nexor.user_model', 'App\\Models\\User');
    }

    /**
     * @return Model&NexorUser
     */
    public static function newUser(): Model
    {
        $class = self::userModel();

        return new $class;
    }

    /**
     * Модули, лицензия и функции сайта.
     */
    public static function modules(): ModuleManager
    {
        return app(ModuleManager::class);
    }

    public static function license(): License
    {
        return self::modules()->license();
    }

    /**
     * Доступна ли функция (`catalog.offers`) или модуль (`shop`).
     */
    public static function feature(string $code): bool
    {
        return self::modules()->allows($code);
    }

    public static function disk(): string
    {
        return config('nexor.storage.disk', 'public');
    }

    public static function directory(string $key): string
    {
        return config("nexor.storage.directories.{$key}", $key);
    }

    /**
     * Инициалы для кружка аватара.
     *
     * Считает их пакет, а не модель пользователя: она живёт в приложении, и
     * такого аксессора там может не быть.
     */
    public static function initials(?string $name): string
    {
        $words = preg_split('/\s+/u', trim((string) $name)) ?: [];
        $letters = array_map(static fn (string $word): string => mb_substr($word, 0, 1), array_slice($words, 0, 2));

        return mb_strtoupper(implode('', $letters)) ?: '?';
    }

    public static function brand(string $key = 'name'): string
    {
        return config("nexor.brand.{$key}", $key === 'initial' ? 'N' : 'NEXOR');
    }

    public static function perPage(string $key = 'default'): int
    {
        return (int) config("nexor.per_page.{$key}", config('nexor.per_page.default', 20));
    }

    public static function routePrefix(): string
    {
        return trim((string) config('nexor.route.prefix', 'admin'), '/');
    }

    /**
     * Whether the Vue panel owns the bare /admin URL.
     */
    public static function vueIsDefault(): bool
    {
        return config('nexor.panel.default', 'vue') === 'vue';
    }

    /**
     * Base path the SPA router is mounted on.
     */
    public static function panelBase(): string
    {
        $prefix = self::vueIsDefault() ? '' : trim((string) config('nexor.panel.path', 'vue'), '/');

        return rtrim('/'.self::routePrefix().'/'.$prefix, '/');
    }

    /**
     * Route name of the screen a signed-in administrator lands on.
     */
    public static function homeRoute(): string
    {
        return self::vueIsDefault() ? 'admin.panel' : 'admin.dashboard';
    }

    public static function home(): string
    {
        return route(self::homeRoute());
    }

    /**
     * URL of the panel that is *not* the default, for the switch-over link.
     */
    public static function otherPanel(): string
    {
        return self::vueIsDefault()
            ? route('admin.dashboard')
            : url(self::routePrefix().'/'.trim((string) config('nexor.panel.path', 'vue'), '/'));
    }

    /**
     * Property types offered when creating an infoblock property.
     *
     * @return array<int, PropertyType>
     */
    public static function propertyTypes(): array
    {
        $allowed = config('nexor.property_types');

        if (! is_array($allowed) || $allowed === []) {
            return PropertyType::cases();
        }

        return array_values(array_filter(
            array_map(fn (string $value) => PropertyType::tryFrom($value), $allowed),
        ));
    }
}
