<?php

namespace Nexor\Cms\Support\Modules;

use Nexor\Cms\Enums\License;
use Nexor\Cms\Models\Iblock;
use Nexor\Cms\Models\IblockElement;

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
     * Страницы модуля в панели.
     *
     * `dist` — папка готовой сборки, `script` и `style` — файлы в ней;
     * `source` — исходная точка входа для режима разработки (NEXOR_PANEL_ASSETS=vite).
     *
     * В `style` не кладите утилиты Tailwind: грузясь после стилей ядра, они
     * перебивают его адаптивные классы (`.hidden` окажется позже `lg:flex`).
     * Только собственные классы модуля.
     *
     * @return array{dist: string, script: string, style?: string, source: string}|null
     */
    public function panelAssets(): ?array
    {
        return null;
    }

    /**
     * Переключатели модуля в карточке «Параметры» формы инфоблока.
     *
     * Значения лежат в `iblocks.settings.modules.<код модуля>.<ключ>`, читаются
     * через `$iblock->moduleSetting('pagebuilder', 'detail')`.
     *
     * @return array<string, array{label: string, hint?: string, default?: bool}>
     */
    public function iblockSettings(): array
    {
        return [];
    }

    /**
     * Поля формы элемента, которые добавляет модуль.
     *
     * В раскладке формы поле называется `module:<код>.<ключ>`, в запросе
     * приходит как `modules[<код>][<ключ>]`. В панели его рисует компонент,
     * зарегистрированный через `Nexor.registerFormField('<код>.<ключ>', …)`.
     *
     * @return array<string, array{label: string, tab: string, tab_label: string}>
     */
    public function elementFields(Iblock $iblock): array
    {
        return [];
    }

    /**
     * Правила проверки своих полей элемента; ключи — без префикса `modules.<код>.`.
     *
     * @return array<string, mixed>
     */
    public function elementRules(Iblock $iblock): array
    {
        return [];
    }

    /**
     * Сохранённые значения своих полей — для формы элемента.
     *
     * @return array<string, mixed>
     */
    public function elementValues(IblockElement $element): array
    {
        return [];
    }

    /**
     * Сохраняет свои поля; вызывается после сохранения самого элемента.
     * Приходят только проверенные значения, которые были в запросе.
     *
     * @param  array<string, mixed>  $values
     */
    public function saveElement(IblockElement $element, array $values): void
    {
        //
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
