<?php

namespace Nexor\Cms\Database\Factories;

use Illuminate\Database\Eloquent\Factories\Factory;
use Nexor\Cms\Models\Iblock;
use Nexor\Cms\Models\IblockSection;

/**
 * @extends Factory<IblockSection>
 */
class IblockSectionFactory extends Factory
{
    /** @var class-string<IblockSection> */
    protected $model = IblockSection::class;

    /**
     * @return array<string, mixed>
     */
    public function definition(): array
    {
        return [
            'iblock_id' => Iblock::factory(),
            'parent_id' => null,
            'code' => 'section-'.fake()->unique()->numberBetween(1, 99999),
            'name' => fake()->words(2, true),
            'is_active' => true,
            'sort' => 500,
        ];
    }

    public function childOf(IblockSection $parent): static
    {
        return $this->state([
            'iblock_id' => $parent->iblock_id,
            'parent_id' => $parent->id,
        ]);
    }
}
