<?php

namespace Nexor\Cms\Enums;

/**
 * Types available for infoblock properties.
 *
 * Every case declares which column of `iblock_element_values` stores its value,
 * so the storage layer never has to branch on the type name itself.
 */
enum PropertyType: string
{
    case String = 'string';
    case Text = 'text';
    case Html = 'html';
    case Integer = 'integer';
    case Decimal = 'decimal';
    case Boolean = 'boolean';
    case Date = 'date';
    case DateTime = 'datetime';
    case Color = 'color';
    case Select = 'select';
    case File = 'file';
    case Image = 'image';
    case Element = 'element';
    case Section = 'section';
    case User = 'user';
    case Json = 'json';

    public function label(): string
    {
        return match ($this) {
            self::String => 'Строка',
            self::Text => 'Текст',
            self::Html => 'HTML / визуальный редактор',
            self::Integer => 'Целое число',
            self::Decimal => 'Дробное число',
            self::Boolean => 'Да / Нет',
            self::Date => 'Дата',
            self::DateTime => 'Дата и время',
            self::Color => 'Цвет (HEX)',
            self::Select => 'Список',
            self::File => 'Файл',
            self::Image => 'Изображение',
            self::Element => 'Привязка к элементу',
            self::Section => 'Привязка к разделу',
            self::User => 'Привязка к пользователю',
            self::Json => 'JSON',
        };
    }

    public function group(): string
    {
        return match ($this) {
            self::String, self::Text, self::Html, self::Color => 'Текстовые',
            self::Integer, self::Decimal, self::Boolean => 'Числовые',
            self::Date, self::DateTime => 'Дата и время',
            self::Select => 'Списки',
            self::File, self::Image => 'Файлы',
            self::Element, self::Section, self::User => 'Связи',
            self::Json => 'Служебные',
        };
    }

    /**
     * Column of `iblock_element_values` that holds this type's value.
     */
    public function column(): string
    {
        return match ($this) {
            self::String, self::Color, self::File, self::Image => 'value_string',
            self::Text, self::Html => 'value_text',
            self::Integer => 'value_int',
            self::Decimal => 'value_decimal',
            self::Boolean => 'value_bool',
            self::Date, self::DateTime => 'value_date',
            self::Select => 'value_enum_id',
            self::Element => 'value_element_id',
            self::Section => 'value_section_id',
            self::User => 'value_user_id',
            self::Json => 'value_json',
        };
    }

    /**
     * Whether the property editor needs the list of enum values.
     */
    public function usesEnums(): bool
    {
        return $this === self::Select;
    }

    public function isFile(): bool
    {
        return in_array($this, [self::File, self::Image], true);
    }

    /**
     * Whether values of this type can be used in list filters.
     */
    public function isFilterable(): bool
    {
        return $this !== self::Json;
    }

    /**
     * Settings keys this type understands, used to render the property editor.
     *
     * @return array<int, string>
     */
    public function settingKeys(): array
    {
        return match ($this) {
            self::String => ['placeholder', 'max_length', 'pattern'],
            self::Text, self::Html => ['placeholder', 'rows'],
            self::Integer, self::Decimal => ['min', 'max', 'step', 'suffix'],
            self::File => ['accept', 'max_size'],
            self::Image => ['accept', 'max_size', 'max_width', 'max_height'],
            self::Element => ['link_iblock_id'],
            self::Section => ['link_iblock_id'],
            default => [],
        };
    }

    /**
     * @return array<string, array<int, self>>
     */
    public static function grouped(): array
    {
        $grouped = [];

        foreach (self::cases() as $case) {
            $grouped[$case->group()][] = $case;
        }

        return $grouped;
    }
}
