<?php

namespace Tests\Feature;

use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Blade;
use Nexor\Cms\Enums\License;
use Nexor\Cms\Models\Iblock;
use Nexor\Cms\Models\IblockElement;
use Nexor\Cms\Support\Modules\Module;
use Nexor\Cms\Support\Nexor;
use Nexor\Cms\Support\Permissions;
use Tests\Concerns\CreatesAdminUsers;
use Tests\TestCase;

/**
 * Лицензии, модули и функции по уровням.
 */
class ModulesTest extends TestCase
{
    use CreatesAdminUsers, RefreshDatabase;

    protected function registerModule(License $license = License::Lite): Module
    {
        $module = new class($license) extends Module
        {
            public function __construct(private License $required) {}

            public function code(): string
            {
                return 'demo';
            }

            public function name(): string
            {
                return 'Демо';
            }

            public function license(): License
            {
                return $this->required;
            }

            public function features(): array
            {
                return [
                    'demo.basic' => ['label' => 'Базовое', 'license' => License::Lite],
                    'demo.extra' => ['label' => 'Расширенное', 'license' => License::Standart],
                ];
            }

            public function permissions(): array
            {
                return ['demo' => ['label' => 'Демо', 'sort' => 900, 'items' => ['demo.view' => 'Смотреть демо']]];
            }

            public function defaultSettings(): array
            {
                return ['mode' => 'page', 'rates' => ['USD' => 90]];
            }
        };

        Nexor::modules()->register($module);

        return $module;
    }

    // ---------------------------------------------------------------- лицензия

    public function test_the_license_comes_from_the_environment(): void
    {
        config(['nexor.license' => 'standart']);

        $this->assertSame(License::Standart, Nexor::license());
    }

    public function test_the_old_spelling_of_standart_still_works(): void
    {
        config(['nexor.license' => 'standard']);

        $this->assertSame(License::Standart, Nexor::license());
    }

    public function test_a_typo_in_the_license_never_unlocks_anything(): void
    {
        config(['nexor.license' => 'prooo']);

        $this->assertSame(License::Lite, Nexor::license());
    }

    public function test_a_higher_license_includes_the_lower_one(): void
    {
        $this->assertTrue(License::Pro->allows(License::Standart));
        $this->assertTrue(License::Standart->allows(License::Standart));
        $this->assertFalse(License::Lite->allows(License::Standart));
    }

    // ----------------------------------------------------------------- функции

    public function test_features_follow_the_license(): void
    {
        $this->registerModule();

        config(['nexor.license' => 'lite']);

        $this->assertTrue(Nexor::feature('demo'));
        $this->assertTrue(Nexor::feature('demo.basic'));
        $this->assertFalse(Nexor::feature('demo.extra'));
        $this->assertFalse(Nexor::feature('catalog.offers'));

        config(['nexor.license' => 'standart']);

        $this->assertTrue(Nexor::feature('demo.extra'));
        $this->assertTrue(Nexor::feature('catalog.offers'));
    }

    public function test_an_unknown_feature_is_never_available(): void
    {
        $this->assertFalse(Nexor::feature('nothing.here'));
    }

    public function test_a_module_above_the_license_is_off_with_all_its_features(): void
    {
        $this->registerModule(License::Pro);

        config(['nexor.license' => 'standart']);

        $this->assertFalse(Nexor::feature('demo'));
        $this->assertFalse(Nexor::feature('demo.basic'));
    }

    public function test_a_switched_off_module_hides_its_features(): void
    {
        $this->registerModule();

        Nexor::modules()->setEnabled('demo', false);

        $this->assertFalse(Nexor::feature('demo'));
        $this->assertFalse(Nexor::feature('demo.basic'));
    }

    public function test_the_feature_directive_hides_template_parts(): void
    {
        config(['nexor.license' => 'lite']);

        $html = Blade::render("@feature('catalog.offers') есть @else нет @endfeature");

        $this->assertStringContainsString('нет', $html);
    }

    // --------------------------------------------------------------- настройки

