<?php

namespace Tests\Feature;

use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Storage;
use Nexor\Cms\Enums\PropertyType;
use Nexor\Cms\Models\UserField;
use Nexor\Cms\Support\Uploads;
use Tests\Concerns\CreatesAdminUsers;
use Tests\TestCase;

/**
 * Свои поля пользователей: набор полей, значения в карточке и выборка по ним.
 */
class UserFieldsTest extends TestCase
{
    use CreatesAdminUsers, RefreshDatabase;

    /**
     * @param  array<string, mixed>  $overrides
     * @return array<string, mixed>
     */
    protected function payload(User $user, array $overrides = []): array
    {
        return array_merge([
            'name' => $user->name,
            'login' => $user->login,
            'email' => $user->email,
            'is_active' => true,
        ], $overrides);
    }

    public function test_a_field_is_created_only_with_the_right(): void
    {
        $definition = [
            'code' => 'city',
            'name' => 'Город',
            'type' => 'select',
            'is_required' => true,
            'is_shown_in_list' => true,
            'is_filterable' => true,
            'settings' => ['options' => [['value' => 'msk', 'label' => 'Москва'], ['value' => 'spb', 'label' => 'Петербург']]],
        ];

        $this->actingAs($this->adminWith(['users.update']))
            ->postJson('/admin/api/user-fields', $definition)
            ->assertForbidden();

        $this->actingAs($this->adminWith(['user_fields.manage']))
            ->postJson('/admin/api/user-fields', $definition)
            ->assertCreated()
            ->assertJsonPath('data.code', 'city')
            ->assertJsonPath('data.enums.0.value', 'Москва');

        $this->assertSame(PropertyType::Select, UserField::query()->sole()->type);
    }

    public function test_the_code_is_checked(): void
    {
        UserField::factory()->create(['code' => 'city']);

        $this->actingAs($this->adminWith(['user_fields.manage']))
            ->postJson('/admin/api/user-fields', ['code' => 'city', 'name' => 'Город', 'type' => 'string'])
            ->assertJsonValidationErrors('code');

        $this->actingAs($this->adminWith(['user_fields.manage']))
            ->postJson('/admin/api/user-fields', ['code' => '2город', 'name' => 'Город', 'type' => 'element'])
            ->assertJsonValidationErrors(['code', 'type']);
    }

    public function test_settings_that_do_not_fit_the_type_are_dropped(): void
    {
        $this->actingAs($this->adminWith(['user_fields.manage']))
            ->postJson('/admin/api/user-fields', [
                'code' => 'about',
                'name' => 'О себе',
                'type' => 'text',
                'settings' => ['max_length' => 500, 'max_size' => 2048, 'options' => [['value' => 'x']]],
            ])
            ->assertCreated();

        $this->assertSame(['max_length' => 500], UserField::query()->sole()->settings);
    }

    public function test_values_are_saved_with_the_user(): void
    {
        UserField::factory()->create(['code' => 'city', 'name' => 'Город', 'type' => PropertyType::String, 'is_shown_in_list' => true]);
        UserField::factory()->create(['code' => 'age', 'name' => 'Возраст', 'type' => PropertyType::Integer]);

        $admin = $this->adminWith(['users.create', 'users.view']);

        $id = $this->actingAs($admin)
            ->postJson('/admin/api/users', [
                'name' => 'Новый',
                'login' => 'noviy',
                'email' => 'noviy@example.test',
                'password' => 'secret123',
                'password_confirmation' => 'secret123',
                'fields' => ['city' => 'Москва', 'age' => '33'],
            ])
            ->assertCreated()
            ->json('data.id');

        $response = $this->actingAs($admin)->getJson("/admin/api/users/{$id}")->assertOk();

        $this->assertSame('Москва', $response->json('data.fields.city'));
        $this->assertSame(33, $response->json('data.fields.age'));
        $this->assertSame('Москва', $response->json('data.list_fields.city'));

        // Значение лежит в своей колонке — по ней и стоит индекс.
        $this->assertDatabaseHas('user_field_values', ['user_id' => $id, 'value_int' => 33]);
    }

    public function test_a_required_field_is_checked(): void
    {
        UserField::factory()->create(['code' => 'city', 'name' => 'Город', 'is_required' => true]);
        $user = User::factory()->create();

        $this->actingAs($this->adminWith(['users.update']))
            ->putJson("/admin/api/users/{$user->id}", $this->payload($user))
            ->assertJsonValidationErrors('fields.city');

        $this->actingAs($this->adminWith(['users.update']))
            ->putJson("/admin/api/users/{$user->id}", $this->payload($user, ['fields' => ['city' => 'Москва']]))
            ->assertOk();
    }

