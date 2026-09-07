<?php

namespace Tests\Feature;

use Illuminate\Foundation\Testing\RefreshDatabase;
use Nexor\Cms\Models\Iblock;
use Nexor\Cms\Models\IblockElement;
use Nexor\Cms\Models\IblockSection;
use Tests\Concerns\CreatesAdminUsers;
use Tests\TestCase;

/**
 * Sections behind the Vue panel: the tree the screen renders and the writes
 * its form makes.
 */
class SectionApiTest extends TestCase
{
    use CreatesAdminUsers, RefreshDatabase;

    public function test_the_tree_comes_back_ordered_with_its_depth(): void
    {
        $iblock = Iblock::factory()->create();

        $parent = IblockSection::factory()->create([
            'iblock_id' => $iblock->id, 'name' => 'Родитель', 'sort' => 100,
        ]);

        IblockSection::factory()->create([
            'iblock_id' => $iblock->id, 'parent_id' => $parent->id, 'name' => 'Ребёнок',
        ]);

        IblockSection::factory()->create([
            'iblock_id' => $iblock->id, 'name' => 'Второй', 'sort' => 200,
        ]);

        $admin = $this->grantIblock($this->adminWith(), $iblock);

        $response = $this->actingAs($admin)
            ->getJson("/admin/api/iblocks/{$iblock->id}/sections")
            ->assertOk();

        $this->assertSame(['Родитель', 'Второй', 'Ребёнок'], array_column($response->json('data'), 'name'));
        $this->assertSame([0, 0, 1], array_column($response->json('data'), 'depth'));
    }

    public function test_the_tree_reports_how_much_each_section_holds(): void
    {
        $iblock = Iblock::factory()->create();
        $section = IblockSection::factory()->create(['iblock_id' => $iblock->id]);
        IblockSection::factory()->create(['iblock_id' => $iblock->id, 'parent_id' => $section->id]);
        IblockElement::factory()->count(2)->for($iblock)->create(['section_id' => $section->id]);

        $admin = $this->grantIblock($this->adminWith(), $iblock);

        $this->actingAs($admin)
            ->getJson("/admin/api/iblocks/{$iblock->id}/sections")
            ->assertOk()
            ->assertJsonPath('data.0.elements_count', 2)
            ->assertJsonPath('data.0.children_count', 1);
    }

    public function test_the_tree_can_be_searched_by_name(): void
    {
        $iblock = Iblock::factory()->create();
        IblockSection::factory()->create(['iblock_id' => $iblock->id, 'name' => 'Обои']);
        IblockSection::factory()->create(['iblock_id' => $iblock->id, 'name' => 'Краски']);

        $admin = $this->grantIblock($this->adminWith(), $iblock);

        $response = $this->actingAs($admin)
            ->getJson("/admin/api/iblocks/{$iblock->id}/sections?search=Кра")
            ->assertOk();

        $this->assertSame(['Краски'], array_column($response->json('data'), 'name'));
    }

    public function test_a_section_can_be_created_under_a_parent(): void
    {
        $iblock = Iblock::factory()->create();
        $parent = IblockSection::factory()->create(['iblock_id' => $iblock->id]);
        $admin = $this->grantIblock($this->adminWith(), $iblock, ['view', 'create']);

        $this->actingAs($admin)->postJson("/admin/api/iblocks/{$iblock->id}/sections", [
            'name' => 'Вложенный',
            'code' => 'nested',
            'parent_id' => $parent->id,
            'is_active' => '1',
            'sort' => 300,
        ])->assertCreated()->assertJsonPath('data.name', 'Вложенный');

        $section = IblockSection::query()->where('code', 'nested')->firstOrFail();

        $this->assertSame(1, $section->depth);
        $this->assertSame("/{$parent->id}/", $section->path);
    }

    public function test_creating_a_section_needs_the_infoblocks_own_permission(): void
    {
        $iblock = Iblock::factory()->create();

        $viewer = $this->grantIblock($this->adminWith(), $iblock, ['view']);

        $this->actingAs($viewer)
            ->postJson("/admin/api/iblocks/{$iblock->id}/sections", ['name' => 'Нельзя'])
            ->assertForbidden();
    }

    public function test_a_section_can_be_moved_to_another_parent(): void
    {
        $iblock = Iblock::factory()->create();
        $first = IblockSection::factory()->create(['iblock_id' => $iblock->id]);
        $second = IblockSection::factory()->create(['iblock_id' => $iblock->id]);
        $moved = IblockSection::factory()->create(['iblock_id' => $iblock->id, 'parent_id' => $first->id]);

        $admin = $this->grantIblock($this->adminWith(), $iblock, ['view', 'update']);

        $this->actingAs($admin)->putJson("/admin/api/iblocks/{$iblock->id}/sections/{$moved->id}", [
            'name' => $moved->name,
            'parent_id' => $second->id,
        ])->assertOk();

        $this->assertSame("/{$second->id}/", $moved->refresh()->path);
    }

    public function test_deleting_a_section_takes_its_subtree_with_it(): void
    {
        $iblock = Iblock::factory()->create();
        $parent = IblockSection::factory()->create(['iblock_id' => $iblock->id]);
        $child = IblockSection::factory()->create(['iblock_id' => $iblock->id, 'parent_id' => $parent->id]);

        $admin = $this->grantIblock($this->adminWith(), $iblock, ['view', 'delete']);

        $this->actingAs($admin)
            ->deleteJson("/admin/api/iblocks/{$iblock->id}/sections/{$parent->id}")
            ->assertOk();

        $this->assertDatabaseMissing('iblock_sections', ['id' => $parent->id]);
        $this->assertDatabaseMissing('iblock_sections', ['id' => $child->id]);
    }

    public function test_a_section_of_another_infoblock_is_not_reachable(): void
    {
        $iblock = Iblock::factory()->create();
        $other = Iblock::factory()->create();
        $section = IblockSection::factory()->create(['iblock_id' => $other->id]);

        $admin = $this->grantIblock($this->adminWith(), $iblock, ['view', 'update']);

        $this->actingAs($admin)
            ->getJson("/admin/api/iblocks/{$iblock->id}/sections/{$section->id}")
            ->assertNotFound();
    }

    public function test_elements_can_be_listed_by_section_or_without_one(): void
    {
        $iblock = Iblock::factory()->create();
        $section = IblockSection::factory()->create(['iblock_id' => $iblock->id]);

        IblockElement::factory()->for($iblock)->create(['name' => 'В разделе', 'section_id' => $section->id]);
        IblockElement::factory()->for($iblock)->create(['name' => 'Сам по себе', 'section_id' => null]);

        $admin = $this->grantIblock($this->adminWith(), $iblock);

        $inside = $this->actingAs($admin)
            ->getJson("/admin/api/iblocks/{$iblock->id}/elements?section={$section->id}")
            ->assertOk();

        $this->assertSame(['В разделе'], array_column($inside->json('data'), 'name'));

        // The tree's «Без раздела» group asks for exactly this.
        $loose = $this->actingAs($admin)
            ->getJson("/admin/api/iblocks/{$iblock->id}/elements?section=none")
            ->assertOk();

        $this->assertSame(['Сам по себе'], array_column($loose->json('data'), 'name'));
    }

    public function test_an_infoblock_without_sections_has_no_tree(): void
    {
        $iblock = Iblock::factory()->withoutSections()->create();
        $admin = $this->grantIblock($this->adminWith(), $iblock);

        $this->actingAs($admin)
            ->getJson("/admin/api/iblocks/{$iblock->id}/sections")
            ->assertNotFound();
    }
}
