<?php

namespace Tests\Feature;

use Illuminate\Foundation\Testing\RefreshDatabase;
use Nexor\Cms\Enums\PropertyType;
use Nexor\Cms\Models\Iblock;
use Nexor\Cms\Models\IblockProperty;
use Nexor\Cms\Models\IblockSection;
use Nexor\Cms\Models\IblockType;
use Nexor\Cms\Models\Permission;
use Tests\Concerns\CreatesAdminUsers;
use Tests\TestCase;

class IblockStructureTest extends TestCase
{
    use CreatesAdminUsers, RefreshDatabase;

    public function test_an_infoblock_can_be_created_and_gets_its_own_permissions(): void
    {
        $admin = $this->adminWith(['iblocks.create']);
        $type = IblockType::factory()->create();

        $this->actingAs($admin)->post(route('admin.iblocks.store'), [
            'iblock_type_id' => $type->id,
            'code' => 'news',
            'name' => 'Новости',
            'has_sections' => '1',
            'is_active' => '1',
            'sort' => 100,
        ])->assertRedirect();

        $iblock = Iblock::query()->where('code', 'news')->firstOrFail();

        foreach (Iblock::abilities() as $ability) {
            $this->assertDatabaseHas('permissions', ['code' => $iblock->permissionCode($ability)]);
        }
    }

    public function test_renaming_an_infoblock_keeps_its_permission_codes(): void
    {
        $iblock = Iblock::factory()->create(['code' => 'news', 'name' => 'Новости']);
        $codeBefore = $iblock->permissionCode('view');

        $iblock->update(['code' => 'articles', 'name' => 'Статьи']);

        $this->assertSame($codeBefore, $iblock->fresh()->permissionCode('view'));
        $this->assertDatabaseHas('permissions', [
            'code' => $codeBefore,
            'name' => 'Просмотр: Статьи',
        ]);
    }

    public function test_a_property_can_be_created_with_type_specific_settings(): void
    {
        $admin = $this->adminWith(['iblocks.update']);
        $iblock = Iblock::factory()->create();

        $this->actingAs($admin)->post(route('admin.iblocks.properties.store', $iblock), [
            'code' => 'PRICE',
            'name' => 'Цена',
            'type' => PropertyType::Decimal->value,
            'is_required' => '1',
            'is_filterable' => '1',
            'sort' => 100,
            'settings' => ['min' => 0, 'step' => 0.01, 'suffix' => '₽', 'rows' => 5],
        ])->assertRedirect(route('admin.iblocks.properties.index', $iblock));

        $property = IblockProperty::query()->where('code', 'PRICE')->firstOrFail();

        $this->assertSame(PropertyType::Decimal, $property->type);
        $this->assertTrue($property->is_required);
        $this->assertSame('₽', $property->setting('suffix'));
        // `rows` belongs to text types, so it must not survive on a decimal property.
        $this->assertNull($property->setting('rows'));
    }

    public function test_a_property_can_turn_on_value_descriptions(): void
    {
        $admin = $this->adminWith(['iblocks.update']);
        $iblock = Iblock::factory()->create();

        $this->actingAs($admin)->post(route('admin.iblocks.properties.store', $iblock), [
            'code' => 'MATERIAL',
            'name' => 'Материал',
            'type' => PropertyType::String->value,
            'with_description' => '1',
        ])->assertRedirect(route('admin.iblocks.properties.index', $iblock));

        $property = IblockProperty::query()->where('code', 'MATERIAL')->firstOrFail();

        $this->assertTrue($property->with_description);

        $this->actingAs($admin)
            ->get(route('admin.iblocks.properties.edit', [$iblock, $property]))
            ->assertOk()
            ->assertSee('Выводить поле для описания свойства');
    }

