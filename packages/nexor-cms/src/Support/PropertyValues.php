<?php

namespace Nexor\Cms\Support;

use Illuminate\Http\Request;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\DB;
use Nexor\Cms\Enums\PropertyType;
use Nexor\Cms\Models\IblockElement;
use Nexor\Cms\Models\IblockElementValue;
use Nexor\Cms\Models\IblockProperty;

/**
 * Reads and writes infoblock property values.
 *
 * Values live in a typed EAV table: each property declares which column holds it,
 * so this class only has to cast the submitted input and pick the right column.
 */
class PropertyValues
{
    /**
     * Validation rules for the `properties` input of an element form.
     *
     * @param  Collection<int, IblockProperty>  $properties
     * @return array<string, mixed>
     */
    public static function rules(Collection $properties): array
    {
        $rules = [];

        foreach ($properties as $property) {
            $key = 'properties.'.$property->code;

            if ($property->with_description) {
                $rules += self::descriptionRules($property);
            }

            if ($property->type->isFile()) {
                $fileRules = [
                    'nullable',
                    $property->type === PropertyType::Image ? 'image' : 'file',
                    'max:'.($property->setting('max_size') ?: 10240),
                ];

                $rules['property_files.'.$property->code] = $property->is_multiple ? ['array'] : $fileRules;

                if ($property->is_multiple) {
                    $rules['property_files.'.$property->code.'.*'] = $fileRules;
                }

                continue;
            }

            $valueRules = self::valueRules($property);

            if ($property->is_multiple) {
                $rules[$key] = ['array'];
                $rules[$key.'.*'] = $valueRules;
            } else {
                $rules[$key] = array_merge([$property->is_required ? 'required' : 'nullable'], $valueRules);
            }
        }

        return $rules;
    }

    /**
     * Описания значений: у файлов они приходят двумя пачками — к сохранённым
     * строкам по их id и к только что выбранным файлам по порядку.
     *
     * @return array<string, mixed>
     */
    protected static function descriptionRules(IblockProperty $property): array
    {
        $key = 'property_descriptions.'.$property->code;

        if ($property->type->isFile()) {
            return [
                $key => ['nullable', 'array'],
                $key.'.saved' => ['nullable', 'array'],
                $key.'.saved.*' => ['nullable', 'string', 'max:500'],
                $key.'.added' => ['nullable', 'array'],
                $key.'.added.*' => ['nullable', 'string', 'max:500'],
            ];
        }

        if ($property->is_multiple) {
            return [
                $key => ['nullable', 'array'],
                $key.'.*' => ['nullable', 'string', 'max:500'],
            ];
        }

        return [$key => ['nullable', 'string', 'max:500']];
    }

    /**
     * @return array<int, mixed>
     */
    protected static function valueRules(IblockProperty $property): array
    {
        $rules = ['nullable'];

        $rules[] = match ($property->type) {
            PropertyType::Integer => 'integer',
            PropertyType::Decimal => 'numeric',
            PropertyType::Boolean => 'boolean',
            PropertyType::Date, PropertyType::DateTime => 'date',
            PropertyType::Color => 'regex:/^#[0-9A-Fa-f]{6}$/',
            PropertyType::Select => 'integer',
            PropertyType::Element, PropertyType::Section, PropertyType::User => 'integer',
            PropertyType::Json => 'json',
            default => 'string',
        };

        if ($max = $property->setting('max_length')) {
            $rules[] = 'max:'.$max;
        }

        if ($property->type === PropertyType::Integer || $property->type === PropertyType::Decimal) {
            if (($min = $property->setting('min')) !== null) {
                $rules[] = 'min:'.$min;
            }

            if (($max = $property->setting('max')) !== null) {
                $rules[] = 'max:'.$max;
            }
        }

        return $rules;
    }

    /**
     * Replace an element's property values with what the form submitted.
     *
     * @param  Collection<int, IblockProperty>  $properties
     */
    public static function save(IblockElement $element, Collection $properties, Request $request): void
    {
        DB::transaction(function () use ($element, $properties, $request): void {
            foreach ($properties as $property) {
                $property->type->isFile()
                    ? self::saveFileProperty($element, $property, $request)
                    : self::saveScalarProperty($element, $property, $request);
            }
        });
    }

    protected static function saveScalarProperty(IblockElement $element, IblockProperty $property, Request $request): void
    {
        $input = $request->input('properties.'.$property->code);
        $descriptions = $property->with_description
            ? $request->input('property_descriptions.'.$property->code)
            : null;

        // Значение и его описание идут парой: пустые значения отсеиваются
        // вместе с описаниями, иначе подписи сползут на соседние строки.
        $pairs = [];

        if ($property->is_multiple) {
            foreach ((array) $input as $index => $value) {
                $pairs[] = [$value, is_array($descriptions) ? ($descriptions[$index] ?? null) : null];
            }
        } else {
            $pairs[] = [$input, is_string($descriptions) ? $descriptions : null];
        }

        $element->values()->where('property_id', $property->id)->delete();

        $sort = 0;

        foreach ($pairs as [$value, $description]) {
            if (! filled($value) && $property->type !== PropertyType::Boolean) {
                continue;
            }

            $element->values()->create([
                'property_id' => $property->id,
                'sort' => ($sort += 100),
                'description' => $property->with_description ? (filled($description) ? trim((string) $description) : null) : null,
                $property->storageColumn() => self::cast($property, $value),
            ]);
        }
    }

