<?php

namespace Nexor\Cms\Support;

use Illuminate\Support\Collection;
use Illuminate\Support\Str;
use Nexor\Cms\Models\Iblock;
use Nexor\Cms\Models\IblockProperty;
use Nexor\Cms\Support\Modules\ModuleFields;

/**
 * Which tabs the element form has, and which field sits on each of them.
 *
 * Bitrix lets an editor rearrange the element form per infoblock; so does this.
 * The layout lives in `iblocks.settings.form_tabs` and is merged with the
 * defaults on every read, so properties added later still show up and
 * properties that were deleted quietly disappear.
 */
class ElementFormLayout
{
    /** Where a stored layout is kept inside `iblocks.settings`. */
    public const KEY = 'form_tabs';

    /** Prefix that tells an infoblock property apart from a base field. */
    public const PROPERTY = 'prop:';

    /**
     * Base fields of every element, in the order the form shows them.
     *
     * @return array<string, array{label: string, tab: string}>
     */
    public static function baseFields(): array
    {
        return [
            'name' => ['label' => 'Название', 'tab' => 'main'],
            'code' => ['label' => 'Символьный код', 'tab' => 'main'],
            'section_id' => ['label' => 'Основной раздел', 'tab' => 'main'],
            'is_active' => ['label' => 'Активность', 'tab' => 'main'],
            'sort' => ['label' => 'Сортировка', 'tab' => 'main'],
            'active_from' => ['label' => 'Начало активности', 'tab' => 'main'],
            'active_to' => ['label' => 'Окончание активности', 'tab' => 'main'],
            'preview_text' => ['label' => 'Текст анонса', 'tab' => 'preview'],
            // Картинка идёт после формата: текст занимает строку целиком, а
            // формат и картинка встают рядом — формат слева, картинка справа.
            'preview_text_type' => ['label' => 'Формат анонса', 'tab' => 'preview'],
            'preview_picture' => ['label' => 'Картинка анонса', 'tab' => 'preview'],
            'detail_text' => ['label' => 'Подробный текст', 'tab' => 'detail'],
            'detail_text_type' => ['label' => 'Формат текста', 'tab' => 'detail'],
            'detail_picture' => ['label' => 'Картинка описания', 'tab' => 'detail'],
            'meta_title' => ['label' => 'Заголовок страницы', 'tab' => 'seo'],
            'meta_description' => ['label' => 'Описание страницы', 'tab' => 'seo'],
            'meta_keywords' => ['label' => 'Ключевые слова', 'tab' => 'seo'],
            'sections' => ['label' => 'Разделы элемента', 'tab' => 'sections'],
        ];
    }

    /**
     * Поля торгового каталога: у товаров и у их предложений.
     *
     * @return array<string, array{label: string, tab: string}>
     */
    public static function commerceFields(Iblock $iblock): array
    {
        $fields = [];

        // Тип товара выбирают только там, где предложения вообще бывают:
        // у самих предложений его нет.
        if ($iblock->hasOffers()) {
            // На «Основном»: от типа зависит, какие вкладки вообще будут.
            $fields['catalog.type'] = ['label' => 'Тип товара', 'tab' => 'main'];
        }

        return $fields + [
            'catalog.price' => ['label' => 'Цена', 'tab' => 'price'],
            'catalog.currency' => ['label' => 'Валюта', 'tab' => 'price'],
            'catalog.quantity' => ['label' => 'Доступное количество', 'tab' => 'stock'],
            'catalog.measure' => ['label' => 'Единица измерения', 'tab' => 'stock'],
            'catalog.ratio' => ['label' => 'Коэффициент', 'tab' => 'stock'],
            'catalog.quantity_trace' => ['label' => 'Количественный учёт', 'tab' => 'stock'],
            'catalog.can_buy_zero' => ['label' => 'Покупать при отсутствии', 'tab' => 'stock'],
            'catalog.discount_percent' => ['label' => 'Скидка', 'tab' => 'discount'],
        ];
    }

    /**
     * Вкладки торгового каталога и их порядок.
     *
     * @return array<string, string>
     */
    public static function commerceTabs(): array
    {
        return [
            'price' => 'Цена',
            'stock' => 'Остатки',
            'discount' => 'Скидки',
            'offers' => 'Предложения',
        ];
    }

    /**
     * Поля, которые есть у элементов этого инфоблока, с их вкладкой по умолчанию.
     *
     * @return array<string, array{label: string, tab: string}>
     */
    protected static function knownFields(Iblock $iblock): array
    {
        $fields = self::baseFields();

        if ($iblock->hasCommerce()) {
            $fields += self::commerceFields($iblock);
        }

        // Предложения бывают только у товара, у самих предложений их нет.
        if ($iblock->hasOffers()) {
            $fields['offers'] = ['label' => 'Торговые предложения', 'tab' => 'offers'];
        }

        // Поля модулей — у каждого своя вкладка, например «Конструктор».
        foreach (ModuleFields::elementFields($iblock) as $key => $field) {
            $fields[$key] = ['label' => $field['label'], 'tab' => $field['tab']];
        }

        return $fields;
    }

    /**
     * Вкладки, которые появляются не у всех: торговые и вкладки модулей.
     *
     * @return array<string, string>
     */
    protected static function extraTabs(Iblock $iblock): array
    {
        $tabs = self::commerceTabs();

        foreach (ModuleFields::elementFields($iblock) as $field) {
            $tabs[$field['tab']] ??= $field['tab_label'];
        }

        return $tabs;
    }

    /**
     * Default tabs, with their default labels and order.
     *
     * @return array<string, string>
     */
    public static function defaultTabs(): array
    {
        return [
            'main' => 'Основное',
            'seo' => 'SEO',
            'preview' => 'Анонс',
            'detail' => 'Описание',
            'sections' => 'Разделы',
        ];
    }

