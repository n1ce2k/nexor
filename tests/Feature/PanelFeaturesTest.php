<?php

namespace Tests\Feature;

use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\File;
use Nexor\Cms\Database\Seeders\SettingSeeder;
use Nexor\Cms\Models\Iblock;
use Nexor\Cms\Models\IblockType;
use Nexor\Cms\Models\MailTemplate;
use Nexor\Cms\Models\Setting;
use Nexor\Cms\Support\PageGenerator;
use Tests\Concerns\CreatesAdminUsers;
use Tests\TestCase;

/**
 * Maintenance mode, page scaffolding, editable settings, mail templates and the
 * developer console.
 */
class PanelFeaturesTest extends TestCase
{
    use CreatesAdminUsers, RefreshDatabase;

    /** Pages are written into a throwaway folder so the app's views stay clean. */
    protected string $pagesDirectory = 'nexor-test-pages';

    protected function setUp(): void
    {
        parent::setUp();

        config(['nexor.pages.directory' => $this->pagesDirectory]);
    }

    protected function tearDown(): void
    {
        File::deleteDirectory(resource_path('views/'.$this->pagesDirectory));

        parent::tearDown();
    }

    // ------------------------------------------------------------ maintenance

    public function test_maintenance_mode_closes_the_site_for_guests(): void
    {
        $this->seed(SettingSeeder::class);
        Setting::put('site.maintenance', '1');

        $this->get('/')
            ->assertStatus(503)
            ->assertSee('Скоро вернёмся');
    }

    public function test_a_signed_in_user_still_sees_the_site_during_maintenance(): void
    {
        $this->seed(SettingSeeder::class);
        Setting::put('site.maintenance', '1');

        $this->actingAs($this->adminWith())->get('/')->assertOk();
    }

    public function test_the_panel_stays_reachable_during_maintenance(): void
    {
        $this->seed(SettingSeeder::class);
        Setting::put('site.maintenance', '1');

        $this->get(route('admin.login'))->assertOk();
    }

    public function test_the_site_is_open_when_maintenance_is_off(): void
    {
        $this->seed(SettingSeeder::class);

        $this->get('/')->assertOk();
    }

    public function test_the_maintenance_page_shows_the_custom_message(): void
    {
        $this->seed(SettingSeeder::class);
        Setting::put('site.maintenance', '1');
        Setting::put('site.maintenance_message', 'Переезжаем на новый сервер');

        $this->get('/')->assertStatus(503)->assertSee('Переезжаем на новый сервер');
    }

    // ------------------------------------------------------------------ pages

    public function test_creating_an_infoblock_with_the_page_switch_scaffolds_a_blade_file(): void
    {
        $admin = $this->adminWith(['iblocks.create']);
        $type = IblockType::factory()->create();

        $this->actingAs($admin)->postJson('/admin/api/iblocks', [
            'iblock_type_id' => $type->id,
            'code' => 'services',
            'name' => 'Услуги',
            'has_page' => '1',
            'is_active' => '1',
        ])->assertCreated();

        $iblock = Iblock::query()->where('code', 'services')->firstOrFail();

        $this->assertTrue(PageGenerator::exists($iblock));
        $this->assertSame($this->pagesDirectory.'/services/index.blade.php', $iblock->page_path);
        $this->assertStringContainsString('<x-nexor::catalog.section iblock="services"', File::get(PageGenerator::path($iblock)));
    }

    public function test_no_file_is_written_when_the_page_switch_is_off(): void
    {
        $admin = $this->adminWith(['iblocks.create']);
        $type = IblockType::factory()->create();

        $this->actingAs($admin)->postJson('/admin/api/iblocks', [
            'iblock_type_id' => $type->id,
            'code' => 'plain',
            'name' => 'Без страницы',
            'has_page' => '0',
        ])->assertCreated();

        $this->assertFalse(PageGenerator::exists(Iblock::query()->where('code', 'plain')->firstOrFail()));
    }

    public function test_an_existing_page_is_never_overwritten(): void
    {
        $iblock = Iblock::factory()->create(['code' => 'about', 'has_page' => true]);

        PageGenerator::create($iblock);
        File::put(PageGenerator::path($iblock), 'мой собственный шаблон');

        PageGenerator::create($iblock);

        $this->assertSame('мой собственный шаблон', File::get(PageGenerator::path($iblock)));
    }

