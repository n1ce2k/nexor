<?php

namespace Nexor\Cms\Database\Factories;

use Illuminate\Database\Eloquent\Factories\Factory;
use Illuminate\Support\Str;
use Nexor\Cms\Models\Permission;
use Nexor\Cms\Models\Role;

/**
 * @extends Factory<Role>
 */
class RoleFactory extends Factory
{
    /** @var class-string<Role> */
    protected $model = Role::class;

    /**
     * @return array<string, mixed>
     */
    public function definition(): array
    {
        $name = fake()->unique()->jobTitle();

        return [
            'code' => Str::slug($name).'-'.fake()->unique()->numberBetween(1, 99999),
            'name' => $name,
            'description' => fake()->sentence(),
            'is_system' => false,
            'sort' => 500,
        ];
    }

    public function system(): static
    {
        return $this->state(['is_system' => true]);
    }

    /**
     * Grant the role a set of permissions, creating any that do not exist yet.
     *
     * @param  array<int, string>  $codes
     */
    public function withPermissions(array $codes): static
    {
        return $this->afterCreating(function (Role $role) use ($codes): void {
            $ids = collect($codes)->map(fn (string $code) => Permission::query()->firstOrCreate(
                ['code' => $code],
                ['name' => $code, 'group' => 'test'],
            )->id);

            $role->permissions()->syncWithoutDetaching($ids);
        });
    }
}
