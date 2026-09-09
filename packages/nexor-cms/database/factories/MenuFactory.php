<?php

namespace Nexor\Cms\Database\Factories;

use Illuminate\Database\Eloquent\Factories\Factory;
use Nexor\Cms\Models\Menu;

/**
 * @extends Factory<Menu>
 */
class MenuFactory extends Factory
{
    /** @var class-string<Menu> */
    protected $model = Menu::class;

    /**
     * @return array<string, mixed>
     */
    public function definition(): array
    {
        return [
            'code' => 'menu-'.fake()->unique()->numberBetween(1, 99999),
            'name' => fake()->words(2, true),
            'is_active' => true,
            'sort' => 500,
        ];
    }
}
