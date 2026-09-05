<?php

namespace Tests\Feature;

use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Nexor\Cms\Models\Permission;
use Nexor\Cms\Models\Role;
use Nexor\Cms\Support\Permissions;
use Tests\Concerns\CreatesAdminUsers;
use Tests\TestCase;

class RoleManagementTest extends TestCase
{
    use CreatesAdminUsers, RefreshDatabase;

    public function test_a_role_can_be_created_with_selected_permissions(): void
    {
        $admin = $this->adminWith(['roles.create']);
        $permissions = Permission::factory()->count(3)->create();

        $this->actingAs($admin)->post(route('admin.roles.store'), [
            'code' => 'editors',
            'name' => 'Редакторы',
            'description' => 'Правят контент',
            'sort' => 250,
            'permissions' => $permissions->pluck('id')->all(),
        ])->assertRedirect(route('admin.roles.index'));

        $role = Role::query()->where('code', 'editors')->firstOrFail();

        $this->assertSame('Редакторы', $role->name);
        $this->assertCount(3, $role->permissions);
    }

    public function test_the_code_must_be_a_valid_slug(): void
    {
        $this->actingAs($this->adminWith(['roles.create']))
            ->post(route('admin.roles.store'), [
                'code' => 'Не Слаг!',
                'name' => 'Плохой код',
            ])
            ->assertSessionHasErrors('code');
    }

    public function test_the_code_of_a_system_role_cannot_be_changed(): void
    {
        $admin = $this->adminWith(['roles.update']);
        $role = Role::factory()->system()->create(['code' => 'administrator']);

        $this->actingAs($admin)->put(route('admin.roles.update', $role), [
            'code' => 'hijacked',
            'name' => 'Переименована',
        ])->assertRedirect(route('admin.roles.index'));

        $role->refresh();

        $this->assertSame('administrator', $role->code);
        $this->assertSame('Переименована', $role->name);
    }

    public function test_the_super_admin_role_always_holds_every_permission(): void
    {
        $admin = $this->superAdmin();
        Permissions::syncStatic();

        $role = Role::factory()->system()->create(['code' => Role::SUPER_ADMIN, 'name' => 'Супер']);

        $this->actingAs($admin)->put(route('admin.roles.update', $role), [
            'name' => 'Супер',
            'permissions' => [],
        ])->assertRedirect();

        $this->assertSame(Permission::query()->count(), $role->fresh()->permissions()->count());
    }

    public function test_a_system_role_cannot_be_deleted(): void
    {
        $admin = $this->adminWith(['roles.delete']);
        $role = Role::factory()->system()->create();

        $this->actingAs($admin)
            ->delete(route('admin.roles.destroy', $role))
            ->assertSessionHas('error');

        $this->assertModelExists($role);
    }

    public function test_a_role_still_assigned_to_users_cannot_be_deleted(): void
    {
        $admin = $this->adminWith(['roles.delete']);
        $role = Role::factory()->create();
        User::factory()->create()->roles()->attach($role);

        $this->actingAs($admin)
            ->delete(route('admin.roles.destroy', $role))
            ->assertSessionHas('error');

        $this->assertModelExists($role);
    }

    public function test_an_unused_custom_role_can_be_deleted(): void
    {
        $admin = $this->adminWith(['roles.delete']);
        $role = Role::factory()->create();

        $this->actingAs($admin)
            ->delete(route('admin.roles.destroy', $role))
            ->assertRedirect(route('admin.roles.index'));

        $this->assertModelMissing($role);
    }
}
