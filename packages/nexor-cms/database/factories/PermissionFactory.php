<?php

namespace Nexor\Cms\Database\Factories;

use Illuminate\Database\Eloquent\Factories\Factory;
use Nexor\Cms\Models\Permission;

/**
 * @extends Factory<Permission>
 */
class PermissionFactory extends Factory
{
    /** @var class-string<Permission> */
    protected $model = Permission::class;

    /**
     * @return array<string, mixed>
     */
    public function definition(): array
    {
        $code = fake()->unique()->word().'.'.fake()->randomElement(['view', 'create', 'update', 'delete']);

        return [
            'code' => $code,
            'name' => $code,
            'group' => 'test',
            'group_label' => 'Тестовые',
            'sort' => 500,
        ];
    }
}
