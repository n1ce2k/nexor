<?php

namespace Nexor\Cms\Models\Concerns;

use Illuminate\Database\Eloquent\Relations\HasMany;
use Nexor\Cms\Models\UserFieldValue;

/**
 * Свои поля пользователя, заведённые в админке.
 *
 * Подключается к модели пользователя приложения рядом с HasRoles.
 */
trait HasUserFields
{
    /**
     * @return HasMany<UserFieldValue, $this>
     */
    public function fieldValues(): HasMany
    {
        return $this->hasMany(UserFieldValue::class, 'user_id')->orderBy('sort')->orderBy('id');
    }

    /**
     * Значение поля по его коду; у множественного — массив значений.
     */
    public function field(string $code): mixed
    {
        $this->loadMissing('fieldValues.field');

        $values = $this->fieldValues->filter(fn (UserFieldValue $value) => $value->field?->code === $code);
        $field = $values->first()?->field;

        if ($field?->is_multiple || $field?->type->isFile()) {
            return $values->map(fn (UserFieldValue $value) => $value->raw())->values()->all();
        }

        return $values->first()?->raw();
    }

    /**
     * Все свои поля: код → значение.
     *
     * @return array<string, mixed>
     */
    public function fields(): array
    {
        $this->loadMissing('fieldValues.field');

        return $this->fieldValues
            ->filter(fn (UserFieldValue $value) => $value->field !== null)
            ->groupBy(fn (UserFieldValue $value) => $value->field->code)
            ->map(function ($values, string $code) {
                $field = $values->first()->field;
                $raw = $values->map(fn (UserFieldValue $value) => $value->raw())->values();

                return $field->is_multiple || $field->type->isFile() ? $raw->all() : $raw->first();
            })
            ->all();
    }
}
