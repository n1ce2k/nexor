<?php

namespace Tests\Feature;

use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\URL;
use Nexor\Cms\Http\Controllers\Auth\LoginLinkController;
use Nexor\Cms\Support\Nexor;
use Tests\Concerns\CreatesAdminUsers;
use Tests\TestCase;

/**
 * The passwordless sign-in link exists for local development, so its guard
 * rails matter more than its happy path.
 */
class LoginLinkTest extends TestCase
{
    use CreatesAdminUsers, RefreshDatabase;

    public function test_a_signed_link_signs_the_user_in(): void
    {
        $admin = $this->adminWith();

        $this->get($this->link($admin))->assertRedirect(Nexor::home());

        $this->assertAuthenticatedAs($admin);
    }

    public function test_a_tampered_signature_is_rejected(): void
    {
        $admin = $this->adminWith();

        $this->get($this->link($admin).'x')->assertForbidden();

        $this->assertGuest();
    }

    public function test_an_expired_link_is_rejected(): void
    {
        $admin = $this->adminWith();

        $url = URL::temporarySignedRoute('admin.login-link', now()->subMinute(), ['account' => $admin->id]);

        $this->get($url)->assertForbidden();
        $this->assertGuest();
    }

    public function test_a_blocked_account_cannot_be_used(): void
    {
        $admin = $this->adminWith();
        $admin->update(['is_active' => false]);

        $this->get($this->link($admin))->assertForbidden();
        $this->assertGuest();
    }

    public function test_the_sign_in_is_written_to_the_activity_log(): void
    {
        $admin = $this->adminWith();

        $this->get($this->link($admin));

        $this->assertDatabaseHas('activity_logs', [
            'user_id' => $admin->id,
            'description' => 'Вход по одноразовой ссылке',
        ]);
    }

    public function test_the_feature_follows_its_config_switch(): void
    {
        $this->assertTrue(LoginLinkController::enabled());

        config(['nexor.login_link.enabled' => false]);

        $this->assertFalse(LoginLinkController::enabled());
    }

    protected function link($user): string
    {
        return URL::temporarySignedRoute('admin.login-link', now()->addMinutes(10), ['account' => $user->id]);
    }
}
