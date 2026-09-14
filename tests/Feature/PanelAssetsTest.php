<?php

namespace Tests\Feature;

use Illuminate\Foundation\Testing\RefreshDatabase;
use Nexor\Cms\Support\Nexor;
use Nexor\Cms\Support\PanelAssets;
use Tests\Concerns\CreatesAdminUsers;
use Tests\TestCase;

/**
 * Админка приходит в пакете уже собранной и отдаётся из vendor.
 */
class PanelAssetsTest extends TestCase
{
    use CreatesAdminUsers, RefreshDatabase;

    /**
     * Любой файл сборки ядра из манифеста.
     */
    protected function builtFile(): string
    {
        $manifest = json_decode((string) file_get_contents(base_path('packages/nexor-cms/dist/manifest.json')), true);

        return $manifest['resources/js/panel/main.js']['file'];
    }

    public function test_the_panel_page_loads_the_packaged_build(): void
    {
        config(['nexor.panel.assets' => 'dist']);

        $this->actingAs($this->superAdmin())
            ->get('/admin')
            ->assertOk()
            ->assertSee('/admin/nexor-assets/nexor/'.$this->builtFile(), false)
            ->assertSee('/admin/nexor-assets/shop/panel.js?v=', false);
    }

    public function test_a_built_file_is_served_with_a_long_cache(): void
    {
        $response = $this->get('/admin/nexor-assets/nexor/'.$this->builtFile())->assertOk();

        $this->assertStringStartsWith('application/javascript', $response->headers->get('Content-Type'));
        $this->assertStringContainsString('immutable', $response->headers->get('Cache-Control'));
    }

    public function test_the_assets_need_no_login(): void
    {
        $this->get('/admin/nexor-assets/shop/panel.js')->assertOk();
    }

    public function test_nothing_outside_the_build_can_be_read(): void
    {
        $this->get('/admin/nexor-assets/nexor/../composer.json')->assertNotFound();
        $this->get('/admin/nexor-assets/nexor/..%2F..%2Fcomposer.json')->assertNotFound();
        $this->get('/admin/nexor-assets/nexor/manifest.json')->assertNotFound();
        $this->get('/admin/nexor-assets/unknown/panel.js')->assertNotFound();
    }

    public function test_a_switched_off_module_does_not_load_its_pages(): void
    {
        Nexor::modules()->setEnabled('shop', false);

        $this->assertStringNotContainsString('/shop/panel.js', PanelAssets::extensions()->toHtml());
    }
}
