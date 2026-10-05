<?php

namespace Nexor\Cms\Models\Concerns;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Support\Collection;
use Nexor\Cms\Enums\PropertyType;
use Nexor\Cms\Models\IblockProperty;
use Nexor\Cms\Support\Uploads;

/**
 * Значения свойств сущности инфоблока — элемента или раздела.
 *
 * Сущность говорит, какие свойства ей положены (`propertyDefinitions`), и
 * отдаёт строки значений связью `values`. Всё остальное одинаково.
 */
trait HasPropertyValues
{
    /**
     * Свойства, положенные этой сущности: у элемента — свойства элементов
     * инфоблока, у раздела — свойства разделов.
     *
     * @return Collection<int, IblockProperty>
     */
    abstract public function propertyDefinitions(): Collection;

    /**
     * Property values keyed by property code.
     *
     * Multiple properties return a collection of values, single ones a scalar.
     *
     * @return Collection<string, mixed>
     */
    public function propertyValues(): Collection
    {
        $this->loadMissing(['values.property', 'values.enum']);

        return $this->propertyDefinitions()->mapWithKeys(function (IblockProperty $property) {
            $values = $this->values
                ->where('property_id', $property->id)
                ->map(fn (Model $value) => $value->resolved())
                ->values();

            return [$property->code => $property->is_multiple ? $values : $values->first()];
        });
    }

    public function property(string $code): mixed
    {
        return $this->propertyValues()->get($code);
    }

    /**
     * Описания значений — той же формы, что и сами значения: у множественного
     * свойства коллекция, у обычного одна строка.
     *
     * @return Collection<string, mixed>
     */
    public function propertyDescriptions(): Collection
    {
        $this->loadMissing(['values.property']);

        return $this->propertyDefinitions()->mapWithKeys(function (IblockProperty $property) {
            $descriptions = $this->values
                ->where('property_id', $property->id)
                ->map(fn (Model $value) => $value->description)
                ->values();

            return [$property->code => $property->is_multiple ? $descriptions : $descriptions->first()];
        });
    }

    /**
     * Описание значения свойства: подпись к файлу, текст ссылки, примечание.
     */
    public function propertyDescription(string $code): mixed
    {
        return $this->propertyDescriptions()->get($code);
    }

    /**
     * Свойства со всем, что нужно шаблону, — по коду свойства.
     *
     * У каждого: `id`, `code`, `name`, `type`, `hint`, `multiple`, `sort` самого
     * свойства и его значение — `value` (готово к выводу: подпись варианта,
     * адрес файла, имя привязанной записи), `raw` (как лежит в базе: id
     * варианта, путь файла, id записи) и `description`. У множественного
     * свойства это списки, у обычного — одно значение. В `values` — те же
     * данные построчно, с `id` строки и дополнительными полями типа (`url`,
     * `enum_code`).
     *
     * Только активные свойства; незаполненное свойство в массиве тоже есть,
     * со значением null (у множественного — пустой список).
     *
     * @return array<string, array<string, mixed>>
     */
    public function properties(): array
    {
        // Свойств нет — и значения грузить незачем: в списке разделов это
        // был бы лишний запрос на каждый раздел.
        if ($this->propertyDefinitions()->isEmpty()) {
            return [];
        }

        $this->loadMissing([
            'values.property', 'values.enum', 'values.linkedElement', 'values.linkedSection',
        ]);

        $result = [];

        foreach ($this->propertyDefinitions()->where('is_active', true) as $property) {
            $rows = $this->values
                ->where('property_id', $property->id)
                ->map(fn (Model $value) => ['id' => $value->id] + $this->describeValue($property, $value))
                ->values();

            $pick = fn (string $key): mixed => $property->is_multiple
                ? $rows->pluck($key)->all()
                : ($rows->first()[$key] ?? null);

            $result[$property->code] = [
                'id' => $property->id,
                'code' => $property->code,
                'name' => $property->name,
                'type' => $property->type->value,
                'hint' => $property->hint,
                'multiple' => $property->is_multiple,
                'sort' => $property->sort,
                'value' => $pick('value'),
                'raw' => $pick('raw'),
                'description' => $pick('description'),
                'values' => $rows->all(),
            ];
        }

        return $result;
    }

    /**
     * Одно значение в готовом к выводу виде.
     *
     * @return array<string, mixed>
     */
    protected function describeValue(IblockProperty $property, Model $value): array
    {
        $raw = $value->raw();
        $row = ['value' => $raw, 'raw' => $raw, 'description' => $value->description];

        if ($property->type === PropertyType::Select) {
            return ['value' => $value->enum?->value, 'enum_code' => $value->enum?->code] + $row;
        }

        if ($property->type === PropertyType::Element) {
            return ['value' => $value->linkedElement?->name, 'url' => $value->linkedElement?->url()] + $row;
        }

        if ($property->type === PropertyType::Section) {
            return ['value' => $value->linkedSection?->name, 'url' => $value->linkedSection?->url()] + $row;
        }

        if ($property->type === PropertyType::User) {
            return ['value' => $value->linkedUser?->name] + $row;
        }

        if ($property->type->isFile()) {
            // В базе путь внутри хранилища, а шаблону нужен адрес.
            return ['value' => Uploads::url($raw), 'url' => Uploads::url($raw)] + $row;
        }

        return $row;
    }
}