    public function test_module_settings_lie_over_the_defaults(): void
    {
        $this->registerModule();

        Nexor::modules()->updateSettings('demo', ['mode' => 'offcanvas']);

        $settings = Nexor::modules()->settings('demo');

        $this->assertSame('offcanvas', $settings['mode']);
        $this->assertSame(['USD' => 90], $settings['rates']);
    }

    // ----------------------------------------------------------------- панель

    public function test_the_modules_page_describes_the_license_and_the_modules(): void
    {
        $this->registerModule(License::Pro);

        config(['nexor.license' => 'standart']);

        $response = $this->actingAs($this->adminWith(['modules.view']))
            ->getJson('/admin/api/modules')
            ->assertOk()
            ->assertJsonPath('license.value', 'standart');

        $demo = collect($response->json('modules'))->firstWhere('code', 'demo');

        $this->assertFalse($demo['is_licensed']);
        $this->assertFalse($demo['is_active']);
        $this->assertContains('catalog.offers', array_column($response->json('features'), 'code'));
    }

    public function test_a_module_can_be_switched_off_from_the_panel(): void
    {
        $this->registerModule();

        $this->actingAs($this->adminWith(['modules.update']))
            ->putJson('/admin/api/modules/demo', ['is_enabled' => false])
            ->assertOk()
            ->assertJsonPath('module.is_active', false);

        $this->assertFalse(Nexor::modules()->enabled('demo'));
    }

    public function test_switching_modules_needs_the_permission(): void
    {
        $this->registerModule();

        $this->actingAs($this->adminWith(['modules.view']))
            ->putJson('/admin/api/modules/demo', ['is_enabled' => false])
            ->assertForbidden();
    }

    public function test_the_bootstrap_tells_the_panel_what_is_available(): void
    {
        config(['nexor.license' => 'lite']);

        $features = $this->actingAs($this->superAdmin())
            ->getJson('/admin/api/bootstrap')
            ->assertOk()
            ->assertJsonPath('license.value', 'lite')
            ->json('features');

        // В кодах функций есть точка, поэтому не через assertJsonPath.
        $this->assertFalse($features['catalog.offers']);
    }

    public function test_module_permissions_join_the_catalogue(): void
    {
        $this->registerModule();

        $this->assertArrayHasKey('demo', Permissions::definitions());
    }

    // ---------------------------------------------------- торговые предложения

    public function test_a_lite_catalog_gets_prices_but_no_offers(): void
    {
        config(['nexor.license' => 'lite']);

        $iblock = Iblock::factory()->create(['code' => 'katalog']);

        $this->actingAs($this->superAdmin())->putJson("/admin/api/iblocks/{$iblock->id}", [
            'iblock_type_id' => $iblock->iblock_type_id,
            'code' => $iblock->code,
            'name' => $iblock->name,
            'is_active' => '1',
            'is_catalog' => '1',
        ])->assertOk()->assertJsonPath('data.has_commerce', true)->assertJsonPath('data.has_offers', false);

        $this->assertNull($iblock->refresh()->offers_iblock_id);

        $keys = array_column($this->actingAs($this->superAdmin())
            ->getJson("/admin/api/iblocks/{$iblock->id}/schema")
            ->json('form_tabs'), 'key');

        $this->assertContains('price', $keys);
        $this->assertNotContains('offers', $keys);
    }

    public function test_offers_created_on_a_higher_license_stay_hidden_after_a_downgrade(): void
    {
        $iblock = Iblock::factory()->create(['code' => 'katalog']);

        $this->actingAs($this->superAdmin())->putJson("/admin/api/iblocks/{$iblock->id}", [
            'iblock_type_id' => $iblock->iblock_type_id,
            'code' => $iblock->code,
            'name' => $iblock->name,
            'is_active' => '1',
            'is_catalog' => '1',
        ])->assertOk();

        $product = IblockElement::factory()->for($iblock)->create();

        config(['nexor.license' => 'lite']);

        // Данные остаются, но на Lite ни вкладка, ни выборка их не видят.
        $this->assertNotNull($iblock->refresh()->offers_iblock_id);

        $this->actingAs($this->superAdmin())
            ->getJson("/admin/api/iblocks/{$iblock->id}/elements/{$product->id}/offers")
            ->assertNotFound();
    }
}
