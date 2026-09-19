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

/**
 * The Vue panel talks to the site over this API, so these tests stand in for
 * clicking through it: they cover the shell, the bootstrap payload, the schema
 * the element form builds itself from, and writing values back.
 */
class PanelApiTest extends TestCase
{
    use CreatesAdminUsers, RefreshDatabase;

    public function test_the_panel_shell_renders_for_an_admin(): void
    {
        $this->actingAs($this->adminWith())
            ->get('/admin')
            ->assertOk()
            ->assertSee('id="nexor-panel"', false)
            ->assertSee('nexor-api', false);
    }

    public function test_the_panel_shell_is_closed_to_guests(): void
    {
        $this->get('/admin')->assertRedirect(route('admin.login'));
    }

    public function test_deep_panel_urls_render_the_same_shell(): void
    {
        $this->actingAs($this->adminWith())
            ->get('/admin/iblocks/7/elements')
            ->assertOk()
            ->assertSee('id="nexor-panel"', false);
    }

    public function test_bootstrap_describes_the_signed_in_user(): void
    {
        $iblock = Iblock::factory()->create();
        $admin = $this->grantIblock($this->adminWith(['iblocks.view']), $iblock);

        $response = $this->actingAs($admin)->getJson('/admin/api/bootstrap')->assertOk();

        $response->assertJsonPath('user.email', $admin->email);
        $response->assertJsonPath('is_super_admin', false);
        $this->assertContains('iblocks.view', $response->json('permissions'));
        $this->assertSame([$iblock->id], array_column($response->json('iblocks'), 'id'));

        // The field registry is driven by this catalogue.
        $this->assertCount(count(PropertyType::cases()), $response->json('property_types'));
    }

    public function test_bootstrap_hides_infoblocks_the_user_may_not_open(): void
    {
        Iblock::factory()->create();

        $response = $this->actingAs($this->adminWith())->getJson('/admin/api/bootstrap')->assertOk();

        $this->assertSame([], $response->json('iblocks'));
    }

    public function test_the_schema_endpoint_describes_the_element_form(): void
    {
        $iblock = Iblock::factory()->create();
        IblockSection::factory()->create(['iblock_id' => $iblock->id, 'name' => 'Раздел']);

        IblockProperty::factory()->for($iblock)->create(['code' => 'ARTICLE', 'name' => 'Артикул']);
        IblockProperty::factory()->for($iblock)->withEnums(['Матовый', 'Глянцевый'])->create(['code' => 'FINISH']);

        $linked = Iblock::factory()->create();
        IblockElement::factory()->for($linked)->create(['name' => 'Связанный элемент']);

        IblockProperty::factory()->for($iblock)->ofType(PropertyType::Element)->create([
            'code' => 'RELATED',
            'settings' => ['link_iblock_id' => $linked->id],
        ]);

        $admin = $this->grantIblock($this->adminWith(), $iblock);

        $response = $this->actingAs($admin)
            ->getJson("/admin/api/iblocks/{$iblock->id}/schema")
            ->assertOk();

        $response->assertJsonPath('iblock.id', $iblock->id);
        $this->assertCount(3, $response->json('properties'));
        $this->assertCount(1, $response->json('sections'));
        $this->assertCount(2, $response->json('properties.1.enums'));
        $this->assertSame('Связанный элемент', $response->json('options.RELATED.0.label'));
    }

