<?php

namespace Nexor\Cms\Support;

use Illuminate\Support\Facades\DB;
use Nexor\Cms\Models\Iblock;
use Nexor\Cms\Models\Role;

/**
 * Превращает инфоблок в торговый каталог — как «Торговый каталог» в Битриксе.
 *
 * Включение заводит соседний инфоблок торговых предложений и связывает их в
 * обе стороны. Выключение ничего не удаляет: предложения — это данные, и
 * случайно снятая галочка не должна их уничтожить. Повторное включение
 * подхватывает уже существующий инфоблок, а не плодит дубли.
 */
class CatalogManager
{
    /** Как подписывается инфоблок предложений. */
    public const OFFERS_PREFIX = 'Предложения — ';

    /**
     * Приводит связку в соответствие с галочкой «Торговый каталог».
     *
     * @return Iblock|null Инфоблок предложений, если каталог включён
     */
    public static function sync(Iblock $iblock): ?Iblock
    {
        // Предложения сами каталогом с предложениями быть не могут.
        if (! $iblock->is_catalog || $iblock->product_iblock_id) {
            return null;
        }

        $offers = $iblock->offersIblock;

        if ($offers) {
            self::keepName($iblock, $offers);

            return $offers;
        }

        return DB::transaction(fn () => self::createOffers($iblock));
    }

    protected static function createOffers(Iblock $iblock): Iblock
    {
        $offers = Iblock::query()->create([
            'iblock_type_id' => $iblock->iblock_type_id,
            'code' => self::freeCode($iblock->code.'-offers'),
            'name' => self::OFFERS_PREFIX.$iblock->name,
            'element_name' => 'предложение',
            'has_sections' => false,
            'has_page' => false,
            'is_active' => true,
            'sort' => $iblock->sort + 1,
        ]);

        $offers->forceFill(['product_iblock_id' => $iblock->id])->save();
        $iblock->forceFill(['offers_iblock_id' => $offers->id])->save();

        self::mirrorGrants($iblock, $offers);

        return $offers;
    }

    /**
     * Кто работал с каталогом, тот работает и с его предложениями.
     *
     * Без этого менеджер, которому выдали права на товары, упирался бы в 403 на
     * вкладке «Предложения» — права на новый инфоблок не выдаются сами.
     */
    protected static function mirrorGrants(Iblock $product, Iblock $offers): void
    {
        foreach (Iblock::abilities() as $ability) {
            $from = $product->permissionCode($ability);
            $to = $offers->permissionCode($ability);

            $target = DB::table('permissions')->where('code', $to)->value('id');

            if (! $target) {
                continue;
            }

            Role::query()
                ->whereHas('permissions', fn ($query) => $query->where('code', $from))
                ->each(fn (Role $role) => $role->permissions()->syncWithoutDetaching([$target]));
        }
    }

    /**
     * Название предложений следует за каталогом, пока его не переименовали вручную.
     */
    protected static function keepName(Iblock $product, Iblock $offers): void
    {
        $expected = self::OFFERS_PREFIX.$product->name;

        if ($offers->name !== $expected && str_starts_with($offers->name, self::OFFERS_PREFIX)) {
            $offers->update(['name' => $expected]);
        }
    }

    protected static function freeCode(string $base): string
    {
        $code = $base;
        $suffix = 2;

        while (Iblock::withTrashed()->where('code', $code)->exists()) {
            $code = $base.'-'.$suffix++;
        }

        return $code;
    }
}
