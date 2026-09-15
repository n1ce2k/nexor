# NEXOR — Конструктор страниц

Модуль для [NEXOR CMS](https://github.com/n1ce2k/nexor-cms): детальная страница элемента собирается из блоков вместо подробного текста. Доступен на всех лицензиях.

```bash
composer require n1ce2k/nexor-pagebuilder
php artisan nexor-pagebuilder:install
```

Установщик создаёт таблицу `pagebuilder_layouts`. Редактор в панели приходит собранным в `dist/`, стили и скрипт блоков для сайта — в `resources/assets/`.

## Как пользоваться

1. Инфоблок → «Параметры» → **«Использовать конструктор детальной страницы»**.
2. У элементов появится вкладка **«Конструктор»**: блоки перетаскиваются из палитры слева или добавляются кликом, меняются местами, сворачиваются, дублируются.
3. Блоки сохраняются вместе с элементом кнопкой «Сохранить».
4. На сайте, пока у элемента есть блоки, они выводятся вместо подробного текста. Нет блоков или переключатель снят — снова подробный текст, а сами блоки остаются в базе.

Выключить модуль целиком можно на странице «Модули»: вкладка и вывод пропадут, данные останутся.

## Блоки первой версии

Вёрстка блоков — классы `pb-*` и `nw-*`, как в исходном конструкторе: `pb-wrapper` → `pb-content` → `pb-block-wrapper[data-type]` → `section.pb-block.pb-<тип>` → `pb-container`. Стили, написанные под эти классы, подходят без изменений.

| Тип | Блок | Данные |
|---|---|---|
| `header` | Заголовок | `text`, `tag` (h2–h5) |
| `text` | Текст | `text`, `text2`, `cols` (1 или 2 колонки — `is-col-2`) |
| `quote` | Цитата | `text`, `showAuthor`, `author`, `role`, `avatar` |
| `text_image` | Текст + Фото | `text`, `image`, `imagePosition` (left/right — `pb-text-image--right`) |
| `photo` | Фото / Галерея | `images [{path, alt, title}]`, `layoutMode` (preset/custom), `presetId` (1, 2, 3, 4, 4_grid, 3_asym), `asymmetricDir`, `customCols`, `customVisible`, `showOverlay` («+N») |
| `video` | Видео | `sourceType` (link/file), `url` или `file` + `poster`, `layout` (full/caption/split), `reverse`, `text`, `settings {autoplay, loop, muted, controls}` |
| `accordion` | Аккордеон | `items [{title, content}]`, `openFirst`; разметка schema.org FAQPage |
| `table` | Таблица | `rows [[ячейка, …]]`, `header` (первый ряд из `<th>`), `showTitle`, `title`, `titleTag` |

У каждого блока есть `cssClass` — дополнительный класс секции. У всей раскладки — боковое меню `pb-aside` со ссылками на блоки (`settings.showSidebar`, `settings.sidebarItems`).

Файлы (`image`, `avatar`, `file`, `poster`, `images[]`) хранятся путём в хранилище; шаблон получает `{ src, alt, title }`.

HTML из визуального редактора чистится при сохранении по белому списку: без скриптов, стилей, обработчиков и `javascript:`-ссылок. Видео встраивается только по адресу плеера, собранному из распознанной ссылки. Файлы загружает тот, у кого есть право менять элементы инфоблока: картинки jpg, png, webp, gif и видео mp4, webm.

## Стили и скрипт на сайте

Вместе с блоками выводятся `pagebuilder.css` и `pagebuilder.js` модуля (адрес `/nexor-pagebuilder/assets/…`, один раз на страницу):

- оформление классов `pb-*` и `nw-*`; цвета берутся из переменных темы сайта (`--color-blue-600` и т. п.), без них — запасные;
- аккордеон (`data-accordion`), табы (`data-tabs`), плавная прокрутка оглавления;
- Fancybox для фото (`data-fancybox`) и Swiper для слайдеров — если сайт их подключил.

Свои стили вместо модульных — `NEXOR_PAGEBUILDER_ASSETS=false` или `PageBuilder::render($element, ['assets' => false])`. Тогда обёртка получает `data-pb-standalone="false"`, и скрипт модуля её не трогает.

## Вывод на сайте

В стандартных шаблонах `catalog.element`, `news.detail` и `site/page` уже есть:

```blade
@feature('pagebuilder')
    @php($pageBuilder = \Nexor\PageBuilder\PageBuilder::render($element)->toHtml())
@endfeature

@if (! empty($pageBuilder))
    {!! $pageBuilder !!}
@else
    {{-- подробный текст --}}
@endif
```

Свой шаблон элемента — вставьте этот кусок туда, где должен быть контент.

Шаблоны блоков забираются к себе и верстаются как угодно:

```bash
php artisan nexor-pagebuilder:component          # список
php artisan nexor-pagebuilder:component text     # один блок
php artisan nexor-pagebuilder:component all      # все блоки и обёртка
```

Копии лежат в `resources/views/vendor/nexor-pagebuilder` и обновлением модуля не перезаписываются.

## Свой блок

```php
use Nexor\PageBuilder\Blocks\Block;
use Nexor\PageBuilder\PageBuilder;

class PromoBlock extends Block
{
    public function type(): string { return 'promo'; }
    public function label(): string { return 'Промо'; }
    public function rules(): array { return ['title' => ['nullable', 'string', 'max:255']]; }
    public function view(): string { return 'blocks.promo'; }
}

// в AppServiceProvider::boot()
PageBuilder::blocks()->register(new PromoBlock);
```

Редактор блока в панели — Vue-компонент с prop `data` (правится на месте):

```js
window.Nexor.pageBuilder.registerEditor('promo', PromoEditor);
```

## Как устроено

- Раскладка — JSON-документ `{ version, settings, blocks: [{ id, type, data }] }` в таблице `pagebuilder_layouts`. Владелец описан парой `owner_type` + `owner_id`, поверхность — `surface`.
- Модуль встраивается в ядро через точки расширения `Module::iblockSettings()`, `elementFields()`, `elementRules()`, `elementValues()`, `saveElement()` — ядро не знает про конструктор.
- В панели поле формы регистрируется через `Nexor.registerFormField('pagebuilder.content', …)`.

## Дальше: конструктор страниц сайта

Следующий большой этап — страницы целиком, как в шаблонах Аспро: главная и лендинги собираются из секций в админке. Задел уже заложен:

- `Surface::Page` — вторая поверхность; блок объявляет, где он доступен (`Block::surfaces()`).
- `pagebuilder_layouts` рассчитана на других владельцев: страница будет `owner_type = page`.
- `settings` в документе раскладки — под параметры всей страницы.

Что предстоит продумать и сделать:

1. **Сущность «Страница»**: адрес, SEO, макет, публикация; раздел «Страницы» в панели и маршрут на сайте (fallback, как у инфоблоков).
2. **Секции страницы**: ширина (контейнер / во всю ширину), фон, отступы, видимость на мобильных — в `settings` каждого блока.
3. **Блоки-витрины**, привязанные к инфоблокам: «Товары из раздела», «Новости», «Баннеры», «Преимущества», «Отзывы», «Бренды» — через `InfoBlockService`, с выбором инфоблока, раздела, количества и шаблона карточки.
4. **Шаблоны секций**: несколько вариантов вёрстки одного блока (как «типы» блоков в Аспро) и превью варианта в палитре.
5. **Предпросмотр страницы** в панели до публикации и черновики.
6. **Блоки второй очереди** для детальной страницы: слайдер, табы, ссылки и файлы, «Товары из каталога» (стили и скрипт для слайдера и табов уже есть).
7. **Поиск**: подключить `pagebuilder_layouts.search_text` к поиску ядра (нужна точка расширения в `InfoBlockService::searchElements`).
8. **Уборка файлов**: удалять загруженные картинки, на которые больше не ссылается ни одна раскладка.
