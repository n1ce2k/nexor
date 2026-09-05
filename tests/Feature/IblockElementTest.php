<?php

namespace Tests\Feature;

use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Storage;
use Nexor\Cms\Enums\PropertyType;
use Nexor\Cms\Models\Iblock;
use Nexor\Cms\Models\IblockElement;
use Nexor\Cms\Models\IblockProperty;
use Nexor\Cms\Models\IblockSection;
use Tests\Concerns\CreatesAdminUsers;
use Tests\TestCase;

class IblockElementTest extends TestCase
{
    use CreatesAdminUsers, RefreshDatabase;

    public function test_the_element_list_requires_the_infoblocks_own_view_permission(): void
    {
        $iblock = Iblock::factory()->create();

        $this->actingAs($this->adminWith())
            ->get(route('admin.iblocks.elements.index', $iblock))
            ->assertForbidden();

        $granted = $this->grantIblock($this->adminWith(), $iblock);

        $this->actingAs($granted)
            ->get(route('admin.iblocks.elements.index', $iblock))
            ->assertOk();
    }

    public function test_an_element_is_saved_with_values_for_every_property_type(): void
    {
        $iblock = Iblock::factory()->create();

        $string = IblockProperty::factory()->for($iblock)->create(['code' => 'ARTICLE']);
        $int = IblockProperty::factory()->for($iblock)->ofType(PropertyType::Integer)->create(['code' => 'WEIGHT']);
        $decimal = IblockProperty::factory()->for($iblock)->ofType(PropertyType::Decimal)->create(['code' => 'PRICE']);
        $bool = IblockProperty::factory()->for($iblock)->ofType(PropertyType::Boolean)->create(['code' => 'IN_STOCK']);
        $color = IblockProperty::factory()->for($iblock)->ofType(PropertyType::Color)->create(['code' => 'HEX']);
        $select = IblockProperty::factory()->for($iblock)->withEnums(['Матовый', 'Глянцевый'])->create(['code' => 'FINISH']);

        $admin = $this->grantIblock($this->adminWith(), $iblock, ['view', 'create']);

        $this->actingAs($admin)->post(route('admin.iblocks.elements.store', $iblock), [
            'name' => 'Тестовый элемент',
            'code' => 'test-element',
            'is_active' => '1',
            'sort' => 100,
            'properties' => [
                'ARTICLE' => 'ART-001',
                'WEIGHT' => '25',
                'PRICE' => '1499.90',
                'IN_STOCK' => '1',
                'HEX' => '#a1b2c3',
                'FINISH' => (string) $select->enums->first()->id,
            ],
        ])->assertRedirect(route('admin.iblocks.elements.index', $iblock));

        $element = IblockElement::query()->where('code', 'test-element')->firstOrFail();
        $values = $element->propertyValues();

        $this->assertSame('ART-001', $values['ARTICLE']);
        $this->assertSame(25, $values['WEIGHT']);
        $this->assertSame('1499.900000', $values['PRICE']);
        $this->assertTrue($values['IN_STOCK']);
        $this->assertSame('#A1B2C3', $values['HEX']);
        $this->assertSame('Матовый', $values['FINISH']);

        $this->assertSame($element->id, $element->values()->first()->element_id);
        unset($string, $int, $decimal, $bool, $color);
    }

    public function test_a_required_property_blocks_saving_when_left_empty(): void
    {
        $iblock = Iblock::factory()->create();
        IblockProperty::factory()->for($iblock)->required()->create(['code' => 'ARTICLE', 'name' => 'Артикул']);

        $admin = $this->grantIblock($this->adminWith(), $iblock, ['view', 'create']);

        $this->actingAs($admin)->post(route('admin.iblocks.elements.store', $iblock), [
            'name' => 'Без артикула',
            'properties' => ['ARTICLE' => ''],
        ])->assertSessionHasErrors('properties.ARTICLE');
    }

    public function test_an_integer_property_rejects_a_value_outside_its_range(): void
    {
        $iblock = Iblock::factory()->create();
        IblockProperty::factory()->for($iblock)->ofType(PropertyType::Integer)->create([
            'code' => 'WEIGHT',
            'settings' => ['min' => 1, 'max' => 100],
        ]);

        $admin = $this->grantIblock($this->adminWith(), $iblock, ['view', 'create']);

        $this->actingAs($admin)->post(route('admin.iblocks.elements.store', $iblock), [
            'name' => 'Слишком тяжёлый',
            'properties' => ['WEIGHT' => '500'],
        ])->assertSessionHasErrors('properties.WEIGHT');
    }

    public function test_a_multiple_property_stores_every_submitted_value(): void
    {
        $iblock = Iblock::factory()->create();
        IblockProperty::factory()->for($iblock)->multiple()->create(['code' => 'TAGS']);

        $admin = $this->grantIblock($this->adminWith(), $iblock, ['view', 'create']);

        $this->actingAs($admin)->post(route('admin.iblocks.elements.store', $iblock), [
            'name' => 'С тегами',
            'properties' => ['TAGS' => ['краска', 'фасад', '']],
        ])->assertRedirect();

        $element = IblockElement::query()->where('name', 'С тегами')->firstOrFail();

        $this->assertSame(['краска', 'фасад'], $element->propertyValues()['TAGS']->all());
    }

