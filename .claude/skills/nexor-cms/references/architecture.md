# Модель данных NEXOR

## Инфоблоки

Калька с Битрикса. Пять уровней:

```
iblock_types            Типы: «Контент», «Каталог», «Служебные»
  └ iblocks             Инфоблоки: «Страницы», «Новости», «Палитра»
      ├ iblock_sections Разделы — дерево внутри инфоблока
      ├ iblock_properties          Свойства с типом данных
      │   └ iblock_property_enums  Варианты для типа «список»
      └ iblock_elements            Элементы (сам контент)
          ├ iblock_element_section        элемент в нескольких разделах
          └ iblock_element_values         значения свойств (EAV)
```

Модели: `IblockType`, `Iblock`, `IblockSection`, `IblockProperty`, `IblockPropertyEnum`, `IblockElement`, `IblockElementValue`.

## Свойства и EAV

`App\Enums\PropertyType` — 16 типов. Энум знает про себя всё:

| Метод | Что даёт |
|---|---|
| `label()` | Подпись на русском |
| `group()` | Группа в выпадающем списке типов |
| `column()` | **Колонка `iblock_element_values`, где лежит значение** |
| `usesEnums()` | Нужен ли редактор вариантов |
| `isFile()` | Файловый ли тип |
| `settingKeys()` | Какие настройки типа показывать в форме свойства |

`column()` — ключевая вещь. Значения лежат в типизированной EAV-таблице с колонками `value_string`, `value_text`, `value_int`, `value_decimal`, `value_bool`, `value_date`, `value_json`, `value_enum_id`, `value_element_id`, `value_section_id`, `value_user_id`. Индексы стоят парами `(property_id, value_*)` — под фильтрацию.

Множественное свойство = несколько строк в `iblock_element_values` с одинаковыми `element_id` + `property_id`, порядок в `sort`.

### Кто с этим работает

`App\Support\PropertyValues`:

- `rules(Collection $properties)` — строит правила валидации для `properties.*` и `property_files.*`
- `save(IblockElement $element, Collection $properties, Request $request)` — переписывает значения элемента
- `forForm(IblockElement $element)` — значения для перезаполнения формы

Скалярные свойства при сохранении удаляются и пересоздаются. Файловые — сохраняются, пока их явно не отметили к удалению (`property_remove[CODE][]`), новые загрузки добавляются.

`IblockElement::propertyValues()` отдаёт коллекцию `код => значение` (для множественных — коллекция значений). `displayValue(IblockProperty)` — короткая печатная форма для колонок списка.

## Разделы — материализованный путь

`IblockSection` хранит `parent_id`, `depth` и `path` вида `/1/7/`. Пересчёт в `saving`/`saved`: при смене родителя пересохраняется всё поддерево. Поэтому:

- поддерево — `where('path', 'like', $section->path.$section->id.'/%')`
- предки — по id из `explode('/', $path)`
- `indented_name` — имя с отступом для плоских списков

## Права

Свои таблицы `roles`, `permissions`, `permission_role`, `role_user`. Никакого spatie.

`App\Models\Concerns\HasRoles` на `User`: `hasPermission()`, `hasAnyPermission()`, `permissionCodes()` (мемоизируется на объекте), `isSuperAdmin()`.

`App\Support\Permissions` — каталог прав:

- `definitions()` — статические права админки, сгруппированы
- `syncIblock(Iblock)` — четыре права конкретного инфоблока
- `syncAll()` — полная пересборка + выдача всего супер-админу + чистка осиротевших

Инфоблок сам поддерживает свои права: `Iblock::booted()` вызывает `syncIblock` при создании и переименовании, `forgetIblock` — при `forceDelete`.

Middleware: `admin` (доступ в панель), `permission:code1,code2` (любое из), `iblock:view|create|update|delete` (право конкретного инфоблока из `{iblock}` в URL).

`Gate::before` в `AppServiceProvider` резолвит любую способность с точкой через `hasPermission`, поэтому в Blade работает `@can('users.view')` и `@can('iblock.7.update')`.

