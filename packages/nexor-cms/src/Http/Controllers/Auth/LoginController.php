<?php

namespace Nexor\Cms\Http\Controllers\Auth;

use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Illuminate\Validation\ValidationException;
use Illuminate\View\View;
use Nexor\Cms\Http\Controllers\Controller;
use Nexor\Cms\Support\ActivityLogger;
use Nexor\Cms\Support\Nexor;
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
            'login' => ['required', 'string', 'max:255'],
            'password' => ['required', 'string'],
        ], [], [
            'login' => 'логин или e-mail',
            'password' => 'пароль',
        ]);

        // Одно поле на логин и почту: с «собакой» — это почта, иначе логин.
        $field = filter_var($credentials['login'], FILTER_VALIDATE_EMAIL) ? 'email' : 'login';

        $attempt = [
            $field => $credentials['login'],
            'password' => $credentials['password'],
            'is_active' => true,
        ];

        if (! Auth::attempt($attempt, $request->boolean('remember'))) {
            ActivityLogger::log('login_failed', description: $credentials['login']);

            throw ValidationException::withMessages([
                'login' => 'Неверный логин, e-mail или пароль, либо учётная запись заблокирована.',
            ]);
        }

        $user = $request->user();

        if (! $user->hasPermission(Permissions::ACCESS_ADMIN)) {
            Auth::logout();
            $request->session()->invalidate();
            $request->session()->regenerateToken();

            throw ValidationException::withMessages([
                'login' => 'У этой учётной записи нет доступа в панель управления.',
            ]);
        }

        $request->session()->regenerate();

        $user->forceFill([
            'last_login_at' => now(),
            'last_login_ip' => $request->ip(),
        ])->save();

        ActivityLogger::log('login', $user);

        return redirect()->intended(Nexor::home())
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
