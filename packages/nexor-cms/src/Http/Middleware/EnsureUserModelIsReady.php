<?php

namespace Nexor\Cms\Http\Middleware;

use Closure;
use Illuminate\Http\Request;
use Nexor\Cms\Support\Nexor;
use Nexor\Cms\Support\UserModelSetup;
use Symfony\Component\HttpFoundation\Response;

/**
 * Не пускает в панель, пока модель пользователя не готова.
 *
 * Без трейта `HasRoles` панель падает на первом же `hasPermission()` где-то в
 * потрохах фреймворка, и по такой ошибке никто не догадается, что делать.
 * Экран говорит прямо: чего не хватает и какой командой это дописать.
 */
class EnsureUserModelIsReady
{
    /**
     * @param  Closure(Request): (Response)  $next
     */
    public function handle(Request $request, Closure $next): Response
    {
        $missing = UserModelSetup::missing();

        if ($missing === []) {
            return $next($request);
        }

        $message = 'Модель '.Nexor::userModel().' не подготовлена к работе с панелью: '
            .'выполните php artisan nexor:user-model.';

        // Панель ходит в API фоном: ей нужен ответ, а не страница.
        if ($request->expectsJson()) {
            return response()->json(['message' => $message], 503);
        }

        return response()->view('nexor::admin.setup', [
            'model' => Nexor::userModel(),
            'missing' => $missing,
            'snippet' => UserModelSetup::snippet(),
        ], 503);
    }
}
