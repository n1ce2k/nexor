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
        $values = $property->is_multiple ? array_values(array_filter((array) $input, 'filled')) : [$input];

        $element->values()->where('property_id', $property->id)->delete();

        foreach ($values as $index => $value) {
            if (! filled($value) && $property->type !== PropertyType::Boolean) {
                continue;
            }

            $element->values()->create([
                'property_id' => $property->id,
                'sort' => ($index + 1) * 100,
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

        foreach ($uploads as $file) {
            $element->values()->create([
                'property_id' => $property->id,
                'sort' => $sort += 100,
                'value_string' => $file->store('properties/'.$property->code, Uploads::disk()),
            ]);
        }
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
                    ? $values->map(fn (IblockElementValue $value) => ['id' => $value->id, 'path' => $value->value_string])
                    : $values->map(fn (IblockElementValue $value) => $value->raw());

                return [$property->code => $property->is_multiple || $property->type->isFile()
                    ? $resolved->values()->all()
                    : $resolved->first()];
            })
            ->all();
    }
}
