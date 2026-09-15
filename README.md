# NEXOR

CMS на Laravel: админ-панель с инфоблоками в стиле Битрикса, свойствами произвольных типов и собственной системой ролей. Без Filament, Orchid и сторонних пакетов прав.

Репозиторий содержит несколько слоёв:

| | |
|---|---|
| `packages/nexor-cms` | Ядро CMS — composer-пакет `n1ce2k/nexor-cms`, namespace `Nexor\Cms` |
| `packages/nexor-shop` | Модуль «Магазин» — composer-пакет `n1ce2k/nexor-shop`, namespace `Nexor\Shop` |
| `packages/nexor-pagebuilder` | Модуль «Конструктор страниц» — composer-пакет `n1ce2k/nexor-pagebuilder`, namespace `Nexor\PageBuilder` |
| остальное | Хост-приложение: модель пользователя, публичная часть сайта, демо-контент |

Приложение подключает пакет path-репозиторием и исполняет его из `vendor/n1ce2k/nexor-cms`. Документация ядра — в [packages/nexor-cms/README.md](packages/nexor-cms/README.md).

## Возможности

**Инфоблоки.** Типы инфоблоков → инфоблоки → разделы-деревья → элементы. У каждого инфоблока свой набор свойств; форма элемента строится из них в рантайме.

**16 типов свойств:** строка, текст, HTML, целое, дробное, да/нет, дата, дата и время, цвет, список, файл, изображение, привязка к элементу, к разделу, к пользователю, JSON. Флаги: множественное, обязательное, в фильтре, в поиске, колонкой в списке. Значения лежат в типизированной EAV-таблице с индексами под фильтрацию.

**Роли и права.** Свои таблицы, без `spatie/laravel-permission`. Каждый инфоблок при создании получает четыре собственных права, привязанных к его id — переименование инфоблока не отбирает доступ у ролей.

## Меню

Навигация собирается в админке — Структура → Меню — и выводится по коду:

```blade
<x-nexor::menu code="main" />
<x-nexor::menu code="footer" template="stacked" />
```

В одном меню рядом живут разные типы пунктов:

| Тип | Что задаётся | Что получается |
|---|---|---|
| Ссылка | название и адрес | обычный пункт |
| Страница | элемент инфоблока | адрес считается сам и переживает переименование |
| Раздел | раздел инфоблока | то же для раздела |
| Разделы инфоблока | инфоблок, корень, глубина | раскрывается в ветку на своём месте |
| Заголовок, разделитель | подпись | оформление больших меню |

Дерево правится перетаскиванием и кнопками. Пункт можно спрятать от гостей или от авторизованных — чтобы «Войти» и «Кабинет» жили в одном меню.

Подсветка идёт по самому длинному совпадению: на `/katalog/razdel1` активен раздел, а «Каталог» только раскрыт. Собранное дерево кешируется и сбрасывается при правке меню и разделов; выключается через `NEXOR_MENU_CACHE=false`.

**Торговый каталог.** Галочка «Торговый каталог» в настройках инфоблока добавляет элементам вкладки «Цена», «Остатки» (доступное количество, единица измерения, коэффициент, количественный учёт, покупка при отсутствии) и «Скидки» — цена со скидкой считается прямо в форме. Одновременно создаётся связанный инфоблок «Предложения — <название>», а у товара появляется вкладка «Предложения», где заводятся варианты со своей ценой и остатком. Торговые данные лежат в отдельной таблице `catalog_products`, а не в свойствах: по цене и наличию сортируют и фильтруют. Снятая галочка ничего не удаляет; права ролей на каталог при включении копируются на предложения. Цены и остатки есть на любой лицензии, торговые предложения — со Standart.

**Ещё:** настройки сайта, журнал действий с диффами изменений, светлая и тёмная темы, публичная часть с меню из инфоблока и `robots.txt` из настроек.

## Компоненты публичной части

