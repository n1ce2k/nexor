<?php

namespace Nexor\Cms\Http\Middleware;

use Closure;
use Illuminate\Http\Request;
use Nexor\Cms\Models\Setting;
use Nexor\Cms\Support\Nexor;
use Symfony\Component\HttpFoundation\Response;

/**
 * Closes the public site while `site.maintenance` is on.
 *
 * Signed-in users pass through so the site can be checked before it reopens;
 * the panel itself is never covered, or an admin could lock themselves out.
 */
class CheckMaintenanceMode
{
    /**
     * @param  Closure(Request): (Response)  $next
     */
    public function handle(Request $request, Closure $next): Response
    {
        if (! Setting::get('site.maintenance', false)) {
            return $next($request);
        }

        if ($request->user() || $this->isPanel($request)) {
            return $next($request);
        }

        return response()->view('nexor::site.maintenance', [
            'siteName' => Setting::get('site.name', config('app.name')),
            'message' => Setting::get('site.maintenance_message'),
        ], 503, ['Retry-After' => 3600]);
    }

    /**
     * The admin panel and its login screen stay reachable.
     */
    protected function isPanel(Request $request): bool
    {
        $prefix = Nexor::routePrefix();

        return $request->is($prefix, $prefix.'/*');
    }
}
