<?php

namespace Nexor\Cms\Support;

use Illuminate\Database\Eloquent\Builder;
use Illuminate\Support\Collection;
use Nexor\Cms\Enums\PropertyType;
use Nexor\Cms\Models\IblockElement;
use Nexor\Cms\Models\IblockProperty;
use Nexor\Cms\Models\IblockSection;

/**
 * Варианты выбора для свойств-привязок: элемент, раздел, пользователь.
 *
 * Нужны и форме элемента, и форме раздела — у свойств разделов те же типы.
 */
class PropertyOptions
{
    /** Больше этого в выпадающий список не кладём. */
    public const LIMIT = 500;

    /**
     * @param  Collection<int, IblockProperty>  $properties
     * @return array<string, array<int, array{value: int, label: string}>>
     */
    public static function for(Collection $properties): array
    {
        $options = [];

        foreach ($properties as $property) {
            $query = match ($property->type) {
                PropertyType::Element => IblockElement::query()
                    ->where('iblock_id', $property->setting('link_iblock_id'))->ordered(),
                PropertyType::Section => IblockSection::query()
                    ->where('iblock_id', $property->setting('link_iblock_id'))->ordered(),
                PropertyType::User => Nexor::newUser()->newQuery()->where('is_active', true)->orderBy('name'),
                default => null,
            };

            if ($query !== null) {
                $options[$property->code] = self::pluck($query);
            }
        }

        return $options;
    }

    /**
     * @return array<int, array{value: int, label: string}>
     */
    protected static function pluck(Builder $query): array
    {
        return $query->limit(self::LIMIT)->get()->map(fn ($model) => [
            'value' => $model->getKey(),
            'label' => $model->name,
        ])->all();
    }
}
