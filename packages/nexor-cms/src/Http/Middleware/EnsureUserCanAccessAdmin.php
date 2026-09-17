<?php

namespace Nexor\Cms\Http\Middleware;

use Closure;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Nexor\Cms\Support\Permissions;
use Symfony\Component\HttpFoundation\Response;

class EnsureUserCanAccessAdmin
{
    /**
     * @param  Closure(Request): (Response)  $next
     */
    public function handle(Request $request, Closure $next): Response
    {
        $user = $request->user();

        if (! $user) {
            // Панель ходит в API фоном: ей нужен ответ, а не страница входа.
            return $request->expectsJson()
                ? response()->json(['message' => 'Сессия закончилась — войдите в панель заново.'], 401)
                : redirect()->guest(route('admin.login'));
        }

        if (! $user->is_active) {
            Auth::logout();
            $request->session()->invalidate();
            $request->session()->regenerateToken();

            return redirect()->route('admin.login')
                ->withErrors(['login' => 'Учётная запись заблокирована.']);
        }

        if (! $user->hasPermission(Permissions::ACCESS_ADMIN)) {
            abort(403, 'Нет доступа в панель управления.');
        }

        return $next($request);
    }
}
