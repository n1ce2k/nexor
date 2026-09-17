<?php

namespace Nexor\Cms\Support;

use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Http\Request;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\DB;
use Nexor\Cms\Enums\PropertyType;
use Nexor\Cms\Models\UserField;
use Nexor\Cms\Models\UserFieldValue;

/**
 * Свои поля пользователей: правила, запись значений и выборка по ним.
 *
 * Значения лежат в типизированной EAV-таблице `user_field_values`: у каждого
 * типа своя колонка со своим индексом, поэтому фильтр по полю остаётся быстрым
 * и на десятках тысяч учётных записей.
 *
 * Форма присылает значения как `fields.<код>`, файлы — `field_files.<код>`,
 * удаление файлов — `field_remove.<код>` (массив id значений).
 */
class UserFields
{
    /**
     * Поля, которые показываются в карточке пользователя.
     *
     * @return Collection<int, UserField>
     */
    public static function all(): Collection
    {
        return UserField::query()->active()->ordered()->get();
    }

    /**
     * @param  Collection<int, UserField>|null  $fields
     * @return array<string, mixed>
     */
    public static function rules(?Collection $fields = null): array
    {
        $rules = [];

        foreach ($fields ?? self::all() as $field) {
            $key = 'fields.'.$field->code;

            if ($field->type->isFile()) {
                $fileRules = [
                    'nullable',
                    $field->type === PropertyType::Image ? 'image' : 'file',
                    'max:'.($field->setting('max_size') ?: 10240),
                ];

                $rules['field_files.'.$field->code] = $field->is_multiple ? ['array'] : $fileRules;

                if ($field->is_multiple) {
                    $rules['field_files.'.$field->code.'.*'] = $fileRules;
                }

                continue;
            }

            $valueRules = self::valueRules($field);

            if ($field->is_multiple) {
                $rules[$key] = ['array'];
                $rules[$key.'.*'] = $valueRules;
            } else {
                $rules[$key] = array_merge([$field->is_required ? 'required' : 'nullable'], $valueRules);
            }
        }

        return $rules;
    }

    /**
     * @return array<string, string>
     */
    public static function attributes(?Collection $fields = null): array
    {
        $attributes = [];

        foreach ($fields ?? self::all() as $field) {
            $attributes['fields.'.$field->code] = mb_strtolower($field->name);
            $attributes['field_files.'.$field->code] = mb_strtolower($field->name);
        }

        return $attributes;
    }

    /**
     * @return array<int, mixed>
     */
    protected static function valueRules(UserField $field): array
    {
        $rules = ['nullable'];

        $rules[] = match ($field->type) {
            PropertyType::Integer => 'integer',
            PropertyType::Decimal => 'numeric',
            PropertyType::Boolean => 'boolean',
            PropertyType::Date, PropertyType::DateTime => 'date',
            PropertyType::Color => 'regex:/^#[0-9A-Fa-f]{6}$/',
            default => 'string',
        };

        if ($field->type === PropertyType::Select && $field->options() !== []) {
            $rules[] = 'in:'.implode(',', array_column($field->options(), 'value'));
        }

        if ($max = $field->setting('max_length')) {
            $rules[] = 'max:'.$max;
        }

        return $rules;
    }

    /**
     * Заменяет значения пользователя тем, что прислала форма.
     *
     * @param  Collection<int, UserField>|null  $fields
     */
    public static function save(Model $user, Request $request, ?Collection $fields = null): void
    {
        $fields ??= self::all();

        DB::transaction(function () use ($user, $request, $fields): void {
            foreach ($fields as $field) {
                $field->type->isFile()
                    ? self::saveFile($user, $field, $request)
                    : self::saveScalar($user, $field, $request);
            }
        });
    }

    protected static function saveScalar(Model $user, UserField $field, Request $request): void
    {
        if (! $request->has('fields.'.$field->code) && ! $field->is_multiple) {
            // Поля нет в запросе — значит форма его не показывала, значение не трогаем.
            return;
        }

        $input = $request->input('fields.'.$field->code);
        $values = $field->is_multiple ? array_values(array_filter((array) $input, 'filled')) : [$input];

        $user->fieldValues()->where('field_id', $field->id)->delete();

        foreach ($values as $index => $value) {
            if (! filled($value) && $field->type !== PropertyType::Boolean) {
                continue;
            }

            $user->fieldValues()->create([
                'field_id' => $field->id,
                'sort' => ($index + 1) * 100,
                $field->storageColumn() => self::cast($field, $value),
            ]);
        }
    }