    public function test_an_element_can_be_created_through_the_api(): void
    {
        $iblock = Iblock::factory()->create();
        IblockProperty::factory()->for($iblock)->create(['code' => 'ARTICLE']);
        IblockProperty::factory()->for($iblock)->ofType(PropertyType::Color)->create(['code' => 'HEX']);
        IblockProperty::factory()->for($iblock)->multiple()->create(['code' => 'TAGS']);

        $admin = $this->grantIblock($this->adminWith(), $iblock, ['view', 'create']);

        $response = $this->actingAs($admin)->postJson("/admin/api/iblocks/{$iblock->id}/elements", [
            'name' => 'Через API',
            'code' => 'via-api',
            'is_active' => '1',
            'sort' => 100,
            'properties' => [
                'ARTICLE' => 'ART-42',
                'HEX' => '#00FF88',
                'TAGS' => ['первый', 'второй'],
            ],
        ])->assertCreated();

        $element = IblockElement::query()->where('code', 'via-api')->firstOrFail();
        $values = $element->propertyValues();

        $response->assertJsonPath('data.name', 'Через API');
        $this->assertSame('ART-42', $values['ARTICLE']);
        $this->assertSame('#00FF88', $values['HEX']);
        $this->assertSame(['первый', 'второй'], $values['TAGS']->all());
    }

    public function test_validation_errors_come_back_as_json(): void
    {
        $iblock = Iblock::factory()->create();
        IblockProperty::factory()->for($iblock)->required()->create(['code' => 'ARTICLE', 'name' => 'Артикул']);

        $admin = $this->grantIblock($this->adminWith(), $iblock, ['view', 'create']);

        $this->actingAs($admin)
            ->postJson("/admin/api/iblocks/{$iblock->id}/elements", ['name' => '', 'properties' => ['ARTICLE' => '']])
            ->assertStatus(422)
            ->assertJsonValidationErrors(['name', 'properties.ARTICLE']);
    }

    public function test_an_image_property_accepts_an_upload(): void
    {
        Storage::fake('public');

        $iblock = Iblock::factory()->create();
        IblockProperty::factory()->for($iblock)->ofType(PropertyType::Image)->create(['code' => 'PHOTO']);

        $admin = $this->grantIblock($this->adminWith(), $iblock, ['view', 'create']);

        $this->actingAs($admin)->post("/admin/api/iblocks/{$iblock->id}/elements", [
            'name' => 'С картинкой',
            'property_files' => ['PHOTO' => UploadedFile::fake()->image('swatch.jpg')],
        ], ['Accept' => 'application/json'])->assertCreated();

        $element = IblockElement::query()->where('name', 'С картинкой')->firstOrFail();

        Storage::disk('public')->assertExists($element->values()->firstOrFail()->value_string);
    }

    public function test_the_element_list_returns_display_values_for_list_columns(): void
    {
        $iblock = Iblock::factory()->create();
        $property = IblockProperty::factory()->for($iblock)->create([
            'code' => 'ARTICLE',
            'is_shown_in_list' => true,
        ]);

        $element = IblockElement::factory()->for($iblock)->create(['name' => 'Элемент']);
        $element->values()->create(['property_id' => $property->id, 'value_string' => 'ART-9']);

        $admin = $this->grantIblock($this->adminWith(), $iblock);

        $this->actingAs($admin)
            ->getJson("/admin/api/iblocks/{$iblock->id}/elements")
            ->assertOk()
            ->assertJsonPath('data.0.display.ARTICLE', 'ART-9');
    }

    public function test_the_api_enforces_the_infoblocks_own_permissions(): void
    {
        $iblock = Iblock::factory()->create();

        $this->actingAs($this->adminWith())
            ->getJson("/admin/api/iblocks/{$iblock->id}/elements")
            ->assertForbidden();

        $viewer = $this->grantIblock($this->adminWith(), $iblock, ['view']);

        $this->actingAs($viewer)
            ->postJson("/admin/api/iblocks/{$iblock->id}/elements", ['name' => 'Нельзя'])
            ->assertForbidden();
    }

