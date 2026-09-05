<?php

namespace Nexor\Cms\Database\Factories;

use Illuminate\Database\Eloquent\Factories\Factory;
use Nexor\Cms\Models\IblockType;

/**
 * @extends Factory<IblockType>
 */
class IblockTypeFactory extends Factory
{
    /** @var class-string<IblockType> */
    protected $model = IblockType::class;

    /**
     * @return array<string, mixed>
     */
    public function definition(): array
    {
        return [
            'code' => 'type-'.fake()->unique()->numberBetween(1, 99999),
            'name' => fake()->words(2, true),
            'description' => fake()->sentence(),
            'has_sections' => true,
            'is_active' => true,
            'sort' => 500,
        ];
    }

    public function withoutSections(): static
    {
        return $this->state(['has_sections' => false]);
    }
}