    /**
     * Загруженное добавляется, отмеченное — удаляется вместе с файлом.
     */
    protected static function saveFile(Model $user, UserField $field, Request $request): void
    {
        $removed = array_filter((array) $request->input('field_remove.'.$field->code, []));

        $user->fieldValues()
            ->where('field_id', $field->id)
            ->whereIn('id', $removed)
            ->get()
            ->each(function (UserFieldValue $value): void {
                Uploads::delete($value->value_string);
                $value->delete();
            });

        $uploads = $request->file('field_files.'.$field->code);
        $uploads = $uploads instanceof UploadedFile ? [$uploads] : (array) $uploads;
        $uploads = array_filter($uploads, fn ($file) => $file instanceof UploadedFile);

        if ($uploads === []) {
            return;
        }

        if (! $field->is_multiple) {
            $user->fieldValues()->where('field_id', $field->id)->get()->each(function (UserFieldValue $value): void {
                Uploads::delete($value->value_string);
                $value->delete();
            });

            $uploads = [reset($uploads)];
        }

        $sort = (int) $user->fieldValues()->where('field_id', $field->id)->max('sort');

        foreach ($uploads as $file) {
            $user->fieldValues()->create([
                'field_id' => $field->id,
                'sort' => $sort += 100,
                'value_string' => $file->store('users/fields/'.$field->code, Uploads::disk()),
            ]);
        }
    }

    protected static function cast(UserField $field, mixed $value): mixed
    {
        return match ($field->type) {
            PropertyType::Integer => (int) $value,
            PropertyType::Decimal => (float) $value,
            PropertyType::Boolean => filter_var($value, FILTER_VALIDATE_BOOLEAN),
            PropertyType::Date, PropertyType::DateTime => $value ?: null,
            PropertyType::Color => strtoupper((string) $value),
            default => $value,
        };
    }

    /**
     * Значения для формы: код поля → значение (у файлов — строки с id и путём).
     *
     * @return array<string, mixed>
     */
    public static function forForm(Model $user): array
    {
        if (! $user->exists) {
            return [];
        }

        $user->loadMissing('fieldValues.field');

        return $user->fieldValues
            ->filter(fn (UserFieldValue $value) => $value->field !== null)
            ->groupBy('field_id')
            ->mapWithKeys(function (Collection $values) {
                $field = $values->first()->field;

                $resolved = $field->type->isFile()
                    ? $values->map(fn (UserFieldValue $value) => [
                        'id' => $value->id,
                        'path' => $value->value_string,
                        'url' => Uploads::url($value->value_string),
                    ])
                    : $values->map(fn (UserFieldValue $value) => $value->raw());

                return [$field->code => $field->is_multiple || $field->type->isFile()
                    ? $resolved->values()->all()
                    : $resolved->first()];
            })
            ->all();
    }

    /**
     * Короткие значения для колонок списка: код поля → строка.
     *
     * @return array<string, string>
     */
    public static function forList(Model $user): array
    {
        $user->loadMissing('fieldValues.field');

        return $user->fieldValues
            ->filter(fn (UserFieldValue $value) => $value->field?->is_shown_in_list)
            ->groupBy(fn (UserFieldValue $value) => $value->field->code)
            ->map(fn (Collection $values) => $values
                ->map(fn (UserFieldValue $value) => $value->display())
                ->filter()
                ->implode(', '))
            ->all();
    }

    /**
     * Фильтр списка пользователей по своим полям: `?fields[code]=значение`.
     *
     * Поиск идёт подзапросом по индексированной колонке значения.
     *
     * @param  Builder<covariant Model>  $query
     * @param  array<string, mixed>  $filter
     */
    public static function filter(Builder $query, array $filter): void
    {
        if ($filter === []) {
            return;
        }

        $fields = self::all()->where('is_filterable', true)->keyBy('code');

        foreach ($filter as $code => $value) {
            $field = $fields->get($code);

            if (! $field || ! filled($value)) {
                continue;
            }

            $query->whereExists(function ($sub) use ($field, $value): void {
                $column = $field->storageColumn();

                $sub->select(DB::raw(1))
                    ->from('user_field_values')
                    ->whereColumn('user_field_values.user_id', 'users.id')
                    ->where('user_field_values.field_id', $field->id);

                match ($field->type) {
                    PropertyType::String, PropertyType::Text, PropertyType::Html => $sub->where($column, 'like', '%'.$value.'%'),
                    PropertyType::Boolean => $sub->where($column, filter_var($value, FILTER_VALIDATE_BOOLEAN)),
                    PropertyType::Date, PropertyType::DateTime => $sub->whereDate($column, $value),
                    default => $sub->where($column, self::cast($field, $value)),
                };
            });
        }
    }
}