    public function test_a_property_can_be_created_and_its_enums_saved(): void
    {
        $iblock = Iblock::factory()->create();
        $admin = $this->adminWith(['iblocks.update']);

        $this->actingAs($admin)->postJson("/admin/api/iblocks/{$iblock->id}/properties", [
            'code' => 'SIZE',
            'name' => 'Размер',
            'type' => PropertyType::Select->value,
            'enums' => [
                ['value' => 'Малый', 'code' => 's', 'sort' => 100, 'is_default' => true],
                ['value' => 'Большой', 'code' => 'l', 'sort' => 200, 'is_default' => false],
            ],
        ])->assertCreated();

        $property = IblockProperty::query()->where('code', 'SIZE')->firstOrFail();

        $this->assertSame(PropertyType::Select, $property->type);
        $this->assertCount(2, $property->enums);
    }

    public function test_a_property_can_ask_for_value_descriptions(): void
    {
        $iblock = Iblock::factory()->create();
        $admin = $this->adminWith(['iblocks.update']);

        $id = $this->actingAs($admin)->postJson("/admin/api/iblocks/{$iblock->id}/properties", [
            'code' => 'MATERIAL',
            'name' => 'Материал',
            'type' => PropertyType::String->value,
            'with_description' => true,
        ])->assertCreated()->json('data.id');

        $this->assertTrue(IblockProperty::query()->findOrFail($id)->with_description);

        $this->actingAs($admin)
            ->getJson("/admin/api/iblocks/{$iblock->id}/properties/{$id}")
            ->assertOk()
            ->assertJsonPath('data.with_description', true);
    }

    public function test_a_value_keeps_its_description(): void
    {
        $iblock = Iblock::factory()->create();
        $property = IblockProperty::factory()->for($iblock)->create([
            'code' => 'MATERIAL',
            'type' => PropertyType::String,
            'with_description' => true,
        ]);

        $admin = $this->grantIblock($this->adminWith(), $iblock, ['view', 'create', 'update']);

        $id = $this->actingAs($admin)->postJson("/admin/api/iblocks/{$iblock->id}/elements", [
            'name' => 'Стул',
            'properties' => ['MATERIAL' => 'Дуб'],
            'property_descriptions' => ['MATERIAL' => 'Массив, без шпона'],
        ])->assertCreated()->json('data.id');

        $element = IblockElement::query()->findOrFail($id);

        $this->assertSame('Дуб', $element->property('MATERIAL'));
        $this->assertSame('Массив, без шпона', $element->propertyDescription('MATERIAL'));

        // Форма получает описание обратно и не теряет его при следующем сохранении.
        $this->actingAs($admin)
            ->getJson("/admin/api/iblocks/{$iblock->id}/elements/{$id}")
            ->assertOk()
            ->assertJsonPath('data.property_descriptions.MATERIAL', 'Массив, без шпона');

        $this->actingAs($admin)->putJson("/admin/api/iblocks/{$iblock->id}/elements/{$id}", [
            'name' => 'Стул',
            'properties' => ['MATERIAL' => 'Дуб'],
            'property_descriptions' => ['MATERIAL' => ''],
        ])->assertOk();

        $this->assertNull($element->fresh()->propertyDescription('MATERIAL'));
    }

    public function test_descriptions_follow_their_values_in_a_multiple_property(): void
    {
        $iblock = Iblock::factory()->create();
        IblockProperty::factory()->for($iblock)->create([
            'code' => 'LINKS',
            'type' => PropertyType::String,
            'is_multiple' => true,
            'with_description' => true,
        ]);

        $admin = $this->grantIblock($this->adminWith(), $iblock, ['view', 'create']);

        $id = $this->actingAs($admin)->postJson("/admin/api/iblocks/{$iblock->id}/elements", [
            'name' => 'Каталоги',
            // Пустое значение посередине уезжает вместе со своим описанием.
            'properties' => ['LINKS' => ['/catalog.pdf', '', '/price.pdf']],
            'property_descriptions' => ['LINKS' => ['Каталог 2026', 'потеряшка', 'Прайс']],
        ])->assertCreated()->json('data.id');

        $element = IblockElement::query()->findOrFail($id);

        $this->assertSame(['/catalog.pdf', '/price.pdf'], $element->property('LINKS')->all());
        $this->assertSame(['Каталог 2026', 'Прайс'], $element->propertyDescription('LINKS')->all());
    }

