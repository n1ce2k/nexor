<?php

namespace Nexor\Cms\Enums;

/**
 * Layouts available for the public listing of an infoblock.
 *
 * The chosen one is scaffolded next to the page as `pagination.blade.php`, so a
 * site can rewrite it afterwards without touching the CMS.
 */
enum PaginationTemplate: string
{
    case Simple = 'pagination';
    case Full = 'pagination_full';
    case ButtonLoad = 'pagination_btnload';

    public function label(): string
    {
        return match ($this) {
            self::Simple => 'Обычная — номера страниц',
            self::Full => 'Полная — номера, «первая/последняя» и счётчик',
            self::ButtonLoad => 'Кнопка «Показать ещё» вместо номеров',
        };
    }

    public function hint(): string
    {
        return match ($this) {
            self::Simple => 'Компактный ряд номеров со стрелками.',
            self::Full => 'То же плюс переходы в начало и в конец и подпись «показано N из M».',
            self::ButtonLoad => 'Номеров нет — следующая порция подгружается кнопкой.',
        };
    }

    /**
     * Name of the `pagination` component template this case selects.
     *
     * The stored value keeps its Bitrix-ish shape, the component only needs the
     * short half — `pagination_full` picks `full.blade.php`.
     */
    public function template(): string
    {
        return match ($this) {
            self::Simple => 'default',
            self::Full => 'full',
            self::ButtonLoad => 'btnload',
        };
    }

    /**
     * Whether the template already loads more items by itself, which makes the
     * separate "показать ещё" switch redundant.
     */
    public function loadsOnDemand(): bool
    {
        return $this === self::ButtonLoad;
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
