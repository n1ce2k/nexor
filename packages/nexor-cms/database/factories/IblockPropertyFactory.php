<?php

namespace Nexor\Cms\Database\Factories;

use Illuminate\Database\Eloquent\Factories\Factory;
use Nexor\Cms\Enums\PropertyType;
use Nexor\Cms\Models\Iblock;
use Nexor\Cms\Models\IblockProperty;

/**
 * @extends Factory<IblockProperty>
 */
class IblockPropertyFactory extends Factory
{
    /** @var class-string<IblockProperty> */
    protected $model = IblockProperty::class;

    /**
     * @return array<string, mixed>
     */
    public function definition(): array
    {
        return [
            'iblock_id' => Iblock::factory(),
            'code' => 'PROP_'.fake()->unique()->numberBetween(1, 99999),
            'name' => fake()->words(2, true),
            'type' => PropertyType::String,
            'is_multiple' => false,
            'is_required' => false,
            'is_filterable' => false,
            'is_searchable' => false,
            'is_shown_in_list' => false,
            'is_active' => true,
            'sort' => 500,
        ];
    }

    public function ofType(PropertyType $type): static
    {
        return $this->state(['type' => $type]);
    }

    public function multiple(): static
    {
        return $this->state(['is_multiple' => true]);
    }

    public function required(): static
    {
        return $this->state(['is_required' => true]);
    }

    /**
     * A select property with the given options already created.
     *
     * @param  array<int, string>  $values
     */
    public function withEnums(array $values): static
    {
        return $this->ofType(PropertyType::Select)
            ->afterCreating(function (IblockProperty $property) use ($values): void {
                foreach ($values as $index => $value) {
                    $property->enums()->create([
                        'value' => $value,
                        'sort' => ($index + 1) * 100,
                        'is_default' => $index === 0,
                    ]);
                }
            });
    }
}