    /**
     * Every field this infoblock's form can place, keyed by field key.
     *
     * @return array<string, array{key: string, label: string, group: string}>
     */
    public static function fields(Iblock $iblock): array
    {
        $fields = [];

        foreach (self::knownFields($iblock) as $key => $field) {
            $group = match (true) {
                str_starts_with($key, 'catalog.') || $key === 'offers' => 'catalog',
                str_starts_with($key, ModuleFields::PREFIX) => 'module',
                default => 'base',
            };

            $fields[$key] = ['key' => $key, 'label' => $field['label'], 'group' => $group];
        }

        foreach (self::properties($iblock) as $property) {
            $key = self::PROPERTY.$property->code;

            $fields[$key] = ['key' => $key, 'label' => $property->name, 'group' => 'property'];
        }

        return $fields;
    }

    /**
     * The layout to render: whatever is stored, reconciled with what exists.
     *
     * @return array<int, array{key: string, label: string, fields: array<int, string>}>
     */
    public static function for(Iblock $iblock): array
    {
        $known = array_keys(self::fields($iblock));
        $stored = self::stored($iblock);
        $tabs = $stored === [] ? self::defaults($iblock) : $stored;

        $placed = [];

        foreach ($tabs as $index => $tab) {
            $fields = array_values(array_filter(
                $tab['fields'],
                fn (string $field) => in_array($field, $known, true) && ! in_array($field, $placed, true),
            ));

            $placed = array_merge($placed, $fields);
            $tabs[$index]['fields'] = $fields;
        }

        // A property added after the layout was saved still has to be editable.
        // Торговые поля при этом едут на свои вкладки, а не сваливаются на первую:
        // инфоблок могли сделать каталогом уже после настройки формы.
        $orphans = array_values(array_diff($known, $placed));
        $defaults = self::knownFields($iblock);
        $extraTabs = self::extraTabs($iblock);

        foreach ($orphans as $field) {
            $tabKey = $defaults[$field]['tab'] ?? null;
            $index = $tabKey === null ? false : array_search($tabKey, array_column($tabs, 'key'), true);

            if ($index === false && $tabKey !== null && isset($extraTabs[$tabKey])) {
                $tabs[] = ['key' => $tabKey, 'label' => $extraTabs[$tabKey], 'fields' => []];
                $index = array_key_last($tabs);
            }

            $tabs[$index === false ? 0 : $index]['fields'][] = $field;
        }

        return array_values($tabs);
    }

    /**
     * The out-of-the-box layout: default tabs, properties on the first one.
     *
     * @return array<int, array{key: string, label: string, fields: array<int, string>}>
     */
    public static function defaults(Iblock $iblock): array
    {
        $tabs = [];

        $known = self::knownFields($iblock);
        $tabList = self::defaultTabs();

        if ($iblock->hasCommerce()) {
            $tabList += self::commerceTabs();
        }

        $tabList += self::extraTabs($iblock);

        foreach ($tabList as $key => $label) {
            $fields = array_keys(array_filter(
                $known,
                fn (array $field) => $field['tab'] === $key,
            ));

            // Вкладка без полей — например, «Предложения» у самих предложений.
            if ($fields === [] && ! isset(self::defaultTabs()[$key])) {
                continue;
            }

            if ($key === 'main') {
                foreach (self::properties($iblock) as $property) {
                    $fields[] = self::PROPERTY.$property->code;
                }
            }

            $tabs[] = ['key' => $key, 'label' => $label, 'fields' => array_values($fields)];
        }

        return $tabs;
    }

    /**
     * Save a layout, or drop it back to the defaults when no tab is given.
     *
     * @param  array<int, array{key?: string, label?: string, fields?: array<int, string>}>  $tabs
     * @return array<int, array{key: string, label: string, fields: array<int, string>}>
     */
    public static function store(Iblock $iblock, array $tabs): array
    {
        $settings = $iblock->settings ?? [];

        if ($tabs === []) {
            unset($settings[self::KEY]);
        } else {
            $settings[self::KEY] = self::sanitise($tabs);
        }

        $iblock->forceFill(['settings' => $settings === [] ? null : $settings])->save();

        return self::for($iblock->refresh());
    }

    /**
     * @param  array<int, mixed>  $tabs
     * @return array<int, array{key: string, label: string, fields: array<int, string>}>
     */
    protected static function sanitise(array $tabs): array
    {
        $clean = [];
        $used = [];

        foreach ($tabs as $index => $tab) {
            if (! is_array($tab)) {
                continue;
            }

            $label = trim((string) ($tab['label'] ?? '')) ?: 'Вкладка '.($index + 1);
            $key = Str::slug((string) ($tab['key'] ?? ''), '_') ?: Str::slug($label, '_') ?: 'tab_'.($index + 1);

            // Two tabs sharing a key would fight over the same fields.
            while (in_array($key, $used, true)) {
                $key .= '_'.($index + 1);
            }

            $used[] = $key;

            $clean[] = [
                'key' => $key,
                'label' => $label,
                'fields' => array_values(array_unique(array_map(
                    'strval',
                    array_filter((array) ($tab['fields'] ?? []), 'is_scalar'),
                ))),
            ];
        }

        return $clean;
    }

    /**
     * @return array<int, array{key: string, label: string, fields: array<int, string>}>
     */
    protected static function stored(Iblock $iblock): array
    {
        $stored = $iblock->settings[self::KEY] ?? [];

        return is_array($stored) && $stored !== [] ? self::sanitise($stored) : [];
    }

    /**
     * @return Collection<int, IblockProperty>
     */
    protected static function properties(Iblock $iblock): Collection
    {
        return $iblock->properties()->active()->get();
    }
}
