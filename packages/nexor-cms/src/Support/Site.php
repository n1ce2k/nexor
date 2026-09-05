<?php

namespace Nexor\Cms\Support;

use Illuminate\Support\Collection;
use Nexor\Cms\Models\Iblock;
use Nexor\Cms\Models\IblockElement;

/**
 * Read helpers for the public part of the site.
 *
 * Everything the front end shows comes out of infoblocks, so these helpers wrap
 * the two lookups templates keep needing: an infoblock by code, and its elements.
 */
class Site
{
    public const PAGES = 'pages';

    public static function iblock(string $code): ?Iblock
    {
        return Iblock::query()->active()->where('code', $code)->first();
    }

    /**
     * Active elements of an infoblock, newest first unless sorted explicitly.
     *
     * @return Collection<int, IblockElement>
     */
    public static function elements(string $iblockCode, int $limit = 50): Collection
    {
        $iblock = self::iblock($iblockCode);

        if (! $iblock) {
            return collect();
        }

        return $iblock->elements()
            ->active()
            ->with(['values.property', 'values.enum'])
            ->ordered()
            ->limit($limit)
            ->get();
    }

    public static function element(string $iblockCode, string $elementCode): ?IblockElement
    {
        $iblock = self::iblock($iblockCode);

        return $iblock?->elements()
            ->active()
            ->where('code', $elementCode)
            ->with(['values.property', 'values.enum'])
            ->first();
    }

    /**
     * Pages flagged to appear in the site menu, ordered by their menu position.
     *
     * @return Collection<int, IblockElement>
     */
    public static function menu(): Collection
    {
        return self::elements(self::PAGES, 100)
            ->filter(fn (IblockElement $page) => (bool) $page->property('show_in_menu'))
            ->sortBy(fn (IblockElement $page) => (int) ($page->property('menu_sort') ?? 500))
            ->values();
    }
}
