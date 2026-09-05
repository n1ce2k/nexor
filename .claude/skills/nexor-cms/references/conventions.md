# Соглашения проекта

## Где что лежит

Ядро CMS живёт в composer-пакете `n1ce2k/nexor-cms`. В репозитории он лежит в `packages/nexor-cms`, а composer симлинкует его в `vendor/n1ce2k/nexor-cms` — приложение работает именно оттуда.

```
packages/nexor-cms/
  composer.json                   n1ce2k/nexor-cms, namespace Nexor\Cms
  config/nexor.php                префикс маршрутов, модель пользователя, диски, брендирование
  routes/admin.php                маршруты панели, имена admin.*
  src/
    NexorServiceProvider.php      маршруты, middleware, gates, биндинги, публикация
    Console/                      nexor:install, nexor:password, nexor:permissions
    Contracts/NexorUser.php       контракт модели пользователя приложения
    Enums/PropertyType.php        типы свойств инфоблоков
    Http/Controllers/             экраны админки (+ Auth/LoginController)
    Http/Middleware/              nexor.admin, nexor.permission, nexor.iblock
    Http/Requests/                Form Request на каждое действие
    Models/                       модели CMS + Concerns/HasRoles
    Support/                      Nexor, Permissions, Navigation, PropertyValues,
                                  ActivityLogger, Uploads, Site
  database/{migrations,factories,seeders}/
  resources/
    css/admin.css                 тема админки, токены, компонентные классы
    js/admin.js + js/admin/       Alpine: theme, stores, components
    views/admin/                  экраны, доступны как nexor::admin.*
    views/components/admin/       UI-кит, теги <x-nexor::admin.*>

app/                              приложение: User, публичная часть
  Models/User.php                 implements NexorUser, use HasRoles
  Http/Controllers/Site/          главная, страницы, robots.txt
resources/views/{layouts,site,errors}/   публичная часть
database/seeders/                 UserSeeder, PageSeeder, DatabaseSeeder
tests/                            интеграционные тесты пакета через хост-приложение
```

Важно про пакет:

- Шаблоны адресуются через неймспейс: `view('nexor::admin.users.index')`, `@extends('nexor::admin.layouts.app')`, теги `<x-nexor::admin.card>`.
- Пакет **не знает** класс пользователя. Только `Nexor::userModel()` / `Nexor::newUser()` и тип-хинт `NexorUser`. Параметр маршрута `{user}` резолвится явным `Route::bind` в провайдере.
- Фабрики пакета обязаны объявлять `protected $model` — стандартный резолвер Laravel ищет фабрику в неймспейсе приложения и на пакете промахивается. Модели объявляют `newFactory()` по той же причине.

## Код

PHP 8.4. Строгие типы возврата и типы параметров везде. Промоушен свойств в конструкторе. Фигурные скобки всегда, даже на однострочниках. PHPDoc вместо инлайновых комментариев; инлайновый комментарий — только на нетривиальную логику, и он объясняет **почему**, а не что.

Форматирование — Pint, запускать перед сдачей:

```bash
vendor/bin/pint --dirty --format agent
```

Модели используют атрибуты Laravel 13: `#[Fillable([...])]`, `#[Hidden([...])]` — не свойства `$fillable`. Касты — через метод `casts()`. Скоупы — `scopeOrdered`, `scopeActive`.

Имена на русском в интерфейсе, английские в коде. Сообщения валидации и `attributes()` в Form Request — на русском, чтобы ошибки читались.

## Контроллеры

Ресурсные, семь методов. `show` там, где нечего показывать, редиректит на `edit`. Валидация — только в Form Request, никогда инлайн в контроллере. Логика значений свойств — в `PropertyValues`, не в контроллере.

Проверка чужого инфоблока — `abort_unless($model->iblock_id === $iblock->id, 404)` в начале метода вложенного ресурса.

Запись в журнал — `ActivityLogger::created/updated/deleted($model)` после сохранения.

Флеш-сообщения: `->with('success', '…')` при успехе, `->with('error', '…')` когда действие запрещено бизнес-правилом (системная роль, удаление себя).

## Тесты

PHPUnit, не Pest. Имена методов — законченное предложение о поведении: `test_a_system_role_cannot_be_deleted`. Не `test_destroy`.

Фабрики есть у всех моделей, со стейтами: `IblockPropertyFactory::ofType()`, `->multiple()`, `->required()`, `->withEnums([...])`, `IblockSectionFactory::childOf()`, `RoleFactory::withPermissions([...])`.

Пользователи в тестах — через трейт `CreatesAdminUsers`:

```php
$admin = $this->adminWith(['users.view']);          // доступ в панель + перечисленные права
$root  = $this->superAdmin();                        // проходит любую проверку
$admin = $this->grantIblock($admin, $iblock, ['view', 'create']);
```

Новый экран админки обязательно добавляется в `AdminScreensTest` — он рендерит каждую страницу и ловит битые шаблоны.

## Команды

```bash
php artisan test --compact                    # весь набор
php artisan test --filter=testName            # один тест
php artisan migrate -n
php artisan db:seed -n
npm run build
```

Всегда `-n` для artisan: интерактивные промпты вешают сессию.
