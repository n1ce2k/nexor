<?php

namespace Nexor\Cms\Enums;

/**
 * Чем может быть пункт меню.
 *
 * Типы `Page` и `Section` хранят ссылку на сущность, а адрес считают при
 * выводе — переименование символьного кода их не ломает, в отличие от
 * записанного руками URL.
 */
enum MenuItemType: string
{
    case Link = 'link';
    case Page = 'page';
    case Section = 'section';
    case Sections = 'sections';
    case Heading = 'heading';
    case Divider = 'divider';

    public function label(): string
    {
        return match ($this) {
            self::Link => 'Ссылка',
            self::Page => 'Страница',
            self::Section => 'Раздел',
            self::Sections => 'Разделы инфоблока',
            self::Heading => 'Заголовок',
            self::Divider => 'Разделитель',
        };
    }

    public function hint(): string
    {
        return match ($this) {
            self::Link => 'Название и адрес вручную — для внешних ссылок и якорей.',
            self::Page => 'Элемент инфоблока; адрес считается сам.',
            self::Section => 'Раздел инфоблока; адрес считается сам.',
            self::Sections => 'Развернётся в дерево разделов при выводе меню.',
            self::Heading => 'Подпись без ссылки — для больших меню в подвале.',
            self::Divider => 'Горизонтальная черта между пунктами.',
        };
    }

    /**
     * Разворачивается ли пункт в поддерево при выводе.
     */
    public function isDynamic(): bool
    {
        return $this === self::Sections;
    }

    /**
     * Ведёт ли пункт куда-нибудь: заголовок и разделитель — нет.
     */
    public function isLink(): bool
    {
        return in_array($this, [self::Link, self::Page, self::Section], true);
    }

    /**
     * Может ли у пункта быть вложенное меню, собранное руками.
     */
    public function acceptsChildren(): bool
    {
        return $this !== self::Divider && ! $this->isDynamic();
    }

    /**
     * Поля, которые показывает форма пункта этого типа.
     *
     * @return array<int, string>
     */
    public function fields(): array
    {
        return match ($this) {
            self::Link => ['title', 'url', 'target'],
            self::Page => ['iblock_id', 'element_id', 'title'],
            self::Section => ['iblock_id', 'section_id', 'title'],
            self::Sections => ['iblock_id', 'section_id', 'max_depth', 'with_elements'],
            self::Heading => ['title'],
            self::Divider => [],
        };
    }

    /**
     * @return array<int, array{value: string, label: string, hint: string}>
     */
    public static function options(): array
    {
        return array_map(fn (self $case) => [
            'value' => $case->value,
            'label' => $case->label(),
            'hint' => $case->hint(),
        ], self::cases());
    }
}