Страницы сайта собираются из компонентов — как в Битриксе, только на Blade. У компонента две независимые ручки: сам компонент задаёт логику выборки, проп `template` — вёрстку.

```blade
<x-nexor::catalog.filter  iblock="katalog" />
<x-nexor::catalog.section iblock="katalog" template="tiles" card="katalog.card" />
```

### Своя карточка элемента

Шаблон списка рисует каждый элемент через `@include($cardView, ['element' => $element])`. `$cardView` — это проп `card`, обычное имя вьюхи через точки от `resources/views`:

```blade
<x-nexor::catalog.section iblock="katalog" template="catalogList" card="katalog.card" />
```

Карточка лежит в `resources/views/katalog/card.blade.php`, внутри доступна `$element`. Так один шаблон списка можно звать с разными карточками на разных страницах. Поменять карточку по умолчанию везде сразу — `php artisan nexor:component catalog.card`.

| Компонент | Аналог в Битриксе | Зачем |
|---|---|---|
| `catalog.section` | `catalog.section` | список элементов |
| `catalog.section-list` | `catalog.section.list` | карточки подразделов |
| `catalog.element` | `catalog.element` | детальная карточка |
| `catalog.filter` | `catalog.filter` | фильтр по свойствам |
| `news.list` | `news.list` | лента новостей |
| `news.detail` | `news.detail` | детальная новость |
| `menu` | `menu` | меню из админки по `code` или разделы по `iblock` |
| `menu.sections` | левое меню разделов | дерево разделов с глубиной и корнем |
| `form` | `main.feedback` | форма обратной связи |
| `search.form` | `search.form` | строка поиска |
| `search.page` | `search.page` | результаты поиска |
| `breadcrumbs` | `breadcrumb` | хлебные крошки |
| `pagination` | `system.pagenavigation` | постраничная навигация |

Два имени расходятся с Битриксом из-за PHP: `list` — зарезервированное слово, класса `Catalog\Section\List` не бывает. У `catalog.section-list` поэтому дефис, а `news.list` сохранил точку через псевдоним класса `News\Listing`.

Форма шлёт письмо на адрес из пропа `to` или из настройки `contacts.email`, по коду почтового шаблона. Настройки едут в браузер зашифрованными — иначе получателя можно было бы подменить и превратить сайт в открытый релей.

Логика лежит в классах пакета, вёрстка — в его вьюхах; сайт кладёт свой шаблон рядом, и тот побеждает пакетный:

```
packages/nexor-cms/
    src/View/Components/                        логика (component.php)
        Breadcrumbs.php  Pagination.php  Form.php  Menu.php
        Menu/Sections.php
        Catalog/Section.php  Catalog/SectionList.php
        Catalog/Filter.php   Catalog/Element.php
        News/Listing.php     News/Detail.php
        Search/Form.php      Search/Page.php
    resources/views/components/                 вёрстка (templates/.default)
        <компонент>/<шаблон>.blade.php

resources/views/vendor/nexor/components/        ваши шаблоны, побеждают пакетные
    catalog/section/my_template.blade.php
```

Забрать шаблон себе — тот же жест, что копирование папки шаблона в свой шаблон сайта:

