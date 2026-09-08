# NEXOR

CMS на Laravel: админ-панель с инфоблоками в стиле Битрикса, свойствами произвольных типов и собственной системой ролей. Без Filament, Orchid и сторонних пакетов прав.

Репозиторий содержит два слоя:

| | |
|---|---|
| `packages/nexor-cms` | Ядро CMS — composer-пакет `n1ce2k/nexor-cms`, namespace `Nexor\Cms` |
| остальное | Хост-приложение: модель пользователя, публичная часть сайта, демо-контент |

Приложение подключает пакет path-репозиторием и исполняет его из `vendor/n1ce2k/nexor-cms`. Документация ядра — в [packages/nexor-cms/README.md](packages/nexor-cms/README.md).

## Возможности

**Инфоблоки.** Типы инфоблоков → инфоблоки → разделы-деревья → элементы. У каждого инфоблока свой набор свойств; форма элемента строится из них в рантайме.

**16 типов свойств:** строка, текст, HTML, целое, дробное, да/нет, дата, дата и время, цвет, список, файл, изображение, привязка к элементу, к разделу, к пользователю, JSON. Флаги: множественное, обязательное, в фильтре, в поиске, колонкой в списке. Значения лежат в типизированной EAV-таблице с индексами под фильтрацию.

**Роли и права.** Свои таблицы, без `spatie/laravel-permission`. Каждый инфоблок при создании получает четыре собственных права, привязанных к его id — переименование инфоблока не отбирает доступ у ролей.

**Ещё:** настройки сайта, журнал действий с диффами изменений, светлая и тёмная темы, публичная часть с меню из инфоблока и `robots.txt` из настроек.

## Компоненты публичной части

Страницы сайта собираются из компонентов — как в Битриксе, только на Blade. У компонента две независимые ручки: сам компонент задаёт логику выборки, проп `template` — вёрстку.

```blade
<x-nexor::catalog.filter  iblock="katalog" />
<x-nexor::catalog.section iblock="katalog" template="tiles" card="katalog.card" />
```

Логика лежит в классах пакета, вёрстка — в его вьюхах; сайт кладёт свой шаблон рядом, и тот побеждает пакетный:

```
packages/nexor-cms/
    src/View/Components/                        логика (component.php)
        Breadcrumbs.php
        Pagination.php
        Catalog/
            Section.php                         список элементов
            Sections.php                        меню разделов
            Filter.php                          фильтр по свойствам
            Element.php                         детальная карточка
    resources/views/components/                 вёрстка (templates/.default)
        breadcrumbs/default.blade.php
        pagination/default.blade.php
        pagination/full.blade.php
        pagination/btnload.blade.php
        catalog/card/default.blade.php
        catalog/section/default.blade.php
        catalog/section/tiles.blade.php
        catalog/sections/default.blade.php
        catalog/sections/tree.blade.php
        catalog/filter/default.blade.php
        catalog/element/default.blade.php

resources/views/vendor/nexor/components/        ваши шаблоны, побеждают пакетные
    catalog/section/my_template.blade.php
```

Забрать шаблон себе — тот же жест, что копирование папки шаблона в свой шаблон сайта:

```bash
php artisan nexor:component                                    # что вообще есть
php artisan nexor:component catalog.section                    # все шаблоны под своими именами
php artisan nexor:component catalog.section blog               # свой шаблон blog на основе default
php artisan nexor:component catalog.section blog --from=tiles  # на основе другого шаблона
php artisan nexor:component catalog.section blog --force       # перезаписать свой
```

Второй аргумент — имя вашей копии, точно как `.default` → `my_template` в Битриксе. После этого шаблон выбирается пропом:

```blade
<x-nexor::catalog.section iblock="katalog" template="blog" />
```

Скопированный файл CMS больше не трогает: обновление пакета его не затрёт.

Соответствие привычным понятиям Битрикса:

| Битрикс | NEXOR |
|---|---|
| `/local/templates/main/header.php` + `footer.php` | `resources/views/site/layout.blade.php` |
| `/katalog/index.php` | `resources/views/katalog/index.blade.php` |
| `component.php` | класс в `src/View/Components` |
| `$arParams` | пропы тега |
| `$arResult` | что класс передал во вьюху |
| `result_modifier.php` | код в самом классе |
| `templates/my_template/template.php` | `resources/views/vendor/nexor/components/<компонент>/my_template.blade.php` |
| копия `.default` в `my_template` | `php artisan nexor:component catalog.section my_template` |
| копирование шаблона в свою тему | `php artisan nexor:component` |
| `bitrix:catalog.section` | `<x-nexor::catalog.section />` |

Под компонентами лежит `Nexor\Cms\Services\InfoBlockService` — единая точка чтения инфоблоков: фильтры, сортировка, разделы, постраничность. Его можно звать и напрямую, когда компонента мало.

## Запуск

```bash
composer install
npm install
cp .env.example .env
php artisan key:generate
```

Пропишите доступ к базе в `.env`, затем:

```bash
php artisan nexor:install
npm run build
php artisan serve
```

Панель управления — `/admin`. Пароль администратора задаётся отдельно:

```bash
php artisan nexor:password admin@example.com
```

## Разработка

Правьте исходники ядра в `packages/nexor-cms`, а не в `vendor/` — там симлинк на тот же каталог.

```bash
php artisan test --compact          # тесты
vendor/bin/pint --dirty             # форматирование
npm run dev                         # сборка стилей и скриптов
```

## Лицензия

MIT.
