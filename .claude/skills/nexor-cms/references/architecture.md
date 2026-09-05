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
