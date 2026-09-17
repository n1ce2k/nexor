<?php

namespace Tests\Feature;

use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Nexor\Cms\Models\ActivityLog;
use Nexor\Cms\Support\Nexor;
use Tests\Concerns\CreatesAdminUsers;
use Tests\TestCase;

class AdminAuthTest extends TestCase
{
    use CreatesAdminUsers, RefreshDatabase;

    public function test_login_page_is_reachable_without_authentication(): void
    {
        $this->get(route('admin.login'))
            ->assertOk()
            ->assertSee('Панель управления');
    }

    public function test_guests_are_redirected_from_the_dashboard(): void
    {
        $this->get(route('admin.dashboard'))->assertRedirect(route('admin.login'));
    }

    /**
     * В чистом Laravel маршрута `login` нет, а стандартный middleware `auth`
     * ведёт именно на него — установка падала с «Route [login] not defined».
     */
    public function test_the_panel_sends_guests_to_its_own_login_without_a_login_route(): void
    {
        $this->assertFalse(app('router')->has('login'));

        $this->get(Nexor::home())->assertRedirect(route('admin.login'));
        $this->get(route('admin.dashboard'))->assertRedirect(route('admin.login'));
        $this->get('/admin/users')->assertRedirect(route('admin.login'));
        $this->getJson('/admin/api/bootstrap')->assertUnauthorized();
    }

    public function test_signing_out_works_even_for_a_guest(): void
    {
        $this->post(route('admin.logout'))->assertRedirect(route('admin.login'));
    }

    public function test_a_user_with_panel_access_can_sign_in(): void
    {
        $user = $this->adminWith();

        $this->post(route('admin.login'), [
            'login' => $user->email,
            'password' => 'password',
        ])->assertRedirect(Nexor::home());

        $this->assertAuthenticatedAs($user);
        $this->assertNotNull($user->fresh()->last_login_at);
        $this->assertDatabaseHas('activity_logs', ['action' => 'login', 'user_id' => $user->id]);
    }

    public function test_a_user_can_sign_in_with_the_login_instead_of_the_email(): void
    {
        $user = $this->adminWith();
        $user->update(['login' => 'andrey']);

        $this->post(route('admin.login'), [
            'login' => 'andrey',
            'password' => 'password',
        ])->assertRedirect(Nexor::home());

        $this->assertAuthenticatedAs($user);
    }

    public function test_an_unknown_login_is_rejected(): void
    {
        $this->adminWith();

        $this->post(route('admin.login'), [
            'login' => 'nobody',
            'password' => 'password',
        ])->assertSessionHasErrors('login');

        $this->assertGuest();
    }

    public function test_wrong_credentials_are_rejected_and_logged(): void
    {
        $user = $this->adminWith();

        $this->post(route('admin.login'), [
            'login' => $user->email,
            'password' => 'not-the-password',
        ])->assertSessionHasErrors('login');

        $this->assertGuest();
        $this->assertSame(1, ActivityLog::query()->where('action', 'login_failed')->count());
    }

    public function test_a_blocked_user_cannot_sign_in(): void
    {
        $user = $this->adminWith();
        $user->update(['is_active' => false]);

        $this->post(route('admin.login'), [
            'login' => $user->email,
            'password' => 'password',
        ])->assertSessionHasErrors('login');

        $this->assertGuest();
    }

    public function test_a_user_without_panel_access_is_signed_out_again(): void
    {
        $user = User::factory()->create(['is_active' => true]);

        $this->post(route('admin.login'), [
            'login' => $user->email,
            'password' => 'password',
        ])->assertSessionHasErrors('login');

        $this->assertGuest();
    }

    public function test_signing_out_ends_the_session(): void
    {
        $user = $this->adminWith();

        $this->actingAs($user)
            ->post(route('admin.logout'))
            ->assertRedirect(route('admin.login'));

        $this->assertGuest();
    }

    public function test_the_default_panel_renders_for_an_admin(): void
    {
        $this->actingAs($this->adminWith())
            ->get(Nexor::home())
            ->assertOk()
            ->assertSee('id="nexor-panel"', false);
    }

    public function test_the_classic_panel_still_renders(): void
    {
        $this->actingAs($this->adminWith())
            ->get(route('admin.dashboard'))
            ->assertOk()
            ->assertSee('Рабочий стол');
    }
}