    public function test_updating_an_element_replaces_its_previous_values(): void
    {
        $iblock = Iblock::factory()->create();
        IblockProperty::factory()->for($iblock)->create(['code' => 'ARTICLE']);

        $admin = $this->grantIblock($this->adminWith(), $iblock, ['view', 'create', 'update']);

        $this->actingAs($admin)->post(route('admin.iblocks.elements.store', $iblock), [
            'name' => 'Элемент',
            'properties' => ['ARTICLE' => 'СТАРЫЙ'],
        ]);

        $element = IblockElement::query()->where('name', 'Элемент')->firstOrFail();

        $this->actingAs($admin)->put(route('admin.iblocks.elements.update', [$iblock, $element]), [
            'name' => 'Элемент',
            'properties' => ['ARTICLE' => 'НОВЫЙ'],
        ])->assertRedirect();

        $this->assertSame(1, $element->values()->count());
        $this->assertSame('НОВЫЙ', $element->fresh()->propertyValues()['ARTICLE']);
    }

    public function test_an_image_property_stores_the_uploaded_file(): void
    {
        Storage::fake('public');

        $iblock = Iblock::factory()->create();
        IblockProperty::factory()->for($iblock)->ofType(PropertyType::Image)->create(['code' => 'PHOTO']);

        $admin = $this->grantIblock($this->adminWith(), $iblock, ['view', 'create']);

        $this->actingAs($admin)->post(route('admin.iblocks.elements.store', $iblock), [
            'name' => 'С картинкой',
            'property_files' => ['PHOTO' => UploadedFile::fake()->image('swatch.jpg')],
        ])->assertRedirect();

        $element = IblockElement::query()->where('name', 'С картинкой')->firstOrFail();
        $path = $element->values()->firstOrFail()->value_string;

        Storage::disk('public')->assertExists($path);
        $this->assertStringStartsWith('properties/PHOTO/', $path);
    }

    public function test_an_element_can_belong_to_several_sections(): void
    {
        $iblock = Iblock::factory()->create();
        $main = IblockSection::factory()->create(['iblock_id' => $iblock->id]);
        $extra = IblockSection::factory()->create(['iblock_id' => $iblock->id]);

        $admin = $this->grantIblock($this->adminWith(), $iblock, ['view', 'create']);

        $this->actingAs($admin)->post(route('admin.iblocks.elements.store', $iblock), [
            'name' => 'В двух разделах',
            'section_id' => $main->id,
            'sections' => [$main->id, $extra->id],
        ])->assertRedirect();

        $element = IblockElement::query()->where('name', 'В двух разделах')->firstOrFail();

        $this->assertSame($main->id, $element->section_id);
        $this->assertCount(2, $element->sections);
    }

    public function test_the_list_can_be_filtered_by_a_filterable_property(): void
    {
        $iblock = Iblock::factory()->create();
        $property = IblockProperty::factory()->for($iblock)->create([
            'code' => 'ARTICLE',
            'is_filterable' => true,
        ]);

        $matching = IblockElement::factory()->for($iblock)->create(['name' => 'Подходит']);
        $other = IblockElement::factory()->for($iblock)->create(['name' => 'Не подходит']);

        $matching->values()->create(['property_id' => $property->id, 'value_string' => 'ART-777']);
        $other->values()->create(['property_id' => $property->id, 'value_string' => 'ART-000']);

        $admin = $this->grantIblock($this->adminWith(), $iblock);

        $this->actingAs($admin)
            ->get(route('admin.iblocks.elements.index', [$iblock, 'prop' => ['ARTICLE' => '777']]))
            ->assertOk()
            ->assertSee('Подходит')
            ->assertDontSee('Не подходит');
    }

    public function test_deleting_a_property_removes_its_values(): void
    {
        $iblock = Iblock::factory()->create();
        $property = IblockProperty::factory()->for($iblock)->create(['code' => 'ARTICLE']);
        $element = IblockElement::factory()->for($iblock)->create();

        $element->values()->create(['property_id' => $property->id, 'value_string' => 'ART-1']);

        $admin = $this->adminWith(['iblocks.update']);

        $this->actingAs($admin)
            ->delete(route('admin.iblocks.properties.destroy', [$iblock, $property]))
            ->assertRedirect();

        $this->assertSame(0, $element->values()->count());
    }

    public function test_an_element_of_another_infoblock_is_not_reachable(): void
    {
        $iblock = Iblock::factory()->create();
        $foreign = IblockElement::factory()->create();

        $admin = $this->grantIblock($this->adminWith(), $iblock, ['view', 'update']);

        $this->actingAs($admin)
            ->get(route('admin.iblocks.elements.edit', [$iblock, $foreign]))
            ->assertNotFound();
    }
}
