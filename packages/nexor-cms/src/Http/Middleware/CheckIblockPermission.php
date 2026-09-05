<?php

namespace Nexor\Cms\Http\Middleware;

use Closure;
use Illuminate\Http\Request;
use Nexor\Cms\Models\Iblock;
use Symfony\Component\HttpFoundation\Response;

class CheckIblockPermission
{
    /**
     * Guard content routes with the permissions owned by the infoblock in the URL.
     *
     * Usage: `->middleware('iblock:update')` on a route with an `{iblock}` parameter.
     *
     * @param  Closure(Request): (Response)  $next
     */
    public function handle(Request $request, Closure $next, string $ability = 'view'): Response
    {
        $iblock = $request->route('iblock');

        if (! $iblock instanceof Iblock) {
            abort(404);
        }

        if (! $request->user()?->hasPermission($iblock->permissionCode($ability))) {
            abort(403, 'Нет прав на этот инфоблок.');
        }

        return $next($request);
    }
}
