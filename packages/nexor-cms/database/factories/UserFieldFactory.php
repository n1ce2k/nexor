<?php

namespace Nexor\Cms\Database\Factories;

use Illuminate\Database\Eloquent\Factories\Factory;
use Nexor\Cms\Enums\PropertyType;
use Nexor\Cms\Models\UserField;

/**
 * @extends Factory<UserField>
 */
class UserFieldFactory extends Factory
{
    protected $model = UserField::class;

    /**
     * @return array<string, mixed>
     */
    public function definition(): array
    {
        return [
            'code' => 'field_'.fake()->unique()->numberBetween(1, 99999),
            'name' => 'Поле',
            'type' => PropertyType::String,
            'is_required' => false,
            'is_multiple' => false,
            'is_shown_in_list' => false,
            'is_filterable' => false,
            'is_active' => true,
            'sort' => 500,
        ];
    }
}
