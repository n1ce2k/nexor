<?php

namespace Nexor\Cms\Http\Middleware;

use Closure;
use Illuminate\Http\Request;
use Nexor\Cms\Support\Install;
use Nexor\Cms\Support\License\Host;
use Nexor\Cms\Support\Licensing;
use Nexor\Cms\Support\Nexor;
use Symfony\Component\HttpFoundation\Response;

/**
 * Закрывает копию сайта, поднятую не на своём домене.
 *
 * Ключ подписан на домен, а база помнит, к какому сайту её привязали. Не сошлось
 * одно из двух — работает `nexor.license_guard`:
 *
 * - `panel` — закрыта только панель, сайт живёт (по умолчанию);
 * - `site` — закрыт весь сайт;
 * - `off` — ничего не закрывается, панель показывает плашку.
 *
 * Локальные адреса и окружения `local`/`testing` не проверяются вовсе, иначе
 * разработка встала бы на первом же запуске. Аварийный выход — `off` в `.env`
 * и `php artisan nexor:license:bind` из консоли.
 */
class EnsureLicensedHost
{
    /**
     * @param  Closure(Request): (Response)  $next
     */
    public function handle(Request $request, Closure $next): Response
    {
        $mode = strtolower(trim((string) config('nexor.license_guard', 'panel')));
        $host = Host::normalise($request->getHost());

        if ($mode === 'off' || Licensing::exempt($host)) {
            return $next($request);
        }

        if (! Licensing::misplaced($host)) {
            // Первый успешный запуск и закрепляет установку за этим доменом.
            Install::remember($host, Licensing::key()?->serial);

            return $next($request);
        }

        $panel = $this->isPanel($request);

        if ($mode !== 'site' && ! $panel) {
            return $next($request);
        }

        // На публичных страницах подробностей нет: там они никому не нужны.
        return response()->view('nexor::license.blocked', [
            'state' => Licensing::state($host),
            'detailed' => $panel,
        ], $panel ? 403 : 503, $panel ? [] : ['Retry-After' => 3600]);
    }

    /**
     * Панель и её экран входа.
     */
    protected function isPanel(Request $request): bool
    {
        $prefix = Nexor::routePrefix();

        return $request->is($prefix, $prefix.'/*');
    }
}
