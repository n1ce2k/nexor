<?php

namespace Nexor\Cms\Http\Middleware;

use Closure;
use Illuminate\Http\Request;
use Nexor\Cms\Support\Nexor;
use Symfony\Component\HttpFoundation\Response;

/**
 * Пускает, только если функция или модуль доступны: `nexor.feature:shop.promocodes`.
 *
 * Недоступное отвечает 404, а не 403: на Lite-сайте страницы промокодов нет,
 * и незачем подсказывать, что она существует.
 */
class CheckFeature
{
    public function handle(Request $request, Closure $next, string ...$features): Response
    {
        foreach ($features as $feature) {
            abort_unless(Nexor::feature($feature), 404);
        }

        return $next($request);
    }
}
