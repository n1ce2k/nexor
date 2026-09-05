<?php

namespace Nexor\Cms\Database\Factories;

use Illuminate\Database\Eloquent\Factories\Factory;
use Nexor\Cms\Models\Iblock;
use Nexor\Cms\Models\IblockElement;

/**
 * @extends Factory<IblockElement>
 */
class IblockElementFactory extends Factory
{
    /** @var class-string<IblockElement> */
    protected $model = IblockElement::class;

    /**
     * @return array<string, mixed>
     */
    public function definition(): array
    {
        return [
            'iblock_id' => Iblock::factory(),
            'code' => 'element-'.fake()->unique()->numberBetween(1, 99999),
            'name' => fake()->words(3, true),
            'preview_text' => fake()->sentence(),
            'detail_text' => fake()->paragraph(),
            'is_active' => true,
            'sort' => 500,
        ];
    }

    public function hidden(): static
    {
        return $this->state(['is_active' => false]);
    }
}
