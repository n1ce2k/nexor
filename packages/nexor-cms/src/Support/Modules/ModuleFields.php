<?php

namespace Nexor\Cms\Support\Modules;

use Nexor\Cms\Models\Iblock;
use Nexor\Cms\Models\IblockElement;
use Nexor\Cms\Support\Nexor;

/**
 * Что модули добавляют в формы инфоблока и элемента.
 *
 * Ядро не знает, какие модули стоят на сайте: оно спрашивает каждый включённый
 * модуль, какие у него переключатели инфоблока и поля элемента, и само кладёт
 * их в форму, проверку и сохранение. Выключенный модуль из форм пропадает,
 * а его значения остаются в базе.
 */
class ModuleFields
{
    /** Префикс поля модуля в раскладке формы элемента: `module:pagebuilder.content`. */
    public const PREFIX = 'module:';

    /**
     * Переключатели включённых модулей для карточки «Параметры».
     *
     * @return array<int, array{module: string, key: string, label: string, hint: string, default: bool}>
     */
    public static function iblockSettings(): array
    {
        $settings = [];

        foreach (self::enabledModules() as $module) {
            foreach ($module->iblockSettings() as $key => $definition) {
                $settings[] = [
                    'module' => $module->code(),
                    'key' => $key,
                    'label' => $definition['label'],
                    'hint' => $definition['hint'] ?? '',
                    'default' => (bool) ($definition['default'] ?? false),
                ];
            }
        }

        return $settings;
    }

    /**
     * Текущие значения переключателей у инфоблока: `['pagebuilder' => ['detail' => true]]`.
     *
     * @return array<string, array<string, bool>>
     */
    public static function iblockSettingValues(Iblock $iblock): array
    {
        $values = [];

        foreach (self::iblockSettings() as $setting) {
            $values[$setting['module']][$setting['key']] = (bool) $iblock->moduleSetting(
                $setting['module'], $setting['key'], $setting['default'],
            );
        }

        return $values;
    }

    /**
     * @return array<string, array<int, string>>
     */
    public static function iblockSettingRules(): array
    {
        $rules = ['module_settings' => ['nullable', 'array']];

        foreach (self::iblockSettings() as $setting) {
            $rules["module_settings.{$setting['module']}.{$setting['key']}"] = ['boolean'];
        }

        return $rules;
    }

    /**
     * Записывает пришедшие переключатели. Настройки выключенных модулей и
     * всё прочее в `settings` не трогаются.
     *
     * @param  array<string, mixed>  $input
     */
    public static function applyIblockSettings(Iblock $iblock, array $input): void
    {
        $settings = $iblock->settings ?? [];

        foreach (self::iblockSettings() as $setting) {
            if (! isset($input[$setting['module']]) || ! array_key_exists($setting['key'], (array) $input[$setting['module']])) {
                continue;
            }

            $settings['modules'][$setting['module']][$setting['key']] = filter_var(
                $input[$setting['module']][$setting['key']], FILTER_VALIDATE_BOOLEAN,
            );
        }

        $iblock->settings = $settings === [] ? null : $settings;
    }

    /**
     * Поля элемента от модулей, ключами уже в виде раскладки формы.
     *
     * @return array<string, array{module: string, key: string, label: string, tab: string, tab_label: string}>
     */
    public static function elementFields(Iblock $iblock): array
    {
        $fields = [];

        foreach (self::enabledModules() as $module) {
            foreach ($module->elementFields($iblock) as $key => $field) {
                $fields[self::PREFIX.$module->code().'.'.$key] = $field + [
                    'module' => $module->code(),
                    'key' => $key,
                ];
            }
        }

        return $fields;
    }

    /**
     * @return array<string, mixed>
     */
    public static function elementRules(Iblock $iblock): array
    {
        $rules = [];

        foreach (self::enabledModules() as $module) {
            if ($module->elementFields($iblock) === []) {
                continue;
            }

            foreach ($module->elementRules($iblock) as $key => $rule) {
                $rules["modules.{$module->code()}.{$key}"] = $rule;
            }
        }

        return $rules;
    }

    /**
     * Значения полей модулей для формы: `['pagebuilder' => ['content' => …]]`.
     *
     * @return array<string, array<string, mixed>>
     */
    public static function elementValues(IblockElement $element): array
    {
        $values = [];
        $iblock = $element->iblock;

        foreach (self::enabledModules() as $module) {
            if ($iblock && $module->elementFields($iblock) !== []) {
                $values[$module->code()] = $module->elementValues($element);
            }
        }

        return $values;
    }

    /**
     * Отдаёт модулям их проверенные значения после сохранения элемента.
     *
     * @param  array<string, mixed>  $validated
     */
    public static function saveElement(Iblock $iblock, IblockElement $element, array $validated): void
    {
        foreach (self::enabledModules() as $module) {
            $fields = $module->elementFields($iblock);
            $sent = $validated['modules'][$module->code()] ?? null;

            if ($fields === [] || ! is_array($sent)) {
                continue;
            }

            $values = array_intersect_key($sent, $fields);

            if ($values !== []) {
                $module->saveElement($element, $values);
            }
        }
    }

    /**
     * @return array<int, Module>
     */
    protected static function enabledModules(): array
    {
        return array_values(array_filter(
            Nexor::modules()->all(),
            fn (Module $module) => Nexor::modules()->enabled($module->code()),
        ));
    }
}