    public function test_a_multiple_field_keeps_every_value(): void
    {
        UserField::factory()->create(['code' => 'skills', 'name' => 'Навыки', 'is_multiple' => true]);
        $user = User::factory()->create();

        $this->actingAs($this->adminWith(['users.update']))
            ->putJson("/admin/api/users/{$user->id}", $this->payload($user, [
                'fields' => ['skills' => ['PHP', '', 'Vue']],
            ]))
            ->assertOk();

        $this->assertSame(['PHP', 'Vue'], $user->fresh()->field('skills'));
    }

    public function test_a_select_only_takes_its_own_options(): void
    {
        UserField::factory()->create([
            'code' => 'city',
            'name' => 'Город',
            'type' => PropertyType::Select,
            'settings' => ['options' => [['value' => 'msk', 'label' => 'Москва']]],
        ]);

        $user = User::factory()->create();

        $this->actingAs($this->adminWith(['users.update']))
            ->putJson("/admin/api/users/{$user->id}", $this->payload($user, ['fields' => ['city' => 'perm']]))
            ->assertJsonValidationErrors('fields.city');

        $this->actingAs($this->adminWith(['users.update']))
            ->putJson("/admin/api/users/{$user->id}", $this->payload($user, ['fields' => ['city' => 'msk']]))
            ->assertOk()
            ->assertJsonPath('data.list_fields', []);

        $this->assertSame('msk', $user->fresh()->field('city'));
    }

    public function test_a_file_field_stores_and_removes_files(): void
    {
        Storage::fake(Uploads::disk());
        UserField::factory()->create(['code' => 'passport', 'name' => 'Скан', 'type' => PropertyType::File]);
        $user = User::factory()->create();
        $admin = $this->adminWith(['users.update', 'users.view']);

        $this->actingAs($admin)
            ->put("/admin/api/users/{$user->id}", $this->payload($user, [
                'field_files' => ['passport' => UploadedFile::fake()->create('scan.pdf', 20, 'application/pdf')],
            ]))
            ->assertOk();

        $stored = $user->fresh()->fieldValues->sole();
        Storage::disk(Uploads::disk())->assertExists($stored->value_string);

        $response = $this->actingAs($admin)->getJson("/admin/api/users/{$user->id}")->assertOk();
        $this->assertSame($stored->id, $response->json('data.fields.passport.0.id'));

        $this->actingAs($admin)
            ->put("/admin/api/users/{$user->id}", $this->payload($user, [
                'field_remove' => ['passport' => [$stored->id]],
            ]))
            ->assertOk();

        $this->assertSame(0, $user->fresh()->fieldValues()->count());
        Storage::disk(Uploads::disk())->assertMissing($stored->value_string);
    }

    public function test_users_can_be_filtered_by_a_field(): void
    {
        UserField::factory()->create(['code' => 'city', 'name' => 'Город', 'is_filterable' => true]);
        UserField::factory()->create(['code' => 'note', 'name' => 'Заметка']);

        $admin = $this->adminWith(['users.view', 'users.update']);
        $moscow = User::factory()->create(['name' => 'Москвич']);
        $other = User::factory()->create(['name' => 'Питерец']);

        $this->actingAs($admin)->putJson("/admin/api/users/{$moscow->id}", $this->payload($moscow, ['fields' => ['city' => 'Москва', 'note' => 'тест']]))->assertOk();
        $this->actingAs($admin)->putJson("/admin/api/users/{$other->id}", $this->payload($other, ['fields' => ['city' => 'Петербург']]))->assertOk();

        $names = $this->actingAs($admin)->getJson('/admin/api/users?fields[city]=Моск')->assertOk()->json('data.*.name');
        $this->assertSame(['Москвич'], $names);

        // Поле без флага «в фильтре» выборку не сужает.
        $this->assertCount(3, $this->actingAs($admin)->getJson('/admin/api/users?fields[note]=тест')->json('data'));
    }

    public function test_deleting_a_field_removes_its_values(): void
    {
        $field = UserField::factory()->create(['code' => 'city', 'name' => 'Город']);
        $user = User::factory()->create();

        $this->actingAs($this->adminWith(['users.update']))
            ->putJson("/admin/api/users/{$user->id}", $this->payload($user, ['fields' => ['city' => 'Москва']]))
            ->assertOk();

        $this->actingAs($this->adminWith(['user_fields.manage']))
            ->deleteJson("/admin/api/user-fields/{$field->id}")
            ->assertOk();

        $this->assertSame(0, $user->fresh()->fieldValues()->count());
    }

    public function test_the_schema_feeds_the_user_card(): void
    {
        UserField::factory()->create(['code' => 'city', 'name' => 'Город', 'sort' => 100]);
        UserField::factory()->create(['code' => 'hidden', 'name' => 'Скрытое', 'is_active' => false]);

        $response = $this->actingAs($this->adminWith(['users.view']))
            ->getJson('/admin/api/users/schema')
            ->assertOk();

        $this->assertSame(['city'], array_column($response->json('fields'), 'code'));
    }
}
