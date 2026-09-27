<?php

namespace Tests\Feature;

use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Blade;
use Nexor\Cms\Models\CookieConsent;
use Nexor\Cms\Models\CookieCounter;
use Nexor\Cms\Support\Cookies;
use Tests\Concerns\CreatesAdminUsers;
use Tests\TestCase;

/**
 * Согласие на cookie: баннер, запись ответа и подключение счётчиков.
 *
 * Главное правило проверяется несколько раз с разных сторон: код счётчика
 * попадает в страницу только после согласия на его категорию.
 */
class CookieTest extends TestCase
{
    use CreatesAdminUsers, RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();

        Cookies::save(['enabled' => true]);
    }

    protected function counter(array $attributes = []): CookieCounter
    {
        return CookieCounter::query()->create($attributes + [
            'name' => 'Счётчик',
            'category' => 'analytics',
            'placement' => 'head',
            'code' => '<script>window.analyticsLoaded = 1;</script>',
            'is_active' => true,
        ]);
    }

    /** Ответ посетителя в том виде, в каком его хранит браузер. */
    protected function consented(bool $analytics = false, bool $marketing = false): array
    {
        return [Cookies::get('cookie_name') => json_encode([
            'technical' => true,
            'analytics' => $analytics,
            'marketing' => $marketing,
        ])];
    }

    // ------------------------------------------------------------- счётчики

    public function test_a_counter_stays_out_of_the_page_until_it_is_allowed(): void
    {
        $this->counter();

        $this->get('/')->assertOk()->assertDontSee('window.analyticsLoaded', false);
    }

    public function test_an_allowed_counter_gets_into_the_page(): void
    {
        $this->counter();

        $this->withUnencryptedCookies($this->consented(analytics: true))
            ->get('/')
            ->assertOk()
            ->assertSee('window.analyticsLoaded', false);
    }

    public function test_categories_do_not_leak_into_each_other(): void
    {
        $this->counter(['name' => 'Аналитика', 'code' => '<script>window.a = 1;</script>']);
        $this->counter(['name' => 'Маркетинг', 'category' => 'marketing', 'code' => '<script>window.m = 1;</script>']);

        $this->withUnencryptedCookies($this->consented(analytics: true))
            ->get('/')
            ->assertOk()
            ->assertSee('window.a = 1', false)
            ->assertDontSee('window.m = 1', false);
    }

    public function test_a_switched_off_counter_is_never_added(): void
    {
        $this->counter(['is_active' => false]);

        $this->withUnencryptedCookies($this->consented(analytics: true))
            ->get('/')
            ->assertOk()
            ->assertDontSee('window.analyticsLoaded', false);
    }

    public function test_the_metrika_counter_is_built_from_its_number(): void
    {
        Cookies::save(['metrika' => '12345678']);

        $this->withUnencryptedCookies($this->consented(analytics: true))
            ->get('/')
            ->assertOk()
            ->assertSee('mc.yandex.ru/metrika/tag.js', false)
            ->assertSee('ym(12345678', false);
    }

    public function test_the_admin_panel_gets_no_counters(): void
    {
        $this->counter();

        $this->actingAs($this->superAdmin())
            ->withUnencryptedCookies($this->consented(analytics: true))
            ->get('/admin')
            ->assertOk()
            ->assertDontSee('window.analyticsLoaded', false);
    }

    public function test_the_browser_decides_when_the_page_comes_from_cache(): void
    {
        Cookies::save(['js_mode' => true]);
        $this->counter();

        // В этом режиме коды уезжают в браузер списком, а не разметкой.
        $html = $this->get('/')->assertOk()->getContent();

        $this->assertStringContainsString('nexor-cookies-counters', $html);
        $this->assertStringContainsString('cookies.js', $html);
    }

    public function test_the_banner_files_are_served(): void
    {
        // Файлы отдаются всем: баннер видит как раз тот, кто ещё не вошёл.
        $this->get('/nexor/cookies/assets/cookies.js')->assertOk()->assertHeader('Content-Type', 'application/javascript; charset=UTF-8');
        $this->get('/nexor/cookies/assets/cookies.css')->assertOk();
        $this->get('/nexor/cookies/assets/.env')->assertNotFound();
    }

    // -------------------------------------------------------------- баннер

    public function test_the_banner_shows_until_the_visitor_answers(): void
    {
        $html = Blade::render('<x-nexor::cookies />');

        $this->assertStringContainsString('data-cookies-accept-all', $html);

        // Ответил — баннер больше не выводится вовсе, прятать нечего.
        $this->withUnencryptedCookies($this->consented())
            ->get('/')
            ->assertOk()
            ->assertDontSee('data-cookies-accept-all', false);
    }

    public function test_each_category_is_a_row_with_a_foldable_description(): void
    {
        $html = Blade::render('<x-nexor::cookies />');

        // Заголовок с галочкой, шеврон справа и описание под ним — по одному
        // на каждую включённую категорию.
        $this->assertSame(3, substr_count($html, 'data-cookies-toggle'));
        $this->assertSame(3, substr_count($html, 'nexor-cookies__desc'));
        $this->assertStringContainsString('data-cookies-category="analytics"', $html);
        $this->assertStringContainsString('data-cookies-category="marketing"', $html);

        // Выключенная категория не показывается вовсе.
        Cookies::save(['marketing_enabled' => false]);

        $this->assertStringNotContainsString(
            'data-cookies-category="marketing"',
            Blade::render('<x-nexor::cookies />'),
        );
    }

    public function test_a_switched_off_banner_renders_nothing(): void
    {
        Cookies::save(['enabled' => false]);

        $this->assertSame('', trim(Blade::render('<x-nexor::cookies />')));
    }

    // ------------------------------------------------------------ согласие

    public function test_an_answer_is_written_down_with_the_server_time(): void
    {
        $response = $this->postJson('/nexor/cookies', ['analytics' => true, 'marketing' => false])->assertOk();

        $consent = CookieConsent::query()->sole();

        $this->assertTrue($consent->preferences['analytics']);
        $this->assertFalse($consent->preferences['marketing']);
        $this->assertTrue($consent->preferences['technical']);
        $this->assertTrue($consent->created_at->isToday());

        // Ответ несёт разрешённые коды: счётчик включается без перезагрузки.
        $response->assertJsonPath('preferences.analytics', true);
        $this->assertArrayHasKey('head', $response->json('codes'));
    }

    public function test_the_address_is_kept_without_its_last_part(): void
    {
        $this->assertSame('192.168.10.0', Cookies::anonymise('192.168.10.37'));
        $this->assertSame('2a02:6b8:1:2::', Cookies::anonymise('2a02:6b8:1:2:3:4:5:6'));
        $this->assertNull(Cookies::anonymise(null));
    }

    public function test_nobody_can_answer_while_the_banner_is_off(): void
    {
        Cookies::save(['enabled' => false]);

        $this->postJson('/nexor/cookies', ['analytics' => true])->assertNotFound();
        $this->assertSame(0, CookieConsent::query()->count());
    }

    // --------------------------------------------------------------- панель

    public function test_the_screen_needs_its_permission(): void
    {
        $this->actingAs($this->adminWith([]))->getJson('/admin/api/cookies')->assertForbidden();

        $this->actingAs($this->adminWith(['cookies.view']))
            ->getJson('/admin/api/cookies')
            ->assertOk()
            ->assertJsonPath('settings.enabled', true);
    }

    public function test_a_counter_is_managed_from_the_panel(): void
    {
        $admin = $this->adminWith(['cookies.view', 'cookies.update']);

        $id = $this->actingAs($admin)->postJson('/admin/api/cookies/counters', [
            'name' => 'VK Пиксель',
            'category' => 'marketing',
            'placement' => 'body',
            'code' => '<script>window.vk = 1;</script>',
        ])->assertCreated()->json('data.id');

        $this->actingAs($admin)->deleteJson("/admin/api/cookies/counters/{$id}")->assertOk();

        $this->assertSame(0, CookieCounter::query()->count());
    }

    public function test_settings_are_saved_and_read_back(): void
    {
        $this->actingAs($this->adminWith(['cookies.view', 'cookies.update']))
            ->putJson('/admin/api/cookies', array_merge(Cookies::settings(), ['banner_title' => 'Про cookie', 'delay' => 2500]))
            ->assertOk()
            ->assertJsonPath('settings.banner_title', 'Про cookie');

        $this->assertSame(2500, Cookies::get('delay'));
    }

    public function test_the_log_is_closed_to_those_without_the_right(): void
    {
        $this->actingAs($this->adminWith(['cookies.view']))->getJson('/admin/api/cookies/consents')->assertForbidden();
        $this->actingAs($this->adminWith(['cookies.consents.view']))->getJson('/admin/api/cookies/consents')->assertOk();
    }

    public function test_old_answers_are_thrown_away(): void
    {
        CookieConsent::query()->create(['preferences' => ['technical' => true]]);
        CookieConsent::query()->create(['preferences' => ['technical' => true]])
            ->forceFill(['created_at' => now()->subYears(3)])->save();

        $this->artisan('nexor:cookies:prune', ['--months' => 24])->assertSuccessful();

        $this->assertSame(1, CookieConsent::query()->count());
    }
}