    public function test_the_public_route_serves_the_generated_page(): void
    {
        $this->seed(SettingSeeder::class);

        $iblock = Iblock::factory()->create(['code' => 'services', 'has_page' => true, 'is_active' => true]);
        PageGenerator::create($iblock);

        $this->get('/services')->assertOk()->assertSee($iblock->name);
    }

    // --------------------------------------------------------------- settings

    public function test_a_custom_setting_can_be_added_to_a_group(): void
    {
        $this->seed(SettingSeeder::class);
        $admin = $this->adminWith(['settings.view', 'settings.update']);

        $this->actingAs($admin)->postJson('/admin/api/settings/definitions', [
            'key' => 'contacts.telegram',
            'name' => 'Telegram',
            'type' => 'string',
            'group' => 'contacts',
            'sort' => 240,
        ])->assertCreated();

        $setting = Setting::query()->where('key', 'contacts.telegram')->firstOrFail();

        $this->assertFalse($setting->is_system);
        $this->assertSame('contacts', $setting->group);
    }

    public function test_a_select_setting_keeps_its_options(): void
    {
        $admin = $this->adminWith(['settings.view', 'settings.update']);

        $this->actingAs($admin)->postJson('/admin/api/settings/definitions', [
            'key' => 'contacts.city',
            'name' => 'Город',
            'type' => 'select',
            'group' => 'contacts',
            'options' => [
                ['value' => 'msk', 'label' => 'Москва'],
                ['value' => 'spb', 'label' => 'Санкт-Петербург'],
            ],
        ])->assertCreated();

        $this->assertCount(2, Setting::query()->where('key', 'contacts.city')->firstOrFail()->options);
    }

    public function test_a_system_setting_cannot_be_deleted(): void
    {
        $this->seed(SettingSeeder::class);
        $admin = $this->adminWith(['settings.view', 'settings.update']);
        $setting = Setting::query()->where('key', 'site.name')->firstOrFail();

        $this->actingAs($admin)
            ->deleteJson("/admin/api/settings/definitions/{$setting->id}")
            ->assertStatus(422);

        $this->assertModelExists($setting);
    }

    public function test_a_custom_setting_can_be_deleted(): void
    {
        $admin = $this->adminWith(['settings.view', 'settings.update']);
        $setting = Setting::query()->create([
            'key' => 'contacts.vk', 'name' => 'ВКонтакте', 'type' => 'string', 'group' => 'contacts',
        ]);

        $this->actingAs($admin)
            ->deleteJson("/admin/api/settings/definitions/{$setting->id}")
            ->assertOk();

        $this->assertModelMissing($setting);
    }

    public function test_a_password_setting_is_stored_encrypted_and_masked_in_the_api(): void
    {
        $this->seed(SettingSeeder::class);
        $admin = $this->adminWith(['settings.view', 'settings.update']);

        $this->actingAs($admin)->putJson('/admin/api/settings', [
            'settings' => ['mail__password' => 'super-secret'],
        ])->assertOk();

        $setting = Setting::query()->where('key', 'mail.password')->firstOrFail();

        $this->assertNotSame('super-secret', $setting->value);
        $this->assertSame('super-secret', $setting->decrypted());

        $response = $this->actingAs($admin)->getJson('/admin/api/settings')->assertOk();
        $masked = collect($response->json('data'))->firstWhere('key', 'mail.password');

        $this->assertSame(Setting::MASK, $masked['value']);
    }

    public function test_resubmitting_the_mask_leaves_a_secret_untouched(): void
    {
        $this->seed(SettingSeeder::class);
        $admin = $this->adminWith(['settings.view', 'settings.update']);

        Setting::put('mail.password', 'original');

        $this->actingAs($admin)->putJson('/admin/api/settings', [
            'settings' => ['mail__password' => Setting::MASK],
        ])->assertOk();

        $this->assertSame('original', Setting::query()->where('key', 'mail.password')->firstOrFail()->decrypted());
    }

    // ------------------------------------------------------------------- mail

