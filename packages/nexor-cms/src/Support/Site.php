<?php

namespace Nexor\Cms\Support;

use Illuminate\Pagination\LengthAwarePaginator;
use Illuminate\Pagination\Paginator;
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

    /**
     * Active elements of an infoblock, one page at a time.
     *
     * The page size comes from the infoblock itself, so «Показать ещё» and the
     * paging templates stay configurable from the panel.
     *
     * @return LengthAwarePaginator<int, IblockElement>
     */
    public static function paginate(string $iblockCode, ?int $perPage = null): LengthAwarePaginator
    {
        $iblock = self::iblock($iblockCode);

        if (! $iblock) {
            return new LengthAwarePaginator([], 0, $perPage ?: 20, Paginator::resolveCurrentPage());
        }

        return $iblock->elements()
            ->active()
            ->with(['values.property', 'values.enum'])
            ->ordered()
            ->paginate($perPage ?: $iblock->pageSize())
            ->withQueryString();
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
