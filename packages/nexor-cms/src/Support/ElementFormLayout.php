<?php

namespace Nexor\Cms\Support;

use Illuminate\Support\Collection;
use Illuminate\Support\Str;
use Nexor\Cms\Models\Iblock;
use Nexor\Cms\Models\IblockProperty;

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
            'preview_text_type' => ['label' => 'Формат анонса', 'tab' => 'preview'],
            'detail_text' => ['label' => 'Подробный текст', 'tab' => 'detail'],
            'detail_text_type' => ['label' => 'Формат текста', 'tab' => 'detail'],
            'meta_title' => ['label' => 'Заголовок страницы', 'tab' => 'seo'],
            'meta_description' => ['label' => 'Описание страницы', 'tab' => 'seo'],
            'meta_keywords' => ['label' => 'Ключевые слова', 'tab' => 'seo'],
            'sections' => ['label' => 'Разделы элемента', 'tab' => 'sections'],
        ];
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

        foreach (self::baseFields() as $key => $field) {
            $fields[$key] = ['key' => $key, 'label' => $field['label'], 'group' => 'base'];
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
        $orphans = array_values(array_diff($known, $placed));

        if ($orphans !== []) {
            $tabs[0]['fields'] = array_merge($tabs[0]['fields'], $orphans);
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

        foreach (self::defaultTabs() as $key => $label) {
            $fields = array_keys(array_filter(
                self::baseFields(),
                fn (array $field) => $field['tab'] === $key,
            ));

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
