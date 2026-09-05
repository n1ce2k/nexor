# Vue-панель

Вторая админка поверх того же ядра: SPA на Vue 3 по адресу `/admin/vue`. Blade-версия остаётся на `/admin` и продолжает работать — их можно использовать параллельно.

## Где что лежит

```
packages/nexor-cms/
  routes/api.php                    JSON API, префикс /admin/api, имена admin.api.*
  src/Http/Controllers/Api/         контроллеры API (наследуют ApiController)
  src/Http/Resources/               Eloquent API Resources
  src/Http/Controllers/PanelController.php   отдаёт HTML-оболочку SPA
  resources/views/admin/panel.blade.php      сама оболочка
  resources/js/panel/
    main.js                         точка входа, публикует window.Nexor
    registry.js                     точки расширения
    api.js                          обёртка над fetch: CSRF, ApiError, FormData
    router.js
    App.vue
    layouts/AdminLayout.vue
    stores/{session,ui}.js
    composables/{useForm,useNavigation}.js
    components/ui/N*.vue            UI-кит
    components/fields/              редакторы свойств + PropertyField
    pages/                          экраны
```

## Как это работает

Форма элемента **не описана в коде**. Страница запрашивает `GET /admin/api/iblocks/{id}/schema`, получает список свойств и для каждого достаёт компонент из реестра по типу. Добавили новый тип свойства — зарегистрировали компонент, и форма его подхватила.

Ответы API: ресурс, возвращённый из контроллера напрямую, обёрнут в `data`; ресурс, вложенный в `response()->json([...])`, — **не обёрнут**. Поэтому `bootstrap`, `dashboard` и `schema` отдают плоские массивы, а списки — `{ data, meta }`.

Значения свойств уходят через `multipart/form-data`, потому что файловые свойства несут настоящие `File`. У файловых свойств значение в форме — объект `{ stored, remove, added }`, у остальных — скаляр или массив.

## Точки расширения

Всё через `window.Nexor` **до** монтирования. Свой бандл подключается в `config('nexor.panel_extensions')` — он грузится раньше `main.js`.

```js
window.Nexor.ready((nexor) => {
    // свой редактор для типа свойства
    nexor.registerField('geo', MyMapField);

    // своя страница + пункт меню
    nexor.registerPage({
        path: 'reports',
        name: 'reports',
        component: Reports,
        permission: 'reports.view',
        menu: { label: 'Отчёты', icon: 'chart', group: 'Контент' },
    });

    // своя ячейка в списке элементов конкретного инфоблока
    nexor.registerColumn('colors', 'HEX', SwatchCell);

    // реакция на события панели
    nexor.on('element.saved', ({ element }) => console.log(element));
});
```

Контракт компонента-поля: props `modelValue`, `property`, `options`, `invalid`; эмитит `update:modelValue`. Множественность и обёртку с подписью берёт на себя `PropertyField` — само поле всегда работает с одним значением.

## Что уже готово и чего нет

Готово: рабочий стол, инфоблоки (список и форма), свойства (список и форма со всеми типами и вариантами списка), элементы (список с фильтрами по свойствам и форма с динамическими полями).

Не готово: разделы, пользователи, роли, настройки, журнал, профиль — эти разделы пока только в Blade-админке. API для них уже есть, нужны только экраны.

## Проверка

Экраны SPA не покрываются PHPUnit — вместо этого протестирован API, через который панель работает: `tests/Feature/PanelApiTest.php`. Добавляя эндпоинт, добавь туда тест.

```bash
php artisan test --filter=PanelApiTest
npm run build
```