    public function test_a_select_property_stores_its_options(): void
    {
        $admin = $this->adminWith(['iblocks.update']);
        $iblock = Iblock::factory()->create();

        $this->actingAs($admin)->post(route('admin.iblocks.properties.store', $iblock), [
            'code' => 'SIZE',
            'name' => 'Размер',
            'type' => PropertyType::Select->value,
            'enums' => [
                ['value' => 'Малый', 'code' => 's', 'sort' => 100, 'is_default' => '1'],
                ['value' => 'Средний', 'code' => 'm', 'sort' => 200],
                ['value' => '', 'code' => 'ignored'],
            ],
        ])->assertRedirect();

        $property = IblockProperty::query()->where('code', 'SIZE')->firstOrFail();

        $this->assertCount(2, $property->enums);
        $this->assertSame('Малый', $property->enums->first()->value);
        $this->assertTrue($property->enums->first()->is_default);
    }

    public function test_the_property_code_must_be_unique_within_the_infoblock(): void
    {
        $admin = $this->adminWith(['iblocks.update']);
        $iblock = Iblock::factory()->create();
        IblockProperty::factory()->create(['iblock_id' => $iblock->id, 'code' => 'COLOR']);

        $this->actingAs($admin)->post(route('admin.iblocks.properties.store', $iblock), [
            'code' => 'COLOR',
            'name' => 'Дубль',
            'type' => PropertyType::String->value,
        ])->assertSessionHasErrors('code');
    }

    public function test_the_same_property_code_is_allowed_in_another_infoblock(): void
    {
        $admin = $this->adminWith(['iblocks.update']);
        $first = Iblock::factory()->create();
        $second = Iblock::factory()->create();

        IblockProperty::factory()->create(['iblock_id' => $first->id, 'code' => 'COLOR']);

        $this->actingAs($admin)->post(route('admin.iblocks.properties.store', $second), [
            'code' => 'COLOR',
            'name' => 'Цвет',
            'type' => PropertyType::Color->value,
        ])->assertSessionHasNoErrors();
    }

    public function test_sections_keep_their_depth_and_path_in_sync(): void
    {
        $iblock = Iblock::factory()->create();

        $root = IblockSection::factory()->create(['iblock_id' => $iblock->id]);
        $child = IblockSection::factory()->childOf($root)->create();
        $grandchild = IblockSection::factory()->childOf($child)->create();

        $this->assertSame(0, $root->fresh()->depth);
        $this->assertSame(1, $child->fresh()->depth);
        $this->assertSame(2, $grandchild->fresh()->depth);
        $this->assertSame("/{$root->id}/{$child->id}/", $grandchild->fresh()->path);
        $this->assertTrue($root->descendants()->contains($grandchild));
    }

    public function test_moving_a_section_reindexes_its_whole_subtree(): void
    {
        $iblock = Iblock::factory()->create();

        $first = IblockSection::factory()->create(['iblock_id' => $iblock->id]);
        $second = IblockSection::factory()->create(['iblock_id' => $iblock->id]);
        $child = IblockSection::factory()->childOf($first)->create();
        $grandchild = IblockSection::factory()->childOf($child)->create();

        $child->update(['parent_id' => $second->id]);

        $this->assertSame(1, $child->fresh()->depth);
        $this->assertSame(2, $grandchild->fresh()->depth);
        $this->assertSame("/{$second->id}/{$child->id}/", $grandchild->fresh()->path);
    }

    public function test_a_section_cannot_be_its_own_parent(): void
    {
        $iblock = Iblock::factory()->create();
        $section = IblockSection::factory()->create(['iblock_id' => $iblock->id]);

        $admin = $this->grantIblock($this->adminWith(), $iblock, ['view', 'update']);

        $this->actingAs($admin)
            ->put(route('admin.iblocks.sections.update', [$iblock, $section]), [
                'name' => $section->name,
                'parent_id' => $section->id,
            ])
            ->assertSessionHasErrors('parent_id');
    }

    public function test_deleting_an_infoblock_permission_row_follows_a_force_delete(): void
    {
        $iblock = Iblock::factory()->create();
        $code = $iblock->permissionCode('view');

        $this->assertDatabaseHas('permissions', ['code' => $code]);

        $iblock->forceDelete();

        $this->assertSame(0, Permission::query()->where('code', $code)->count());
    }
}
