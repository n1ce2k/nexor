<?php

namespace Nexor\Cms\Support;

use Illuminate\Database\Eloquent\Model;
use Nexor\Cms\Contracts\NexorUser;
use Nexor\Cms\Enums\PropertyType;

/**
 * Entry points into the package's configuration.
 *
 * The CMS never names the application's user model directly — the host app
 * declares it in `config/nexor.php`, and everything here resolves through that.
 */
class Nexor
{
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

    public static function disk(): string
    {
        return config('nexor.storage.disk', 'public');
    }

    public static function directory(string $key): string
    {
        return config("nexor.storage.directories.{$key}", $key);
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
