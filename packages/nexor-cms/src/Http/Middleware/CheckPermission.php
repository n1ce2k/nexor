<?php

namespace Nexor\Cms\Http\Middleware;

use Closure;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\Response;

class CheckPermission
{
    /**
     * Allow the request when the user holds at least one of the given permissions.
     *
     * Usage: `->middleware('permission:users.view,users.update')`
     *
     * @param  Closure(Request): (Response)  $next
     */
    public function handle(Request $request, Closure $next, string ...$permissions): Response
    {
        $user = $request->user();

        if (! $user || ! $user->hasAnyPermission($permissions)) {
            abort(403, 'Недостаточно прав для этого действия.');
        }

        return $next($request);
    }
}
