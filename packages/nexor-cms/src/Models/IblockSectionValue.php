<?php

namespace Nexor\Cms\Models;

use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Nexor\Cms\Models\Concerns\HoldsPropertyValue;

/**
 * Значение свойства раздела. Устроено так же, как значение свойства элемента.
 */
#[Fillable([
    'section_id', 'property_id', 'sort',
    'value_string', 'value_text', 'value_int', 'value_decimal', 'value_bool',
    'value_date', 'value_json', 'value_enum_id', 'value_element_id', 'value_section_id', 'value_user_id',
    'description',
])]
class IblockSectionValue extends Model
{
    use HoldsPropertyValue;

    protected $table = 'iblock_section_values';

    /**
     * @return BelongsTo<IblockSection, $this>
     */
    public function section(): BelongsTo
    {
        return $this->belongsTo(IblockSection::class, 'section_id');
    }
}
