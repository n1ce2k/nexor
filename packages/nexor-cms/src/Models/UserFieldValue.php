<?php

namespace Nexor\Cms\Models;

use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Nexor\Cms\Enums\PropertyType;
use Nexor\Cms\Support\Uploads;

/**
 * Значение своего поля пользователя.
 */
#[Fillable([
    'user_id', 'field_id', 'sort',
    'value_string', 'value_text', 'value_int', 'value_decimal', 'value_bool', 'value_date', 'value_json',
])]
class UserFieldValue extends Model
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
     * @return BelongsTo<UserField, $this>
     */
    public function field(): BelongsTo
    {
        return $this->belongsTo(UserField::class, 'field_id');
    }

    /**
     * Значение как оно лежит в базе — для формы.
     */
    public function raw(): mixed
    {
        $field = $this->field;

        return match ($field?->type) {
            PropertyType::Date => $this->value_date?->format('Y-m-d'),
            PropertyType::DateTime => $this->value_date?->format('Y-m-d\TH:i'),
            PropertyType::Decimal => $this->value_decimal === null ? null : (float) $this->value_decimal,
            null => $this->value_string,
            default => $this->{$field->storageColumn()},
        };
    }

    /**
     * Значение для показа: файл — ссылкой, список — подписью варианта.
     */
    public function display(): ?string
    {
        $field = $this->field;

        return match ($field?->type) {
            PropertyType::File, PropertyType::Image => $this->value_string ? Uploads::url($this->value_string) : null,
            PropertyType::Select => collect($field->options())->firstWhere('value', $this->value_string)['label'] ?? $this->value_string,
            PropertyType::Boolean => $this->value_bool ? 'да' : 'нет',
            PropertyType::Date => $this->value_date?->format('d.m.Y'),
            PropertyType::DateTime => $this->value_date?->format('d.m.Y H:i'),
            default => $this->raw() === null ? null : (string) $this->raw(),
        };
    }
}
