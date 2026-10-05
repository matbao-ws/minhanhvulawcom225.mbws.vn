<?php

namespace App\Http\Middleware;

use Closure;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\Response;

/**
 * Extension admin routes only: drop {locale} from the route parameters once it has done its job
 * (ValidateLocalizedAdminPath set the app locale and the URL default before routing). Controller
 * parameters are passed by position, so without this an extension's destroy(Note $note) would get
 * "vi" — the core's own controllers take `string $locale` first instead.
 */
class ForgetRouteLocale
{
    public function handle(Request $request, Closure $next): Response
    {
        $request->route()?->forgetParameter('locale');

        return $next($request);
    }
}
