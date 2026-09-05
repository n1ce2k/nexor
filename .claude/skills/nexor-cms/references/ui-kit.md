# UI-кит админки

Все компоненты — в `packages/nexor-cms/resources/views/components/admin/`, теги пишутся с неймспейсом: `<x-nexor::admin.card>`. Проверь этот список прежде чем писать новый.

## Каркас страницы

```blade
@extends('nexor::admin.layouts.app')
@section('title', 'Заголовок вкладки')

@section('content')
    <x-nexor::admin.page-header title="Заголовок" description="Подзаголовок" :back="route('admin.foo.index')">
        <x-slot:breadcrumbs>
            <x-nexor::admin.breadcrumbs :items="['Раздел' => route('admin.foo.index'), 'Текущая' => null]" />
        </x-slot:breadcrumbs>
        <x-slot:actions>
            <x-nexor::admin.button :href="route('admin.foo.create')" icon="plus">Добавить</x-nexor::admin.button>
        </x-slot:actions>
    </x-nexor::admin.page-header>

    <x-nexor::admin.card title="Блок" :padding="false">…</x-nexor::admin.card>
@endsection
```

`nexor::admin.layouts.app` — админка, `nexor::admin.layouts.auth` — вход. Тема (светлая/тёмная) ставится до отрисовки, поэтому мигания нет.

## Каталог

| Компонент | Ключевые пропсы |
|---|---|
| `x-nexor::admin.page-header` | `title`, `description`, `back`; слоты `breadcrumbs`, `actions` |
| `x-nexor::admin.breadcrumbs` | `items` — массив `подпись => url\|null` |
| `x-nexor::admin.card` | `title`, `description`, `padding`; слоты `actions`, `footer` |
| `x-nexor::admin.button` | `variant` (primary/secondary/danger/ghost/link), `size` (sm/md/lg/icon), `href`, `icon`, `type` |
| `x-nexor::admin.icon` | `name` — 30 иконок, список внутри компонента |
| `x-nexor::admin.badge` | `color` (gray/green/red/amber/blue/violet), `icon` |
| `x-nexor::admin.field` | `label`, `name`, `hint`, `required` — обёртка, сама рисует ошибку валидации |
| `x-nexor::admin.input` | `name`, `type`, `value`, `prefix`, `suffix` |
| `x-nexor::admin.textarea` | `name`, `value`, `rows` |
| `x-nexor::admin.select` | `name`, `options`, `selected`, `placeholder`, `multiple`; `options` умеет вложенные группы |
| `x-nexor::admin.checkbox` | `name`, `value`, `checked`, `label`, `hint`, `hidden` |
| `x-nexor::admin.toggle` | `name`, `checked`, `label`, `hint` |
| `x-nexor::admin.color-input` | `name`, `value` — свотч + нативный пикер + HEX-поле |
| `x-nexor::admin.file-input` | `name`, `value`, `accept`, `image` — превью и флаг `{name}_remove` |
| `x-nexor::admin.property-field` | `property`, `value` — **рисует редактор под тип свойства инфоблока** |
| `x-nexor::admin.table` | слот `head`; внутри `x-admin.table.heading`, `.row`, `.cell` |
| `x-nexor::admin.table.heading` | `sort` — включает сортировку по колонке, `align`, `width` |
| `x-nexor::admin.table.row` | `id` — попадает в `data-row-id` для массовых действий |
| `x-nexor::admin.table.cell` | `align`, `muted` |
| `x-nexor::admin.pagination` | `paginator` |
| `x-nexor::admin.filters` | `placeholder`; в слот кладутся дополнительные фильтры |
| `x-nexor::admin.empty-state` | `icon`, `title`, `description`; в слот — кнопка действия |
| `x-nexor::admin.modal` | `title`, `maxWidth`, `show` — имя переменной Alpine |
| `x-nexor::admin.delete-button` | `action`, `title`, `message`, `iconOnly` — кнопка + подтверждение |
| `x-nexor::admin.dropdown` / `x-admin.dropdown-item` | `align`, `width` / `href`, `icon`, `danger` |
| `x-nexor::admin.toasts` | без пропсов, лежит в layout, забирает flash-сообщения |

## Цвета и тема

Не пиши `bg-white` / `text-slate-900` напрямую — бери семантические токены, тогда тёмная тема заработает сама:

`--surface-page`, `--surface-panel`, `--surface-muted`, `--surface-border`, `--surface-border-strong`, `--text-strong`, `--text-base`, `--text-muted`, `--text-faint`, плюс `--sidebar-*`.

В классах: `bg-[var(--surface-panel)]`, `text-[var(--text-muted)]`. Готовые классы: `.surface`, `.field-input`, `.table-head`, `.table-row`, `.sidebar-link`.

Акцент — палитра `brand-50…950`, у неё обычные утилиты Tailwind: `bg-brand-600`, `text-brand-400`.

## Alpine

Компоненты объявлены в `packages/nexor-cms/resources/js/admin/components.js`:

| Данные | Зачем |
|---|---|
| `slugField(initial)` | Транслитерация названия в символьный код, пока код не правили руками |
| `fileField(url)` | Превью файла и флаг удаления |
| `repeater(rows, blank)` | Повторяющиеся строки: варианты списка, множественные значения |
| `colorField(initial)` | Синхронизация свотча, пикера и HEX-поля |
| `bulkSelect()` | Чекбоксы в таблице |
| `confirmAction()` | Подтверждение опасного действия |

Сторы: `$store.theme`, `$store.sidebar`, `$store.toasts`. Уведомление из JS — `notify('текст', 'success')`.

## Формы: рабочий шаблон

```blade
<form method="POST" action="…" enctype="multipart/form-data"
      x-data="slugField(@js(old('code', $model->code)))"
      class="grid gap-6 lg:grid-cols-3">
    @csrf
    @if ($model->exists) @method('PUT') @endif

    <div class="space-y-6 lg:col-span-2">…основное…</div>
    <div class="space-y-6">…параметры, статус, кнопки…</div>
</form>
```

Значения полей всегда через `old('field', $model->field)`. Имя поля в `x-admin.field` пишется в точечной нотации (`properties.CODE`), в самом инпуте — в скобочной (`properties[CODE]`).
