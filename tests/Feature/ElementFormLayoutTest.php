<?php

namespace Tests\Feature;

use Illuminate\Foundation\Testing\RefreshDatabase;
use Nexor\Cms\Models\Iblock;
use Nexor\Cms\Models\IblockProperty;
use Nexor\Cms\Support\ElementFormLayout;
use Tests\Concerns\CreatesAdminUsers;
use Tests\TestCase;

/**
 * The element form is tabbed, and each infoblock may rearrange its own tabs.
 */
class ElementFormLayoutTest extends TestCase
{
    use CreatesAdminUsers, RefreshDatabase;

    public function test_the_schema_describes_the_default_tabs(): void
    {
        $iblock = Iblock::factory()->create();
        IblockProperty::factory()->for($iblock)->create(['code' => 'ARTICLE', 'name' => 'Артикул']);

        $admin = $this->grantIblock($this->adminWith(), $iblock);

        $response = $this->actingAs($admin)
            ->getJson("/admin/api/iblocks/{$iblock->id}/schema")
            ->assertOk();

        $this->assertSame(
            ['main', 'seo', 'preview', 'detail', 'sections'],
            array_column($response->json('form_tabs'), 'key'),
        );

        $this->assertSame(
            ['Основное', 'SEO', 'Анонс', 'Описание', 'Разделы'],
            array_column($response->json('form_tabs'), 'label'),
        );

        // Properties start out next to the base fields, on the first tab.
        $this->assertContains('prop:ARTICLE', $response->json('form_tabs.0.fields'));
        $this->assertSame(['meta_title', 'meta_description', 'meta_keywords'], $response->json('form_tabs.1.fields'));
    }

    public function test_the_field_catalogue_lists_base_fields_and_properties(): void
    {
        $iblock = Iblock::factory()->create();
        IblockProperty::factory()->for($iblock)->create(['code' => 'ARTICLE', 'name' => 'Артикул']);

        $admin = $this->grantIblock($this->adminWith(), $iblock);

        $fields = $this->actingAs($admin)
            ->getJson("/admin/api/iblocks/{$iblock->id}/schema")
            ->assertOk()
            ->json('form_fields');

        $this->assertCount(count(ElementFormLayout::baseFields()) + 1, $fields);

        $property = collect($fields)->firstWhere('key', 'prop:ARTICLE');

        $this->assertSame('Артикул', $property['label']);
        $this->assertSame('property', $property['group']);

        // Картинки анонса и описания — такие же базовые поля, их тоже можно
        // двигать по вкладкам.
        $keys = collect($fields)->pluck('key')->all();

        $this->assertContains('preview_picture', $keys);
        $this->assertContains('detail_picture', $keys);
    }

    public function test_a_layout_can_be_renamed_and_rearranged(): void
    {
        $iblock = Iblock::factory()->create();
        $admin = $this->adminWith(['iblocks.update']);

        $this->actingAs($admin)->putJson("/admin/api/iblocks/{$iblock->id}/form-layout", [
            'tabs' => [
                ['key' => 'main', 'label' => 'Карточка', 'fields' => ['name', 'code']],
                ['key' => 'texts', 'label' => 'Тексты', 'fields' => ['preview_text', 'detail_text']],
            ],
        ])->assertOk()->assertJsonPath('tabs.0.label', 'Карточка');

        $tabs = ElementFormLayout::for($iblock->refresh());

        $this->assertSame(['main', 'texts'], array_column($tabs, 'key'));
        $this->assertSame(['name', 'code'], array_slice($tabs[0]['fields'], 0, 2));
        $this->assertSame(['preview_text', 'detail_text'], $tabs[1]['fields']);
    }

    public function test_fields_left_out_of_the_layout_stay_on_the_first_tab(): void
    {
        $iblock = Iblock::factory()->create();
        $admin = $this->adminWith(['iblocks.update']);

        $this->actingAs($admin)->putJson("/admin/api/iblocks/{$iblock->id}/form-layout", [
            'tabs' => [['key' => 'main', 'label' => 'Всё', 'fields' => ['name']]],
        ])->assertOk();

        $tabs = ElementFormLayout::for($iblock->refresh());

        // Nothing may become uneditable just because it was not mentioned.
        $this->assertSame(array_keys(ElementFormLayout::baseFields()), $tabs[0]['fields']);
    }

    public function test_a_property_added_after_the_layout_was_saved_is_still_editable(): void
    {
        $iblock = Iblock::factory()->create();
        $admin = $this->adminWith(['iblocks.update']);

        $this->actingAs($admin)->putJson("/admin/api/iblocks/{$iblock->id}/form-layout", [
            'tabs' => [['key' => 'main', 'label' => 'Основное', 'fields' => ['name']]],
        ])->assertOk();

        IblockProperty::factory()->for($iblock)->create(['code' => 'LATE']);

        $this->assertContains('prop:LATE', ElementFormLayout::for($iblock->refresh())[0]['fields']);
    }

    public function test_a_field_that_no_longer_exists_is_dropped(): void
    {
        $iblock = Iblock::factory()->create();
        $property = IblockProperty::factory()->for($iblock)->create(['code' => 'GONE']);
        $admin = $this->adminWith(['iblocks.update']);

        $this->actingAs($admin)->putJson("/admin/api/iblocks/{$iblock->id}/form-layout", [
            'tabs' => [['key' => 'main', 'label' => 'Основное', 'fields' => ['name', 'prop:GONE', 'prop:NEVER']]],
        ])->assertOk();

        $property->delete();

        $fields = ElementFormLayout::for($iblock->refresh())[0]['fields'];

        $this->assertNotContains('prop:GONE', $fields);
        $this->assertNotContains('prop:NEVER', $fields);
    }

    public function test_an_empty_payload_restores_the_default_tabs(): void
    {
        $iblock = Iblock::factory()->create();
        $admin = $this->adminWith(['iblocks.update']);

        $this->actingAs($admin)->putJson("/admin/api/iblocks/{$iblock->id}/form-layout", [
            'tabs' => [['key' => 'only', 'label' => 'Одна', 'fields' => ['name']]],
        ])->assertOk();

        $this->actingAs($admin)
            ->putJson("/admin/api/iblocks/{$iblock->id}/form-layout", ['tabs' => []])
            ->assertOk();

        $this->assertSame(
            array_keys(ElementFormLayout::defaultTabs()),
            array_column(ElementFormLayout::for($iblock->refresh()), 'key'),
        );

        $this->assertArrayNotHasKey(ElementFormLayout::KEY, $iblock->settings ?? []);
    }

    public function test_the_layout_is_closed_to_users_without_the_permission(): void
    {
        $iblock = Iblock::factory()->create();

        $this->actingAs($this->adminWith())
            ->putJson("/admin/api/iblocks/{$iblock->id}/form-layout", ['tabs' => []])
            ->assertForbidden();
    }

    public function test_two_tabs_cannot_share_one_key(): void
    {
        $iblock = Iblock::factory()->create();
        $admin = $this->adminWith(['iblocks.update']);

        $this->actingAs($admin)->putJson("/admin/api/iblocks/{$iblock->id}/form-layout", [
            'tabs' => [
                ['key' => 'main', 'label' => 'Первая', 'fields' => ['name']],
                ['key' => 'main', 'label' => 'Вторая', 'fields' => ['code']],
            ],
        ])->assertOk();

        $keys = array_column(ElementFormLayout::for($iblock->refresh()), 'key');

        $this->assertCount(2, array_unique($keys));
    }
}
