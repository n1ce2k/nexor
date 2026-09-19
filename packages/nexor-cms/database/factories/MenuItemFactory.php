<?php

namespace Nexor\Cms\Database\Factories;

use Illuminate\Database\Eloquent\Factories\Factory;
use Nexor\Cms\Enums\MenuItemType;
use Nexor\Cms\Enums\MenuVisibility;
use Nexor\Cms\Models\Menu;
use Nexor\Cms\Models\MenuItem;

/**
 * @extends Factory<MenuItem>
 */
class MenuItemFactory extends Factory
{
    /** @var class-string<MenuItem> */
    protected $model = MenuItem::class;

    /**
     * @return array<string, mixed>
     */
    public function definition(): array
    {
        return [
            'menu_id' => Menu::factory(),
            'type' => MenuItemType::Link,
            'title' => fake()->words(2, true),
            'url' => '/'.fake()->slug(),
            'visibility' => MenuVisibility::All,
            'max_depth' => 2,
            'with_elements' => false,
            'with_title' => false,
            'highlight_children' => true,
            'is_active' => true,
            'sort' => 500,
        ];
    }

    public function ofType(MenuItemType $type): static
    {
        return $this->state(['type' => $type]);
    }

    public function hidden(): static
    {
        return $this->state(['is_active' => false]);
    }
}
