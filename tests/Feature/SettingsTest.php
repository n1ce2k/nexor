<?php

namespace Tests\Feature;

use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Storage;
use Nexor\Cms\Database\Seeders\SettingSeeder;
use Nexor\Cms\Models\Setting;
use Tests\Concerns\CreatesAdminUsers;
use Tests\TestCase;

class SettingsTest extends TestCase
{
    use CreatesAdminUsers, RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();

        $this->seed(SettingSeeder::class);
    }

    public function test_the_settings_screen_needs_the_view_permission(): void
    {
        $this->actingAs($this->adminWith())
            ->get(route('admin.settings.index'))
            ->assertForbidden();

        $this->actingAs($this->adminWith(['settings.view']))
            ->get(route('admin.settings.index'))
            ->assertOk()
            ->assertSee('Название сайта');
    }

    public function test_scalar_settings_are_saved(): void
    {
        $admin = $this->adminWith(['settings.view', 'settings.update']);

        $this->actingAs($admin)->put(route('admin.settings.update'), [
            'settings' => [
                'site__name' => 'NEXOR Test',
                'contacts__phone' => '+7 900 000-00-00',
                'site__maintenance' => '1',
            ],
        ])->assertRedirect();

        $this->assertSame('NEXOR Test', Setting::get('site.name'));
        $this->assertSame('+7 900 000-00-00', Setting::get('contacts.phone'));
        $this->assertTrue(Setting::get('site.maintenance'));
    }

    public function test_a_boolean_setting_falls_back_to_false_when_unchecked(): void
    {
        Setting::put('site.maintenance', '1');

        $admin = $this->adminWith(['settings.view', 'settings.update']);

        $this->actingAs($admin)->put(route('admin.settings.update'), [
            'settings' => ['site__name' => 'NEXOR'],
        ])->assertRedirect();

        $this->assertFalse(Setting::get('site.maintenance'));
    }

    public function test_an_image_setting_stores_the_uploaded_file(): void
    {
        Storage::fake('public');

        $admin = $this->adminWith(['settings.view', 'settings.update']);

        $this->actingAs($admin)->put(route('admin.settings.update'), [
            'settings' => ['site__name' => 'NEXOR'],
            'file_site__logo' => UploadedFile::fake()->image('logo.png'),
        ])->assertRedirect();

        $path = Setting::get('site.logo');

        $this->assertNotNull($path);
        Storage::disk('public')->assertExists($path);
    }

    public function test_saved_values_are_read_back_through_the_cache(): void
    {
        Setting::put('site.name', 'Первое');
        $this->assertSame('Первое', Setting::get('site.name'));

        Setting::put('site.name', 'Второе');
        $this->assertSame('Второе', Setting::get('site.name'));
    }
}