```bash
php artisan nexor:component                                    # что вообще есть
php artisan nexor:component all                                # шаблоны всех компонентов разом
php artisan nexor:component catalog.section                    # все шаблоны одного компонента
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
| `/katalog/mebel/` раздел | `resources/views/katalog/section.blade.php` |
| `/katalog/mebel/stul` элемент | `resources/views/katalog/detail.blade.php` |
| `component.php` | класс в `src/View/Components` |
| `$arParams` | пропы тега |
| `$arResult` | что класс передал во вьюху |
| `result_modifier.php` | код в самом классе |
| `templates/my_template/template.php` | `resources/views/vendor/nexor/components/<компонент>/my_template.blade.php` |
| копия `.default` в `my_template` | `php artisan nexor:component catalog.section my_template` |
| копирование шаблона в свою тему | `php artisan nexor:component catalog.section` |
| копирование всех шаблонов в свою тему | `php artisan nexor:component all` |
| `bitrix:catalog.section` | `<x-nexor::catalog.section />` |

Под компонентами лежит `Nexor\Cms\Services\InfoBlockService` — единая точка чтения инфоблоков: фильтры, сортировка, разделы, постраничность. Его можно звать и напрямую, когда компонента мало.

## Адреса элементов и предложений

У инфоблока выбирается **канонический адрес элемента** (карточка «Адреса на сайте»):

| Режим | Адрес |
|---|---|
| С разделами | `/katalog/razdel1/podrazdel/element1` |
| Без разделов | `/katalog/element1` |

Элемент открывается только по каноническому адресу; любой другой путь к нему — чужой или пропущенный раздел — отвечает 301 на канонический, query-строка сохраняется. У торгового предложения адрес товара плюс код предложения: `/katalog/razdel1/futbolka/krasnyy`. В шаблон детальной приходят `$element` и `$offer`, в компонент — `<x-nexor::catalog.element :element="$element" :offer="$offer" />`, в макет — `@yield('canonical')`.

Пример переключения предложений без перезагрузки (кнопки по свойству, подмена зон `data-offer-zone`, `history.pushState`, обновление `<title>` и canonical) — в шаблоне `catalog.element` после `php artisan nexor:component catalog.element`. Для canonical в `<head>` макета нужна строка `<link rel="canonical" href="@yield('canonical', url()->current())">`.

### Как товар выводит предложения

У товара с предложениями на вкладке **Предложения** есть переключатель **«Выводить через свойства»** (колонка `catalog_products.offers_by_properties`, по умолчанию включён):

| Переключатель | На странице товара | Адрес предложения |
|---|---|---|
| включён | кнопки вариантов, цена и «В корзину» у выбранного | свой: `/katalog/razdel1/futbolka/krasnyy` |
| выключен | товар, под ним список всех предложений, у каждого цена, количество и «В корзину» | `IblockElement::url()` отдаёт адрес товара, старые ссылки отвечают 301 на `/katalog/razdel1/futbolka` |

В шаблон `catalog.element` приходит `$offersByProperties`, по нему шаблон выбирает переключатель или список. В режиме списка `$offer` всегда `null`. В коде режим проверяет `CatalogProduct::listsOffers()`. Цена и наличие в обоих режимах выводятся общей частью `components/catalog/element/partials/price.blade.php`. Подпись варианта задаёт `$offerLabel` в начале шаблона: в пакете это название предложения, в своей копии можно подставить свойство, например `$item->property('SVOYSTVO1_PREDL') ?: $item->name`.

## Лицензии и модули

Уровень сайта задаётся в `.env`:

```dotenv
NEXOR_LICENSE=pro   # lite, standart или pro; непонятное значение считается lite
```

Старший уровень включает всё, что есть в младших. Функции и модули объявляют минимальный уровень, а проверка везде одна — `Nexor::feature('код')`:

```php
Nexor::feature('catalog.offers');   // функция ядра
Nexor::feature('shop');             // модуль включён и входит в лицензию
Nexor::feature('shop.promocodes');  // функция модуля
```

```blade
@feature('shop.checkout') … @endfeature
```

На маршрутах — middleware `nexor.feature:shop.promocodes`: недоступное отвечает 404. В панели пункты меню и страницы с `feature` пропадают сами.

| Функция | Lite | Standart | Pro |
|---|---|---|---|
| Инфоблоки, разделы, меню, компоненты | ✓ | ✓ | ✓ |
| Цены и остатки торгового каталога | ✓ | ✓ | ✓ |
| Торговые предложения | — | ✓ | ✓ |
| Корзина Basic, заказы | ✓ | ✓ | ✓ |
| Корзина Ultimate, промокоды, оформление с доставкой и оплатой | — | ✓ | ✓ |

**Модуль** — отдельный composer-пакет. В `register()` своего провайдера он регистрирует описание (`Nexor\Cms\Support\Modules\Module`): код, уровень лицензии, функции, права, файлы маршрутов API и сайта, настройки по умолчанию. Ядро подключает маршруты модуля под `nexor.feature:<код>`, добавляет его права в каталог ролей и показывает на странице Настройки → **Модули**, где модуль можно выключить. Выключение прячет маршруты, меню и компоненты, но данные модуля остаются.

Компоненты модулей в шаблонах подключаются явно и обёрнуты в проверку функции — по шаблону сразу видно, что к чему привязано:

```blade
@feature('shop')
    @if ($catalog->hasPrice() && ! ($catalog->usesOffers() && $element->iblock?->hasOffers()))
        <livewire:nexor-shop::add-to-cart :element-id="$element->id" />
    @endif
