<?php

namespace Nexor\Cms\Models;

use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Nexor\Cms\Enums\PropertyType;
use Nexor\Cms\Support\Nexor;

#[Fillable([
    'element_id', 'property_id', 'sort',
    'value_string', 'value_text', 'value_int', 'value_decimal', 'value_bool',
    'value_date', 'value_json', 'value_enum_id', 'value_element_id', 'value_section_id', 'value_user_id',
])]
class IblockElementValue extends Model
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
     * @return BelongsTo<IblockElement, $this>
     */
    public function element(): BelongsTo
    {
        return $this->belongsTo(IblockElement::class, 'element_id');
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
