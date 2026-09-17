<?php

namespace Nexor\Cms\Models;

use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Factories\Factory;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Nexor\Cms\Database\Factories\UserFieldFactory;
use Nexor\Cms\Enums\PropertyType;

/**
 * Своё поле пользователя — как свойство инфоблока, только у учётных записей.
 */
#[Fillable([
    'code', 'name', 'hint', 'type', 'is_required', 'is_multiple',
    'is_shown_in_list', 'is_filterable', 'is_active', 'settings', 'sort',
])]
class UserField extends Model
{
    /** @use HasFactory<UserFieldFactory> */
    use HasFactory;

    /**
     * Типы, которые есть у полей пользователя.
     *
     * Привязок к элементам, разделам и пользователям здесь нет: им нужен свой
     * выбор сущности, это отдельная задача.
     *
     * @return array<int, PropertyType>
     */
    public static function types(): array
    {
        return [
            PropertyType::String,
            PropertyType::Text,
            PropertyType::Html,
            PropertyType::Integer,
            PropertyType::Decimal,
            PropertyType::Boolean,
            PropertyType::Date,
            PropertyType::DateTime,
            PropertyType::Color,
            PropertyType::Select,
            PropertyType::File,
            PropertyType::Image,
        ];
    }

    protected $attributes = [
        'type' => 'string',
        'is_active' => true,
        'sort' => 500,
    ];

    protected static function newFactory(): Factory
    {
        return UserFieldFactory::new();
    }

    protected function casts(): array
    {
        return [
            'type' => PropertyType::class,
            'is_required' => 'boolean',
            'is_multiple' => 'boolean',
            'is_shown_in_list' => 'boolean',
            'is_filterable' => 'boolean',
            'is_active' => 'boolean',
            'settings' => 'array',
            'sort' => 'integer',
        ];
    }

    /**
     * @return HasMany<UserFieldValue, $this>
     */
    public function values(): HasMany
    {
        return $this->hasMany(UserFieldValue::class, 'field_id');
    }

    public function setting(string $key, mixed $default = null): mixed
    {
        return data_get($this->settings, $key, $default);
    }

    /**
     * Колонка `user_field_values`, где лежит значение этого типа.
     *
     * Список хранится строкой, а не ссылкой на таблицу вариантов: варианты
     * живут в настройках самого поля.
     */
    public function storageColumn(): string
    {
        return $this->type === PropertyType::Select ? 'value_string' : $this->type->column();
    }

    /**
     * Варианты списка: `[{ value, label }]`.
     *
     * @return array<int, array{value: string, label: string}>
     */
    public function options(): array
    {
        $options = [];

        foreach ((array) $this->setting('options', []) as $option) {
            $value = trim((string) (is_array($option) ? ($option['value'] ?? '') : $option));

            if ($value === '') {
                continue;
            }

            $options[] = [
                'value' => $value,
                'label' => trim((string) (is_array($option) ? ($option['label'] ?? '') : '')) ?: $value,
            ];
        }

        return $options;
    }

    /**
     * @param  Builder<$this>  $query
     */
    public function scopeActive(Builder $query): void
    {
        $query->where('is_active', true);
    }

    /**
     * @param  Builder<$this>  $query
     */
    public function scopeOrdered(Builder $query): void
    {
        $query->orderBy('sort')->orderBy('id');
    }
}
