<?php

namespace Tests\Feature;

use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Hash;
use Nexor\Cms\Models\Role;
use Tests\Concerns\CreatesAdminUsers;
use Tests\TestCase;

class UserManagementTest extends TestCase
{
    use CreatesAdminUsers, RefreshDatabase;

    public function test_the_list_is_hidden_without_the_view_permission(): void
    {
        $this->actingAs($this->adminWith())
            ->get(route('admin.users.index'))
            ->assertForbidden();
    }

    public function test_users_are_listed_for_a_permitted_admin(): void
    {
        $admin = $this->adminWith(['users.view']);
        $other = User::factory()->create(['name' => 'Пётр Тестов']);

        $this->actingAs($admin)
            ->get(route('admin.users.index'))
            ->assertOk()
            ->assertSee($other->name);
    }

    public function test_a_user_can_be_created_with_roles(): void
    {
        $admin = $this->adminWith(['users.create']);
        $role = Role::factory()->create();

        $this->actingAs($admin)->post(route('admin.users.store'), [
            'name' => 'Новый Редактор',
            'email' => 'editor@example.test',
            'password' => 'secret123',
            'password_confirmation' => 'secret123',
            'is_active' => '1',
            'roles' => [$role->id],
        ])->assertRedirect(route('admin.users.index'));

        $created = User::query()->where('email', 'editor@example.test')->firstOrFail();

        $this->assertTrue($created->is_active);
        $this->assertTrue(Hash::check('secret123', $created->password));
        $this->assertTrue($created->roles->contains($role));
        $this->assertDatabaseHas('activity_logs', ['action' => 'created', 'subject_id' => $created->id]);
    }

    public function test_creating_a_user_requires_a_unique_email(): void
    {
        $admin = $this->adminWith(['users.create']);
        $existing = User::factory()->create();

        $this->actingAs($admin)->post(route('admin.users.store'), [
            'name' => 'Дубль',
            'email' => $existing->email,
            'password' => 'secret123',
            'password_confirmation' => 'secret123',
        ])->assertSessionHasErrors('email');
    }

    public function test_the_password_stays_unchanged_when_the_field_is_left_empty(): void
    {
        $admin = $this->adminWith(['users.update']);
        $user = User::factory()->create();
        $original = $user->password;

        $this->actingAs($admin)->put(route('admin.users.update', $user), [
            'name' => 'Переименован',
            'email' => $user->email,
            'password' => '',
            'password_confirmation' => '',
            'is_active' => '1',
        ])->assertRedirect(route('admin.users.index'));

        $user->refresh();

        $this->assertSame('Переименован', $user->name);
        $this->assertSame($original, $user->password);
    }

    public function test_an_admin_cannot_block_or_re_role_themselves(): void
    {
        $admin = $this->adminWith(['users.update']);
        $otherRole = Role::factory()->create();

        $this->actingAs($admin)->put(route('admin.users.update', $admin), [
            'name' => $admin->name,
            'email' => $admin->email,
            'is_active' => '0',
            'roles' => [$otherRole->id],
        ])->assertRedirect();

        $admin->refresh();

        $this->assertTrue($admin->is_active);
        $this->assertFalse($admin->roles->contains($otherRole));
    }

    public function test_an_admin_cannot_delete_their_own_account(): void
    {
        $admin = $this->adminWith(['users.delete']);

        $this->actingAs($admin)
            ->delete(route('admin.users.destroy', $admin))
            ->assertSessionHas('error');

        $this->assertNull($admin->fresh()->deleted_at);
    }

    public function test_another_user_can_be_deleted(): void
    {
        $admin = $this->adminWith(['users.delete']);
        $victim = User::factory()->create();

        $this->actingAs($admin)
            ->delete(route('admin.users.destroy', $victim))
            ->assertRedirect(route('admin.users.index'));

        $this->assertSoftDeleted($victim);
    }

    public function test_effective_permissions_come_from_all_assigned_roles(): void
    {
        $user = User::factory()->create();
        $user->roles()->attach([
            Role::factory()->withPermissions(['a.view'])->create()->id,
            Role::factory()->withPermissions(['b.update'])->create()->id,
        ]);

        $user = $user->fresh();

        $this->assertTrue($user->hasPermission('a.view'));
        $this->assertTrue($user->hasPermission('b.update'));
        $this->assertFalse($user->hasPermission('c.delete'));
    }

    public function test_a_super_admin_passes_every_permission_check(): void
    {
        $this->assertTrue($this->superAdmin()->hasPermission('anything.at.all'));
    }
}
