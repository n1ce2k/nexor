<?php

namespace Nexor\Cms\Models\Concerns;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Nexor\Cms\Enums\PropertyType;
use Nexor\Cms\Models\IblockElement;
use Nexor\Cms\Models\IblockProperty;
use Nexor\Cms\Models\IblockPropertyEnum;
use Nexor\Cms\Models\IblockSection;
use Nexor\Cms\Support\Nexor;

/**
 * Одна строка значения свойства — у элемента или у раздела.
 *
 * Таблицы значений устроены одинаково: у каждого типа своя типизированная
 * колонка, а какая именно — говорит свойство. Поэтому чтение значения у
 * элементов и разделов общее.
 */
trait HoldsPropertyValue
{
    protected function casts(): array
    {
        return [
            'sort' => 'integer',
            'value_int' => 'integer',
            'value_decimal' => 'decimal:6',
            'value_bool' => 'boolean',
            'value_date' => 'datetime',
            'value_json' => 'array',
        ];
    }

    /**
     * @return BelongsTo<IblockProperty, $this>
     */
    public function property(): BelongsTo
    {
        return $this->belongsTo(IblockProperty::class, 'property_id');
    }

    /**
     * @return BelongsTo<IblockPropertyEnum, $this>
     */
    public function enum(): BelongsTo
    {
        return $this->belongsTo(IblockPropertyEnum::class, 'value_enum_id');
    }

    /**
     * @return BelongsTo<IblockElement, $this>
     */
    public function linkedElement(): BelongsTo
    {
        return $this->belongsTo(IblockElement::class, 'value_element_id');
    }

    /**
     * @return BelongsTo<IblockSection, $this>
     */
    public function linkedSection(): BelongsTo
    {
        return $this->belongsTo(IblockSection::class, 'value_section_id');
    }

    /**
     * @return BelongsTo<covariant Model, $this>
     */
    public function linkedUser(): BelongsTo
    {
        return $this->belongsTo(Nexor::userModel(), 'value_user_id');
    }

    /**
     * Raw stored value for this row, taken from the column its type dictates.
     */
    public function raw(): mixed
    {
        return $this->{$this->property->storageColumn()};
    }

    /**
     * Value ready for display: enums resolve to their label, links to their model.
     */
    public function resolved(): mixed
    {
        return match ($this->property->type) {
            PropertyType::Select => $this->enum?->value,
            PropertyType::Element => $this->linkedElement,
            PropertyType::Section => $this->linkedSection,
            PropertyType::User => $this->linkedUser,
            default => $this->raw(),
        };
    }
}
