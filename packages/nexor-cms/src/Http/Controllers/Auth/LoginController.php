<?php

namespace Nexor\Cms\Http\Controllers\Auth;

use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Illuminate\Validation\ValidationException;
use Illuminate\View\View;
use Nexor\Cms\Http\Controllers\Controller;
use Nexor\Cms\Support\ActivityLogger;
use Nexor\Cms\Support\Permissions;

class LoginController extends Controller
{
    public function create(): View
    {
        return view('nexor::admin.auth.login');
    }

    public function store(Request $request): RedirectResponse
    {
        $credentials = $request->validate([
            'email' => ['required', 'string', 'email'],
            'password' => ['required', 'string'],
        ], [], [
            'email' => 'e-mail',
            'password' => 'пароль',
        ]);

        if (! Auth::attempt($credentials + ['is_active' => true], $request->boolean('remember'))) {
            ActivityLogger::log('login_failed', description: $credentials['email']);

            throw ValidationException::withMessages([
                'email' => 'Неверный e-mail или пароль, либо учётная запись заблокирована.',
            ]);
        }

        $user = $request->user();

        if (! $user->hasPermission(Permissions::ACCESS_ADMIN)) {
            Auth::logout();
            $request->session()->invalidate();
            $request->session()->regenerateToken();

            throw ValidationException::withMessages([
                'email' => 'У этой учётной записи нет доступа в панель управления.',
            ]);
        }

        $request->session()->regenerate();

        $user->forceFill([
            'last_login_at' => now(),
            'last_login_ip' => $request->ip(),
        ])->save();

        ActivityLogger::log('login', $user);

        return redirect()->intended(route('admin.dashboard'))
            ->with('success', 'Добро пожаловать, '.$user->name.'!');
    }

    public function destroy(Request $request): RedirectResponse
    {
        ActivityLogger::log('logout', $request->user());

        Auth::logout();
        $request->session()->invalidate();
        $request->session()->regenerateToken();

        return redirect()->route('admin.login');
    }
}