## Прочее

`App\Support\Navigation` — сайдбар. URL и активность вычисляются в PHP, Blade только рисует. Инфоблоки попадают в меню сами, сгруппированные по типу.

`App\Support\ActivityLogger` — журнал. `updated()` пишет только реально изменившиеся атрибуты, пароли маскируются.

`App\Support\Uploads` — триада «загрузить новый / оставить старый / удалить» для файловых полей.

`App\Models\Setting` — настройки с вечным кэшем, `Setting::get()` / `Setting::put()`.

`App\Support\Site` — читалка для публичной части: `iblock()`, `elements()`, `element()`, `menu()`.

## Страницы-папки

Инфоблок с включённым `has_page` владеет папкой `resources/views/<код>/` — как папка-страница в Битриксе. `Nexor\Cms\Support\PageGenerator` кладёт туда три файла и **никогда их не перезаписывает**: дальше они принадлежат тому, кто их правит.

| файл | что делает |
| --- | --- |
| `index.blade.php` | список, отдаётся по `/<код>` |
| `detail.blade.php` | один элемент, `/<код>/<код элемента>` |
| `pagination.blade.php` | компонент пагинации, который подключает список |

Путь списка запоминается в `iblocks.page_path`. Публичный роут `/{code}` сначала ищет такую страницу и только потом — элемент инфоблока «Страницы»; `/{code}/{element}` ведёт на деталку.

## Пагинация

`Nexor\Cms\Enums\PaginationTemplate` — три шаблона: `pagination` (номера), `pagination_full` (номера + «первая/последняя» + счётчик), `pagination_btnload` (только кнопка «Показать ещё»). Выбранный шаблон разворачивается в `pagination.blade.php`; при смене шаблона этот **один** файл переписывается заново (`PageGenerator::refreshPagination`), остальные не трогаются.

Отдельный переключатель `has_load_more` добавляет кнопку и к нумерованным шаблонам, а `load_more_size` задаёт размер порции. `Iblock::pageSize()` сводит это к одному числу, `Site::paginate($code)` отдаёт страницу списка, `IblockElement::url()` — адрес элемента.

Кнопка работает и без JavaScript: это обычная ссылка на `?page=N`, а скрипт лишь дописывает следующую порцию в `#nexor-items`. Поэтому контейнер списка обязан иметь этот id, а макет — `@stack('scripts')`.

## Форма элемента

`Nexor\Cms\Support\ElementFormLayout` описывает вкладки формы элемента. По умолчанию их пять — Основное, SEO, Анонс, Описание, Разделы, — свойства инфоблока попадают на первую. Своя раскладка живёт в `iblocks.settings.form_tabs` и на каждом чтении сверяется с реальностью: новое свойство добавляется на первую вкладку, удалённое исчезает. Ключ поля — либо имя колонки, либо `prop:<КОД СВОЙСТВА>`.

Схема `/admin/api/iblocks/{iblock}/schema` отдаёт `form_tabs` и `form_fields`; `PUT /admin/api/iblocks/{iblock}/form-layout` сохраняет раскладку, пустой массив `tabs` возвращает стандартную.

## Режим обслуживания

`CheckMaintenanceMode` висит на группе `web`. Пока настройка `site.maintenance` включена, гость получает заглушку `nexor::site.maintenance` с кодом 503; авторизованные и весь префикс админки проходят насквозь.

Ловушка: HTTP-ядро при резолве заменяет группы middleware у роутера, поэтому провайдер добавляет middleware дважды — в `boot()` и в `afterResolving(HttpKernel::class)`. Порядок этих двух шагов различается в тестах и на бою, выигрывает тот, что позже.

## Почта

`Nexor\Cms\Support\MailConfig::apply()` на каждом запросе накрывает конфиг почты значениями из настроек группы `mail`, если включён `mail.enabled`. Пароль хранится зашифрованным (`Setting` с типом `password`).

`MailTemplate` — шаблоны писем с подстановками вида `#NAME#`, метод `render($field, $data)`.
