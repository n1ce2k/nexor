<?php

namespace Tests\Feature;

use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Storage;
use Nexor\Cms\Database\Seeders\SettingSeeder;
use Nexor\Cms\Models\Setting;
use Nexor\Cms\Support\Cookies;
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

    public function test_a_logo_is_stored_as_a_file(): void
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

    public function test_a_file_setting_takes_documents_too(): void
    {
        Storage::fake('public');
        Setting::query()->create(['key' => 'contacts.price', 'type' => 'file', 'group' => 'contacts', 'name' => 'Прайс']);

        $this->actingAs($this->adminWith(['settings.view', 'settings.update']))
            ->post('/admin/api/settings', [
                '_method' => 'PUT',
                'file_contacts__price' => UploadedFile::fake()->create('price.pdf', 120, 'application/pdf'),
            ])
            ->assertOk();

        Storage::disk('public')->assertExists((string) Setting::get('contacts.price'));
    }

    public function test_a_file_setting_refuses_files_that_would_run_on_the_site(): void
    {
        Storage::fake('public');
        Setting::query()->create(['key' => 'contacts.price', 'type' => 'file', 'group' => 'contacts', 'name' => 'Прайс']);

        // Файл ложится в публичное хранилище: .php и .html оттуда исполнились
        // бы на домене сайта.
        foreach (['shell.php' => 'text/x-php', 'page.html' => 'text/html'] as $name => $mime) {
            $this->actingAs($this->adminWith(['settings.view', 'settings.update']))
                ->post('/admin/api/settings', [
                    '_method' => 'PUT',
                    'file_contacts__price' => UploadedFile::fake()->create($name, 1, $mime),
                ], ['Accept' => 'application/json'])
                ->assertUnprocessable();
        }

        $this->assertNull(Setting::get('contacts.price'));
    }

    public function test_an_svg_is_taken_but_loses_everything_that_runs(): void
    {
        Storage::fake('public');

        $svg = <<<'SVG'
            <?xml version="1.0" encoding="UTF-8"?>
            <svg xmlns="http://www.w3.org/2000/svg" xmlns:xlink="http://www.w3.org/1999/xlink" viewBox="0 0 10 10" onload="alert(1)">
                <script>alert(document.cookie)</script>
                <style>@import url(http://evil.test/x.css); .logo { fill: #2563eb; }</style>
                <a xlink:href="javascript:alert(2)"><circle class="logo" cx="5" cy="5" r="4" onclick="alert(3)"/></a>
                <foreignObject><body xmlns="http://www.w3.org/1999/xhtml"><img src="x" onerror="alert(4)"/></body></foreignObject>
                <use href="http://evil.test/sprite.svg#icon"/>
                <path d="M1 1h8v8H1z" fill="none"/>
            </svg>
            SVG;

        $this->actingAs($this->adminWith(['settings.view', 'settings.update']))
            ->post('/admin/api/settings', [
                '_method' => 'PUT',
                'file_site__logo' => UploadedFile::fake()->createWithContent('logo.svg', $svg),
            ])
            ->assertOk();

        $path = (string) Setting::get('site.logo');
        $stored = Storage::disk('public')->get($path);

        $this->assertStringEndsWith('.svg', $path);

        // Рисунок на месте.
        $this->assertStringContainsString('<circle', $stored);
        $this->assertStringContainsString('M1 1h8v8H1z', $stored);
        $this->assertStringContainsString('fill: #2563eb', $stored);

        // Всё, что выполнилось бы на домене сайта, вырезано.
        foreach (['<script', 'onload', 'onclick', 'onerror', 'javascript:', 'foreignObject', '@import', 'evil.test'] as $danger) {
            $this->assertStringNotContainsString($danger, $stored);
        }
    }

    public function test_a_file_that_only_calls_itself_svg_is_refused(): void
    {
        Storage::fake('public');
        $admin = $this->adminWith(['settings.view', 'settings.update']);

        // Сущности из DOCTYPE — чтение чужих файлов и «миллиард смеха».
        $bomb = '<?xml version="1.0"?><!DOCTYPE svg [<!ENTITY x SYSTEM "file:///etc/passwd">]><svg xmlns="http://www.w3.org/2000/svg">&x;</svg>';

        foreach (['<html><body>не svg</body></html>', $bomb] as $content) {
            $this->actingAs($admin)
                ->post('/admin/api/settings', [
                    '_method' => 'PUT',
                    'file_site__logo' => UploadedFile::fake()->createWithContent('logo.svg', $content),
                ], ['Accept' => 'application/json'])
                ->assertUnprocessable();
        }

        $this->assertNull(Setting::get('site.logo'));
    }

    public function test_the_type_is_offered_as_a_file(): void
    {
        $this->assertArrayHasKey('file', Setting::types());
        $this->assertArrayNotHasKey('image', Setting::types());

        // Логотип и favicon — тоже файлы.
        $this->assertSame('file', Setting::query()->where('key', 'site.logo')->value('type'));
    }

    public function test_a_setting_of_the_old_image_type_still_takes_a_file(): void
    {
        Storage::fake('public');
        Setting::query()->where('key', 'site.logo')->update(['type' => 'image']);

        $this->actingAs($this->adminWith(['settings.view', 'settings.update']))
            ->post('/admin/api/settings', [
                '_method' => 'PUT',
                'file_site__logo' => UploadedFile::fake()->image('logo.png'),
            ])
            ->assertOk();

        Storage::disk('public')->assertExists((string) Setting::get('site.logo'));
    }

    // ------------------------------------------------- служебные значения модулей

    public function test_module_values_stay_off_the_settings_screen(): void
    {
        Cookies::save(['enabled' => true, 'banner_title' => 'Про cookie']);

        // Модуль пишет в общую таблицу, но у него своя группа и свой экран.
        $this->assertSame('cookies', Setting::query()->where('key', 'cookies.enabled')->value('group'));

        $keys = $this->actingAs($this->adminWith(['settings.view']))
            ->getJson('/admin/api/settings')
            ->assertOk()
            ->json('data.*.key');

        $this->assertNotContains('cookies.enabled', $keys);
        $this->assertNotContains('cookies.banner_title', $keys);
        $this->assertContains('site.name', $keys);
    }

    public function test_saving_the_settings_screen_keeps_module_values(): void
    {
        Cookies::save(['enabled' => true, 'banner_title' => 'Про cookie']);

        // Классический экран сохраняет всё, что видит, — значения модуля он
        // раньше обнулял, потому что форма их не присылала.
        $this->actingAs($this->adminWith(['settings.view', 'settings.update']))
            ->put(route('admin.settings.update'), ['settings' => ['site__name' => 'NEXOR']])
            ->assertRedirect();

        $this->assertTrue(Cookies::get('enabled'));
        $this->assertSame('Про cookie', Cookies::get('banner_title'));
    }

    public function test_saved_values_are_read_back_through_the_cache(): void
    {
        Setting::put('site.name', 'Первое');
        $this->assertSame('Первое', Setting::get('site.name'));

        Setting::put('site.name', 'Второе');
        $this->assertSame('Второе', Setting::get('site.name'));
    }
}
