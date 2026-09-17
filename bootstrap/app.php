<?php

use Illuminate\Foundation\Application;
use Illuminate\Foundation\Configuration\Exceptions;
use Illuminate\Foundation\Configuration\Middleware;
use Illuminate\Http\Request;
use Nexor\Cms\Support\Nexor;

return Application::configure(basePath: dirname(__DIR__))
    ->withRouting(
        web: __DIR__.'/../routes/web.php',
        commands: __DIR__.'/../routes/console.php',
        health: '/up',
    )
    ->withMiddleware(function (Middleware $middleware): void {
        // Маршруты панели, алиасы middleware и gates приходят из NexorServiceProvider.
        // Гостя разворачивает сам пакет, поэтому redirectGuestsTo здесь не нужен:
        // приложение должно работать так же, как свежая установка.
        $middleware->redirectUsersTo(fn () => Nexor::home());
    })
    ->withExceptions(function (Exceptions $exceptions): void {
        $exceptions->shouldRenderJsonWhen(
            fn (Request $request) => $request->is('api/*') || $request->expectsJson(),
        );
    })->create();