    public function test_a_mail_template_can_be_created(): void
    {
        $admin = $this->adminWith(['mail.view', 'mail.update']);

        $this->actingAs($admin)->postJson('/admin/api/mail-templates', [
            'code' => 'ORDER_CREATED',
            'name' => 'Новый заказ',
            'from' => '#SITE_EMAIL#',
            'to' => '#EMAIL#',
            'subject' => 'Заказ №#ORDER_ID#',
            'body' => '<p>Здравствуйте, #NAME#!</p>',
            'body_type' => 'html',
            'is_active' => '1',
        ])->assertCreated();

        $template = MailTemplate::query()->where('code', 'ORDER_CREATED')->firstOrFail();

        $this->assertSame(['ORDER_ID', 'NAME'], $template->placeholders());
    }

    public function test_mail_templates_need_the_mail_permission(): void
    {
        $this->actingAs($this->adminWith())->getJson('/admin/api/mail-templates')->assertForbidden();

        $this->actingAs($this->adminWith(['mail.view']))->getJson('/admin/api/mail-templates')->assertOk();
    }

    public function test_placeholders_are_substituted_when_rendering(): void
    {
        $template = MailTemplate::query()->create([
            'code' => 'HELLO',
            'name' => 'Привет',
            'subject' => 'Здравствуйте, #NAME#',
            'body' => 'Ваш заказ #ORDER_ID# принят.',
            'body_type' => 'text',
        ]);

        $this->assertSame('Здравствуйте, Андрей', $template->render('subject', ['name' => 'Андрей']));
        $this->assertSame('Ваш заказ 42 принят.', $template->render('body', ['ORDER_ID' => 42]));
    }

    // ------------------------------------------------------------------ tools

    public function test_the_developer_console_is_off_by_default(): void
    {
        $this->actingAs($this->superAdmin())
            ->postJson('/admin/api/tools/sql', ['query' => 'SELECT 1'])
            ->assertForbidden();
    }

    public function test_the_sql_console_refuses_anyone_but_a_super_admin(): void
    {
        config(['nexor.tools.enabled' => true]);

        $this->actingAs($this->adminWith(['settings.update']))
            ->postJson('/admin/api/tools/sql', ['query' => 'SELECT 1'])
            ->assertForbidden();
    }

    public function test_a_super_admin_can_run_a_select(): void
    {
        config(['nexor.tools.enabled' => true]);

        Iblock::factory()->create(['name' => 'Новости']);

        $response = $this->actingAs($this->superAdmin())
            ->postJson('/admin/api/tools/sql', ['query' => 'select name from iblocks'])
            ->assertOk();

        $response->assertJsonPath('type', 'rows');
        $response->assertJsonPath('rows.0.name', 'Новости');
    }

    public function test_a_broken_query_reports_the_error_instead_of_throwing(): void
    {
        config(['nexor.tools.enabled' => true]);

        $this->actingAs($this->superAdmin())
            ->postJson('/admin/api/tools/sql', ['query' => 'select * from no_such_table'])
            ->assertStatus(422)
            ->assertJsonPath('type', 'error');
    }

    public function test_every_console_run_is_written_to_the_activity_log(): void
    {
        config(['nexor.tools.enabled' => true]);

        $this->actingAs($this->superAdmin())
            ->postJson('/admin/api/tools/sql', ['query' => 'select 1'])
            ->assertOk();

        $this->assertDatabaseHas('activity_logs', ['action' => 'tool.sql']);
    }

    public function test_the_php_console_needs_its_own_switch(): void
    {
        config(['nexor.tools.enabled' => true, 'nexor.tools.php' => false]);

        $this->actingAs($this->superAdmin())
            ->postJson('/admin/api/tools/php', ['code' => '1 + 1'])
            ->assertForbidden();
    }

    public function test_the_php_console_evaluates_an_expression(): void
    {
        config(['nexor.tools.enabled' => true, 'nexor.tools.php' => true]);

        $this->actingAs($this->superAdmin())
            ->postJson('/admin/api/tools/php', ['code' => '2 + 2'])
            ->assertOk()
            ->assertJsonPath('returned', '4');
    }
}
