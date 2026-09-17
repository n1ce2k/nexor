# NEXOR CMS

Админ-панель для Laravel: инфоблоки со свойствами произвольных типов, разделы-деревья, роли и права, настройки сайта и журнал действий. Без Filament, Orchid и сторонних пакетов прав — только ядро Laravel.

## Требования

- PHP 8.3+
- Laravel 12 или 13
- Livewire 4 — ставится вместе с пакетом, нужен формам с отправкой без перезагрузки

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
use Nexor\Cms\Models\Concerns\HasUserFields;

class User extends Authenticatable implements NexorUser
{
    use HasRoles, HasUserFields;
}
```

Если модель лежит не по адресу `App\Models\User`, укажите её в `config/nexor.php` или в `.env`:

```
NEXOR_USER_MODEL="Domain\Users\Models\Account"
```

### Стили и скрипты

Админка приходит уже собранной (`dist/`) и отдаётся адресом `/admin/nexor-assets/...` — Node для неё не нужен. `nexor:install` дописывает в `resources/css/app.css` строки `@source` на шаблоны компонентов, чтобы Tailwind сайта видел их классы.

Для разработки самой панели — `NEXOR_PANEL_ASSETS=vite`: исходники пакета собирает Vite сайта.

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

## Компоненты сайта

Страницы собираются из Blade-компонентов, как `IncludeComponent` в Битриксе: класс отвечает за выборку, проп `template` — за вёрстку.

```blade
<x-nexor::catalog.section iblock="katalog" template="tiles" />
<x-nexor::news.list iblock="news" />
<x-nexor::menu code="main" />
```

Инфоблок указывается кодом или id: `iblock="news"` и `iblock="1"` — одно и то же (поэтому код инфоблока не может состоять из одних цифр). id видны первой колонкой в списках админки.

Свой шаблон — копия в `resources/views/vendor/nexor/components`, обновление пакета её не трогает:

```bash
php artisan nexor:component catalog.section blog
```

**Список одного раздела — `section_id`.** `catalog.section` и `news.list` выводят элементы только указанного раздела (вместе с подразделами; без них — `:recursive="false"`):

```blade
<x-nexor::catalog.section iblock="katalog" :section_id="3" />
<x-nexor::news.list iblock="news" :section_id="4" :per-page="3" />
```

Такой раздел закреплён: адрес страницы его не меняет. Раздел удалили или он из другого инфоблока — список пустой, а не весь инфоблок. Без `section_id` раздел, как и раньше, берётся из пропа `section` (код) или из адреса страницы.

### Детальная элемента в любом месте

`news.detail` и `catalog.element` выводят элемент и вне его страницы — например, новость на главной. Элемент передаётся готовым или ищется по инфоблоку и id либо символьному коду:

```blade
<x-nexor::news.detail :element="$element" />
<x-nexor::news.detail iblock="news" :id="5" />
<x-nexor::news.detail iblock="news" code="otkrytie-magazina" />
<x-nexor::news.detail :iblock="1" :id="5" />
<x-nexor::catalog.element iblock="katalog" :id="15" template="home" />
```

- Ищется только опубликованный элемент этого инфоблока (активен и в сроке показа).
- Удалили, скрыли, id от другого инфоблока — компонент ничего не выводит, страница не падает.
- Нет ни `id`, ни `code` или `id` не число — исключение с понятным текстом.
- Инфоблок не найден или отключён — вместо компонента заглушка «Инфоблок недоступен» (с подробностями при `APP_DEBUG=true`), предупреждение в лог. Так ведут себя все компоненты с `iblock`. Своя вёрстка заглушки — `php artisan nexor:component unavailable`.
- Другая вёрстка для такого места — свой шаблон: `php artisan nexor:component news.detail home` и `template="home"`.

## Пользователи

Логин и e-mail обязательны и уникальны; на входе одно поле «Логин или e-mail».

Свои поля пользователей заводятся в админке («Пользователи» → «Поля»), значения лежат в EAV-таблице `user_field_values` с индексами по типам. В коде:

```php
$user->field('city');
$user->fields();
```

Право на набор полей — `user_fields.manage`. Для значений модели пользователя нужен трейт `HasUserFields` (см. «Модель пользователя»).

## Формы обратной связи

В админке — «Формы ОС» (поля, почтовый шаблон, соглашение, записи) и «Соглашения». На сайте:

```blade
<x-nexor::form :id="1" />
<x-nexor::form form="callback" />
```

- Типы полей: строка, textarea, телефон, e-mail, файл. Файлы — на закрытом диске (`NEXOR_FORMS_DISK`, по умолчанию `local`), во вложениях письма и в записях.
- Подстановки письма: `#<КОД ПОЛЯ>#`, `#FORM_NAME#`, `#FORM_ID#`, `#FORM_CODE#`, `#SUBMISSION_ID#`, `#SUBMISSION_URL#`, `#PAGE_URL#`, `#DATE#`, `#ALL_FIELDS#`.
- «Сделать отправку без перезагрузки» — Livewire-компонент `nexor::feedback-form`, шаблон `<имя>-livewire.blade.php`. `php artisan nexor:component form my` копирует пару.
- Вкладка «Telegram»: заявка сообщением от бота во все указанные чаты, файлы — документами.
- Вкладка «Защита»: Yandex SmartCaptcha, Google reCAPTCHA (v2/v3) или своя Nexor Captcha (картинка, нужен `ext-gd`, без него — упрощённая SVG). Секреты — зашифрованы.
- Соглашение открывается во всплывающем окне (CSS `:target`, без JS) или страницей `/agreement/<код>`.
- Старый вариант без админки — `<x-nexor::form :fields="['name', 'phone']" />` — работает как раньше.

Права: `forms.view|create|update|delete`, `forms.submissions.view|delete`, `agreements.view|create|update|delete`.

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
