# NEXOR CMS

Админ-панель для Laravel: инфоблоки со свойствами произвольных типов, разделы-деревья, роли и права, настройки сайта и журнал действий. Без Filament, Orchid и сторонних пакетов прав — только ядро Laravel.

## Требования

- PHP 8.3+
- Laravel 12 или 13

## Установка

```bash
composer require n1ce2k/nexor-cms
php artisan nexor:install
```

`nexor:install` прогонит миграции, создаст роли, права, настройки и базовые типы инфоблоков, затем спросит e-mail супер-администратора. Пароль задаётся отдельно, скрытым вводом:

```bash
php artisan nexor:password admin@example.com
```

### Модель пользователя

Модель пользователя остаётся в приложении. Добавьте ей трейт и контракт:

```php
use Nexor\Cms\Contracts\NexorUser;
use Nexor\Cms\Models\Concerns\HasRoles;

class User extends Authenticatable implements NexorUser
{
    use HasRoles;
}
```

Если модель лежит не по адресу `App\Models\User`, укажите её в `config/nexor.php` или в `.env`:

```
NEXOR_USER_MODEL="Domain\Users\Models\Account"
```

### Стили и скрипты

Точки входа лежат в пакете. Добавьте их в `vite.config.js` приложения:

```js
laravel({
    input: [
        'vendor/n1ce2k/nexor-cms/resources/css/admin.css',
        'vendor/n1ce2k/nexor-cms/resources/js/admin.js',
    ],
})
```

## Конфигурация

```bash
php artisan vendor:publish --tag=nexor-config
```

| Ключ | Что задаёт |
|---|---|
| `route.prefix` | URL панели, по умолчанию `admin` |
| `route.middleware` | Middleware группы маршрутов |
| `user_model` | Класс пользователя приложения |
| `storage.disk` | Диск для загрузок |
| `storage.directories` | Папки под аватары, картинки инфоблоков, файлы свойств |
| `brand.name`, `brand.initial` | Название и буква логотипа |
| `per_page` | Размер страницы в списках |
| `property_types` | Ограничить набор доступных типов свойств |

Шаблоны переопределяются публикацией:

```bash
php artisan vendor:publish --tag=nexor-views
```

## Инфоблоки

```
Тип инфоблока → Инфоблок → Раздел (дерево)
                         → Свойство (16 типов данных)
                         → Элемент → Значения свойств
```

Типы свойств: строка, текст, HTML, целое, дробное, да/нет, дата, дата и время, цвет, список, файл, изображение, привязка к элементу, к разделу, к пользователю, JSON. У свойства есть флаги: множественное, обязательное, участвует в фильтре, участвует в поиске, колонка в списке.

Форма элемента строится из свойств инфоблока в рантайме — добавили свойство, оно сразу появилось в форме, фильтре и списке.

Чтение в шаблонах сайта:

```php
use Nexor\Cms\Support\Site;

$news = Site::elements('news', limit: 10);
$page = Site::element('pages', 'about');

$page->property('SUBTITLE');
```

## Права

Каждый инфоблок при создании получает четыре собственных права: `iblock.{id}.view`, `.create`, `.update`, `.delete`. Ключ — идентификатор, а не символьный код, поэтому переименование инфоблока не отбирает права у ролей.

Проверка в коде и шаблонах:

```php
$user->hasPermission('users.update');
```

```blade
@can('iblock.7.update') … @endcan
```

Middleware маршрутов: `nexor.admin`, `nexor.permission:code`, `nexor.iblock:view|create|update|delete`.

Пересобрать каталог прав после ручных изменений:

```bash
php artisan nexor:permissions
```

## Лицензия

MIT.