@endfeature
```

Нет модуля, он выключен или не входит в лицензию — блок просто не выводится. Так же выглядят и копии, которые забирает `nexor:component`.

## Модуль «Магазин»

Корзина, промокоды, оформление и заказы на Livewire 4. Настраивается в панели, группа **Магазин**.

**Корзина Basic** (с Lite): только цена, без остатков, товары штуками; форма заказа прямо в корзине. **Корзина Ultimate** (со Standart): учёт остатков и коэффициента, промокоды на странице корзины и отдельное оформление с доставкой и оплатой. Уровень переключается на странице Магазин → Корзина; Ultimate на Lite-сайте работает как Basic.

Установка из консоли:

```bash
php artisan nexor-shop:install                        # таблицы и права
php artisan nexor-shop:install --layout=site.layout   # плюс выезжающая корзина в макет
php artisan nexor-shop:component                      # список компонентов
php artisan nexor-shop:component cart-page            # забрать шаблон себе
```

Кнопку в шапку команда ставит только на место метки `{{-- nexor-shop:cart-button --}}` — где ей стоять, решает вёрстка шапки. Выезжающая корзина встаёт перед `</body>` сама.

### Свои шаблоны магазина: `nexor-shop:component`

```bash
php artisan nexor-shop:component                  # список компонентов и их тегов
php artisan nexor-shop:component cart-offcanvas   # один компонент вместе с его частями
php artisan nexor-shop:component all              # все шаблоны магазина
php artisan nexor-shop:component cart-page --force  # перезаписать уже скопированное
```

| Компонент | Тег | Что копируется |
|---|---|---|
| `add-to-cart` | `<livewire:nexor-shop::add-to-cart :element-id="$element->id" />` | кнопка и её вставки в карточку и детальную |
| `cart-button` | `<livewire:nexor-shop::cart-button />` | иконка со счётчиком |
| `cart-offcanvas` | `<livewire:nexor-shop::cart-offcanvas />` | панель, строки корзины, форма заказа, итог |
| `cart-page` | `<livewire:nexor-shop::cart-page />` | страница `/cart`, строки, форма, итог |
| `checkout` | `<livewire:nexor-shop::checkout />` | страница `/checkout`, форма, итог |

Файлы ложатся в `resources/views/vendor/nexor-shop/` и побеждают шаблоны пакета; обновление модуля их не трогает. Уже скопированное без `--force` не перезаписывается. Общие части (`partials/lines`, `partials/order-form`) едут с каждым компонентом, который их подключает, — править их можно один раз для всех.

Корзина синхронизируется между вкладками браузера: добавили товар в одной — счётчик, панель и страница корзины в других обновляются сами.

У выезжающей корзины есть переключатель «Открывать при добавлении». Выключен — «В корзину» просто кладёт товар, а покупателю об этом говорит текст в кнопке («Добавлено ✓») или всплывающее сообщение со ссылкой в корзину; способ выбирается там же.

На сайте — две строки в макете и две страницы:

```blade
<livewire:nexor-shop::cart-button />      {{-- в шапку: иконка со счётчиком --}}
<livewire:nexor-shop::cart-offcanvas />   {{-- в конец макета: выезжающая корзина --}}
```

| Адрес | Что там |
|---|---|
| `/cart` | корзина: у Basic с формой заказа, у Ultimate с промокодом |
| `/checkout` | оформление (только Ultimate) |

Кнопка «В корзину» уже стоит в шаблонах карточки и детальной страницы каталога — тегом `<livewire:nexor-shop::add-to-cart :element-id="$element->id" />` внутри `@feature('shop')`. В своём шаблоне — так же.

### Количество у кнопки «В корзину»

У кнопки два режима, выбираются атрибутом `mode`. Шаг и предел счётчика в обоих считает сервер (`CartPricing::limits()`): в Basic шаг 1 и предела нет, в Ultimate шаг — коэффициент товара, предел — остаток, если он учитывается и покупка без остатка запрещена.

**`button` — по умолчанию.** Рядом с кнопкой счётчик `− 1 +` и поле ввода. Количество меняется в браузере без запросов и уходит в корзину одним нажатием:

```blade
<livewire:nexor-shop::add-to-cart :element-id="$element->id" />
```

**`counter` — заготовка на будущее.** Пока товара нет в корзине, видна кнопка. После добавления на её месте счётчик, который меняет количество прямо в корзине, а на нуле убирает товар:

```blade
<livewire:nexor-shop::add-to-cart :element-id="$element->id" mode="counter" />
```

Где это лежит:

| Что | Где |
|---|---|
| Выбор режима, `add($quantity)`, `change($direction)` для `counter` | `packages/nexor-shop/src/Livewire/AddToCart.php` |
| Вёрстка обоих режимов: ветка `@if ($mode === 'counter' && $inCart > 0)` и счётчик Alpine для `button` | `packages/nexor-shop/resources/views/livewire/add-to-cart.blade.php` |
| Шаг и предел количества | `CartPricing::limits()` в `packages/nexor-shop/src/Support/CartPricing.php` |
| Своя копия шаблона кнопки | `resources/views/vendor/nexor-shop/livewire/add-to-cart.blade.php` (`php artisan nexor-shop:component add-to-cart`) |

В шаблон кнопки приходят `$mode`, `$step`, `$available` (сколько ещё можно добавить, `null` — без предела) и `$inCart`. В Alpine те же значения доступны как `$wire.step` и `$wire.available` и обновляются после каждого изменения корзины. Своя вёрстка счётчика должна отправлять количество через `$wire.add(qty)` в режиме `button` и через `wire:click="change(1)"` / `change(-1)` в режиме `counter`.

- **Корзина гостя** лежит в зашифрованной cookie: только id и количества. Цены каждый раз берутся из базы, подделать сумму через cookie нельзя.
- **Валюта магазина** выбирается на странице корзины. Цена товара в другой валюте пересчитывается по курсу оттуда же и помечается знаком ≈. Без курса товар купить нельзя; сменили валюту магазина — курсы вводятся заново.
- **Поля заказа** (ФИО, телефон, e-mail, комментарий) добавляются, удаляются и переставляются. Заказ хранит снимок полей, цен и названий — правка каталога старые заказы не меняет.
- **Промокоды:** процент или сумма, срок, лимит использований, минимальная сумма, действие на весь заказ, разделы с подразделами или отдельные товары.
- **Заказы:** статусы, комментарий менеджера; письма администратору и покупателю через почтовые шаблоны `SHOP_ORDER_ADMIN` и `SHOP_ORDER_CUSTOMER`. На Lite в заказе нет доставки и оплаты.
- **Telegram:** Магазин → Корзина → Уведомления — токен бота от @BotFather и chat id; каждый заказ приходит сообщением. Токен хранится зашифрованным, в панель уходит маской; кнопка «Отправить проверочное сообщение» объясняет ошибку Telegram по-русски. Упавший Telegram заказ не ломает.
- Ultimate при оформлении списывает остатки и блокирует строки: последний товар не продаётся дважды.

Шаблоны меняются копией в `resources/views/vendor/nexor-shop/`, макет страниц — `NEXOR_SHOP_LAYOUT`.

## Модуль «Конструктор страниц»

`packages/nexor-pagebuilder`, доступен на всех лицензиях. Детальная страница элемента собирается из блоков вместо подробного текста.

- Включается у инфоблока: «Параметры» → **«Использовать конструктор детальной страницы»**. У элементов появляется вкладка **«Конструктор»**: палитра блоков слева, страница справа, перетаскивание, дублирование, сворачивание.
- Блоки первой версии: заголовок, текст (1–2 колонки), цитата, текст + фото, фото / галерея (раскладки, «+N»), видео (ссылка или файл, три вида), аккордеон (с разметкой FAQ), таблица; у страницы — боковое меню со ссылками на блоки.
- Вёрстка блоков — классы `pb-*` / `nw-*`, как в исходном конструкторе; стили и скрипт модуля подключаются сами, свои — `NEXOR_PAGEBUILDER_ASSETS=false`.
- HTML чистится при сохранении, картинки загружает только тот, кто вправе менять элементы инфоблока.
- На сайте блоки выводятся в шаблонах `catalog.element`, `news.detail` и `site/page` внутри `@feature('pagebuilder')`; нет блоков — показывается подробный текст.
- Свой шаблон блока: `php artisan nexor-pagebuilder:component text`, все сразу — `all`.

Модуль встраивается через точки расширения ядра — `Module::iblockSettings()` (переключатели инфоблока) и `Module::elementFields()` / `elementRules()` / `elementValues()` / `saveElement()` (поля формы элемента), в панели — `Nexor.registerFormField()`. Подробности, свои блоки и план конструктора страниц сайта — в [packages/nexor-pagebuilder/README.md](packages/nexor-pagebuilder/README.md).

## Установка на чистый Laravel

```bash
composer require n1ce2k/nexor-cms
php artisan nexor:install
```

`nexor:install` проводит миграции, создаёт роли и администратора, кладёт в `resources/views/site` стартовые шаблоны сайта (только недостающие — свои не перезапишет) и дописывает в `resources/css/app.css` строки `@source` на шаблоны компонентов пакета, чтобы Tailwind сайта видел их классы.

- **Админка приходит уже собранной.** Скрипты и стили панели лежат в `vendor/n1ce2k/nexor-cms/dist` и отдаются адресом `/admin/nexor-assets/...` с долгим кешем. Node для админки не нужен, `composer update` приносит новую панель сразу.
- **Маршруты сайта тоже в пакете:** `/`, `/search`, `/robots.txt`, `/{раздел}` и `/{раздел}/{путь}` — это fallback-маршруты. Любой маршрут в `routes/web.php` с тем же адресом важнее. Выключить их целиком — `NEXOR_SITE_ROUTES=false`.

Магазин ставится так же:

```bash
composer require n1ce2k/nexor-shop
php artisan nexor-shop:install --layout=site.layout
```

И конструктор страниц:

```bash
composer require n1ce2k/nexor-pagebuilder
php artisan nexor-pagebuilder:install
```

Стили сайта собирает сам сайт: `npm install && npm run build`. Пароль администратора задаётся отдельно:

```bash
php artisan nexor:password admin@example.com
```

## Запуск этого репозитория

```bash
composer install
npm install
cp .env.example .env
php artisan key:generate
php artisan nexor:install
npm run build
php artisan serve
```

## Разработка

Правьте исходники в `packages/`, а не в `vendor/` — там симлинк на тот же каталог.

```bash
php artisan test --compact          # тесты
vendor/bin/pint --dirty             # форматирование
npm run dev                         # стили и скрипты сайта
```

### Правки панели (`.vue`, `resources/js`, `admin.css`)

Панель по умолчанию грузится из готовой сборки `packages/*/dist`, поэтому правка `.vue` сама по себе в браузере не видна. Для работы переключите панель на исходники — это касается ядра, магазина и конструктора разом:

```dotenv
NEXOR_PANEL_ASSETS=vite
```

После смены `.env` перезапустите `php artisan serve`, затем держите запущенным `npm run dev`: правки видны сразу. Без `npm run dev` панель в этом режиме открывается пустой.

> **Перед каждым коммитом с правками панели — `npm run build:packages`** и закоммитить `packages/*/dist` вместе с кодом. Сайты, которые ставят пакеты через Composer, получают только `dist/`: без сборки у вас всё работает (режим `vite`), а у них — старая панель.

Порядок перед коммитом:

1. Остановить `npm run dev` (на Windows он держит файлы в `resources/views`, и тесты шаблонов не могут вернуть их на место).
2. `npm run build:packages`.
3. `php artisan test --compact` и `vendor/bin/pint --dirty`.
4. Перед выпуском версии — открыть админку в режиме `dist` (`NEXOR_PANEL_ASSETS=dist` или без строки, перезапуск `php artisan serve`) и убедиться, что собранная панель работает.

Без сборки применяются сразу: PHP, Blade-шаблоны и `packages/nexor-pagebuilder/resources/assets/pagebuilder.{css,js}` (стили и скрипт блоков на сайте). Стили самого сайта (`resources/css/app.css`) собирает `npm run build` / `npm run dev`.

На боевом сервере `NEXOR_PANEL_ASSETS=vite` не ставить.

## Выпуск версии

Код правится **только здесь**, в `n1ce2k/nexor`. Репозитории `n1ce2k/nexor-cms`, `n1ce2k/nexor-shop` и `n1ce2k/nexor-pagebuilder` — копии только для чтения: их заполняет Action, ручные правки там затрутся следующим пуском.

```
n1ce2k/nexor ──(split.yml)──► n1ce2k/nexor-cms, nexor-shop, nexor-pagebuilder ──► Packagist ──► composer update на сайтах
```

1. Если менялась панель (`.vue`, `resources/js`, `admin.css`) — `npm run build:packages`, собранный `packages/*/dist` коммитится вместе с кодом.
2. `php artisan test --compact` и `vendor/bin/pint --dirty`.
3. Поднять `Nexor::VERSION` в `packages/nexor-cms/src/Support/Nexor.php`, закоммитить.
4. Поставить тег и отправить:

   ```bash
   git tag vX.Y.Z
   git push origin main --tags
   ```

5. GitHub Action [split.yml](.github/workflows/split.yml) переносит `packages/nexor-cms`, `packages/nexor-shop` и `packages/nexor-pagebuilder` в отдельные репозитории вместе с тегом. Проверить: вкладка **Actions** в `n1ce2k/nexor` — зелёные прогоны по ветке `main` и по тегу, а во всех репозиториях появился тег.
6. Packagist подхватывает версию сам, если в его настройках подключён GitHub. Иначе — кнопка **Update** на страницах пакетов.

**Если прогон по тегу не запустился** (так было с первым тегом, пришедшим в одном пуше с новым workflow) — отправить тот же тег заново:

```bash
git push origin :refs/tags/vX.Y.Z
git push origin vX.Y.Z
```

**Для работы нужно:** секрет `ACCESS_TOKEN` в `n1ce2k/nexor` → Settings → Secrets and variables → Actions — fine-grained токен GitHub с правом *Contents: Read and write* на `nexor-cms`, `nexor-shop` и `nexor-pagebuilder`. Новый пакет — новый пустой репозиторий, доступ к нему в токене и регистрация на Packagist. У токена есть срок: когда истечёт, Action упадёт с ошибкой доступа — выпустить новый и заменить секрет.

При смене минорной версии (0.2 → 0.3) поправьте `branch-alias` в `composer.json` обоих пакетов и требование `n1ce2k/nexor-cms` у магазина.

## Лицензия

MIT.
