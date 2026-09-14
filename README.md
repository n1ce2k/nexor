# NEXOR

CMS на Laravel: админ-панель с инфоблоками в стиле Битрикса, свойствами произвольных типов и собственной системой ролей. Без Filament, Orchid и сторонних пакетов прав.

Репозиторий содержит два слоя:

| | |
|---|---|
| `packages/nexor-cms` | Ядро CMS — composer-пакет `n1ce2k/nexor-cms`, namespace `Nexor\Cms` |
| `packages/nexor-shop` | Модуль «Магазин» — composer-пакет `n1ce2k/nexor-shop`, namespace `Nexor\Shop` |
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

- **Корзина гостя** лежит в зашифрованной cookie: только id и количества. Цены каждый раз берутся из базы, подделать сумму через cookie нельзя.
- **Валюта магазина** выбирается на странице корзины. Цена товара в другой валюте пересчитывается по курсу оттуда же и помечается знаком ≈. Без курса товар купить нельзя; сменили валюту магазина — курсы вводятся заново.
- **Поля заказа** (ФИО, телефон, e-mail, комментарий) добавляются, удаляются и переставляются. Заказ хранит снимок полей, цен и названий — правка каталога старые заказы не меняет.
- **Промокоды:** процент или сумма, срок, лимит использований, минимальная сумма, действие на весь заказ, разделы с подразделами или отдельные товары.
- **Заказы:** статусы, комментарий менеджера; письма администратору и покупателю через почтовые шаблоны `SHOP_ORDER_ADMIN` и `SHOP_ORDER_CUSTOMER`. На Lite в заказе нет доставки и оплаты.
- **Telegram:** Магазин → Корзина → Уведомления — токен бота от @BotFather и chat id; каждый заказ приходит сообщением. Токен хранится зашифрованным, в панель уходит маской; кнопка «Отправить проверочное сообщение» объясняет ошибку Telegram по-русски. Упавший Telegram заказ не ломает.
- Ultimate при оформлении списывает остатки и блокирует строки: последний товар не продаётся дважды.

Шаблоны меняются копией в `resources/views/vendor/nexor-shop/`, макет страниц — `NEXOR_SHOP_LAYOUT`.

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
