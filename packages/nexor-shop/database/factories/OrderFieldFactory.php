<?php

namespace Nexor\Shop\Database\Factories;

use Illuminate\Database\Eloquent\Factories\Factory;
use Nexor\Shop\Enums\OrderFieldType;
use Nexor\Shop\Models\OrderField;

/**
 * @extends Factory<OrderField>
 */
class OrderFieldFactory extends Factory
{
    /** @var class-string<OrderField> */
    protected $model = OrderField::class;

    /**
     * @return array<string, mixed>
     */
    public function definition(): array
    {
        return [
            'code' => strtoupper(fake()->unique()->lexify('FIELD_????')),
            'name' => fake()->words(2, true),
            'type' => OrderFieldType::Text,
            'is_required' => false,
            'is_active' => true,
            'sort' => 900,
        ];
    }

    public function required(): static
    {
        return $this->state(['is_required' => true]);
    }
}