    public function test_a_description_is_ignored_while_the_property_does_not_ask_for_it(): void
    {
        $iblock = Iblock::factory()->create();
        IblockProperty::factory()->for($iblock)->create([
            'code' => 'MATERIAL',
            'type' => PropertyType::String,
            'with_description' => false,
        ]);

        $admin = $this->grantIblock($this->adminWith(), $iblock, ['view', 'create']);

        $id = $this->actingAs($admin)->postJson("/admin/api/iblocks/{$iblock->id}/elements", [
            'name' => 'Стул',
            'properties' => ['MATERIAL' => 'Дуб'],
            'property_descriptions' => ['MATERIAL' => 'Не должно сохраниться'],
        ])->assertCreated()->json('data.id');

        $this->assertNull(IblockElement::query()->findOrFail($id)->propertyDescription('MATERIAL'));
    }

    public function test_an_element_saves_without_any_section(): void
    {
        $iblock = Iblock::factory()->create();
        IblockSection::factory()->create(['iblock_id' => $iblock->id]);

        $admin = $this->grantIblock($this->adminWith(), $iblock, ['view', 'create']);

        // An untouched multi-select used to arrive as one blank entry.
        $this->actingAs($admin)->postJson("/admin/api/iblocks/{$iblock->id}/elements", [
            'name' => 'Без раздела',
            'sections' => [''],
        ])->assertCreated();

        $element = IblockElement::query()->where('name', 'Без раздела')->firstOrFail();

        $this->assertNull($element->section_id);
        $this->assertCount(0, $element->sections);
    }

    public function test_an_element_saves_when_sections_are_not_sent_at_all(): void
    {
        $iblock = Iblock::factory()->create();
        $admin = $this->grantIblock($this->adminWith(), $iblock, ['view', 'create']);

        $this->actingAs($admin)
            ->postJson("/admin/api/iblocks/{$iblock->id}/elements", ['name' => 'Совсем без разделов'])
            ->assertCreated();

        $this->assertDatabaseHas('iblock_elements', ['name' => 'Совсем без разделов', 'section_id' => null]);
    }

    public function test_an_infoblock_says_how_its_add_button_should_read(): void
    {
        $named = Iblock::factory()->create(['element_name' => 'товар']);
        $plain = Iblock::factory()->create(['element_name' => null]);

        $admin = $this->grantIblock($this->grantIblock($this->adminWith(), $named), $plain);

        $response = $this->actingAs($admin)->getJson('/admin/api/bootstrap')->assertOk();

        $labels = collect($response->json('iblocks'))->pluck('add_element_label', 'id');

        $this->assertSame('Добавить товар', $labels[$named->id]);
        $this->assertSame('Добавить', $labels[$plain->id]);
    }

    public function test_the_entity_name_is_saved_with_the_infoblock(): void
    {
        $iblock = Iblock::factory()->create(['element_name' => null]);
        $admin = $this->adminWith(['iblocks.update']);

        $this->actingAs($admin)->putJson("/admin/api/iblocks/{$iblock->id}", [
            'iblock_type_id' => $iblock->iblock_type_id,
            'code' => $iblock->code,
            'name' => $iblock->name,
            'element_name' => 'статью',
        ])->assertOk()->assertJsonPath('data.add_element_label', 'Добавить статью');

        $this->assertSame('статью', $iblock->refresh()->element_name);
    }

    public function test_the_dashboard_endpoint_returns_stats(): void
    {
        Iblock::factory()->count(2)->create();

        $this->actingAs($this->superAdmin())
            ->getJson('/admin/api/dashboard')
            ->assertOk()
            ->assertJsonPath('stats.2.key', 'iblocks')
            ->assertJsonPath('stats.2.value', 2);
    }
}
