<?php

namespace Nexor\Cms\Http\Controllers\Auth;

use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Nexor\Cms\Http\Controllers\Controller;
use Nexor\Cms\Support\ActivityLogger;
use Nexor\Cms\Support\Nexor;

/**
 * One-time sign-in link for local development.
 *
 * Lets a developer open the panel without typing a password — useful for
 * automated checks and screenshots. Deliberately narrow:
 *
 *   - the route only exists outside production and only while
 *     `nexor.login_link.enabled` is on;
 *   - the URL is signed, so it cannot be forged, and expires in minutes;
 *   - each use is written to the activity log.
 */
class LoginLinkController extends Controller
{
    public function __invoke(Request $request, string $account): RedirectResponse
    {
        abort_unless(self::enabled(), 404);

        $user = Nexor::newUser()->newQuery()->whereKey($account)->first();

        abort_if($user === null || ! $user->is_active, 403, 'Учётная запись недоступна.');

        Auth::login($user);
        $request->session()->regenerate();

        ActivityLogger::log('login', $user, 'Вход по одноразовой ссылке');

        return redirect()->intended(Nexor::home());
    }

    /**
     * Never available in production, whatever the config says.
     */
    public static function enabled(): bool
    {
        return ! app()->isProduction() && (bool) config('nexor.login_link.enabled', false);
    }
}