    /**
     * Keeps existing files unless explicitly removed, and appends newly uploaded ones.
     */
    protected static function saveFileProperty(IblockElement $element, IblockProperty $property, Request $request): void
    {
        $removed = (array) $request->input('property_remove.'.$property->code, []);

        $element->values()
            ->where('property_id', $property->id)
            ->whereIn('id', array_filter($removed))
            ->get()
            ->each(function (IblockElementValue $value): void {
                Uploads::delete($value->value_string);
                $value->delete();
            });

        if ($property->with_description) {
            self::saveFileDescriptions($element, $property, $request);
        }

        $uploads = $request->file('property_files.'.$property->code);
        $uploads = $uploads instanceof UploadedFile ? [$uploads] : (array) $uploads;
        $uploads = array_filter($uploads, fn ($file) => $file instanceof UploadedFile);

        if ($uploads === []) {
            return;
        }

        if (! $property->is_multiple) {
            $element->values()->where('property_id', $property->id)->get()->each(function (IblockElementValue $value): void {
                Uploads::delete($value->value_string);
                $value->delete();
            });

            $uploads = [reset($uploads)];
        }

        $sort = ($element->values()->where('property_id', $property->id)->max('sort') ?? 0);
        $added = $property->with_description
            ? (array) $request->input('property_descriptions.'.$property->code.'.added', [])
            : [];

        foreach (array_values($uploads) as $index => $file) {
            $description = $added[$index] ?? null;

            $element->values()->create([
                'property_id' => $property->id,
                'sort' => $sort += 100,
                'description' => filled($description) ? trim((string) $description) : null,
                'value_string' => $file->store('properties/'.$property->code, Uploads::disk()),
            ]);
        }
    }

    /**
     * Подписи к уже загруженным файлам: строки на месте, меняется только текст.
     */
    protected static function saveFileDescriptions(IblockElement $element, IblockProperty $property, Request $request): void
    {
        $saved = (array) $request->input('property_descriptions.'.$property->code.'.saved', []);

        if ($saved === []) {
            return;
        }

        $element->values()
            ->where('property_id', $property->id)
            ->whereIn('id', array_keys($saved))
            ->get()
            ->each(function (IblockElementValue $value) use ($saved): void {
                $description = $saved[$value->id] ?? null;

                $value->update(['description' => filled($description) ? trim((string) $description) : null]);
            });
    }

    protected static function cast(IblockProperty $property, mixed $value): mixed
    {
        return match ($property->type) {
            PropertyType::Integer, PropertyType::Select, PropertyType::Element,
            PropertyType::Section, PropertyType::User => (int) $value,
            PropertyType::Decimal => (float) $value,
            PropertyType::Boolean => filter_var($value, FILTER_VALIDATE_BOOLEAN),
            PropertyType::Date, PropertyType::DateTime => $value ?: null,
            PropertyType::Json => is_string($value) ? json_decode($value, true) : $value,
            PropertyType::Color => strtoupper((string) $value),
            default => $value,
        };
    }

    /**
     * Raw values keyed by property code, ready to repopulate the element form.
     *
     * File properties return the value rows themselves so the form can show and
     * offer to remove each stored file.
     *
     * @return array<string, mixed>
     */
    public static function forForm(IblockElement $element): array
    {
        if (! $element->exists) {
            return [];
        }

        $element->loadMissing('values.property');

        return $element->values
            ->groupBy('property_id')
            ->mapWithKeys(function (Collection $values) {
                $property = $values->first()->property;

                $resolved = $property->type->isFile()
                    ? $values->map(fn (IblockElementValue $value) => [
                        'id' => $value->id,
                        'path' => $value->value_string,
                        'description' => $value->description,
                    ])
                    : $values->map(fn (IblockElementValue $value) => $value->raw());

                return [$property->code => $property->is_multiple || $property->type->isFile()
                    ? $resolved->values()->all()
                    : $resolved->first()];
            })
            ->all();
    }

    /**
     * Описания значений для формы: у файлов они уже лежат в строках файлов,
     * поэтому здесь только свойства с обычными значениями.
     *
     * @return array<string, string|array<int, string|null>|null>
     */
    public static function descriptionsForForm(IblockElement $element): array
    {
        if (! $element->exists) {
            return [];
        }

        $element->loadMissing('values.property');

        return $element->values
            ->groupBy('property_id')
            ->mapWithKeys(function (Collection $values) {
                $property = $values->first()->property;

                if (! $property?->with_description || $property->type->isFile()) {
                    return [];
                }

                $descriptions = $values->map(fn (IblockElementValue $value) => $value->description);

                return [$property->code => $property->is_multiple
                    ? $descriptions->values()->all()
                    : $descriptions->first()];
            })
            ->all();
    }
}
