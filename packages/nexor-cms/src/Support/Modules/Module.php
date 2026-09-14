<?php

namespace Nexor\Cms\Support\Modules;

use Nexor\Cms\Enums\License;

/**
 * Описание модуля NEXOR.
 *
 * Модуль — отдельный composer-пакет со своим провайдером. В `register()`
 * провайдера он объявляет себя через `Nexor::modules()->register(...)`, и ядро
 * дальше само подключает его маршруты, права, функции и показывает его на
 * странице «Модули». Выключенный модуль прячет маршруты и меню, но его данные
 * остаются в базе.
 */
abstract class Module
{
    /**
     * Символьный код, он же код функции «модуль включён»: `nexor.feature:shop`.
     */
    abstract public function code(): string;

    abstract public function name(): string;

    public function description(): string
    {
        return '';
    }

    public function version(): string
    {
        return '0.0.0';
    }

    /**
     * С какого уровня лицензии модуль вообще работает.
     */
    public function license(): License
    {
        return License::Lite;
    }

    /**
     * Функции модуля со своими уровнями лицензии.
     *
     * Функция доступна, только если включён сам модуль и лицензия её покрывает.
     *
     * @return array<string, array{label: string, license: License}>
     */
    public function features(): array
    {
        return [];
    }

    /**
     * Группы прав в том же виде, что `Permissions::definitions()`.
     *
     * @return array<string, array{label: string, sort: int, items: array<string, string>}>
     */
    public function permissions(): array
    {
        return [];
    }

    /**
     * Файл маршрутов JSON API панели: префикс `/admin/api`, имена `admin.api.`.
     */
    public function apiRoutes(): ?string
    {
        return null;
    }

    /**
     * Файл маршрутов публичной части, под middleware `web`.
     */
    public function webRoutes(): ?string
    {
        return null;
    }

    /**
     * Настройки модуля по умолчанию — поверх них ложится сохранённое.
     *
     * @return array<string, mixed>
     */
    public function defaultSettings(): array
    {
        return [];
    }
}
