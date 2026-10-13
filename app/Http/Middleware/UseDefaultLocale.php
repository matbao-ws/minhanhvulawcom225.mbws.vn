<?php

namespace App\Http\Middleware;

use App\Support\LocaleUrl;
use Closure;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\Response;

/**
 * The storefront routes without a language prefix (`/chinh-sach/x`) serve the default language.
 * Their controllers keep their `(string $locale, …)` signature: the locale is put first among the
 * route parameters, as the prefixed routes have it.
 */
class UseDefaultLocale
{
    public function handle(Request $request, Closure $next): Response
    {
        if (! LocaleUrl::enabled()) {
            // Prefix kept on this site: these addresses do not exist, except the root, which
            // still opens the admin as before.
            return $request->path() === '/' ? redirect('/admin') : abort(404);
        }

        $route = $request->route();
        if ($route && ! array_key_exists('locale', (array) $route->parameters)) {
            $route->parameters = ['locale' => LocaleUrl::defaultLocale()] + (array) $route->parameters;
        }

        return $next($request);
    }
}
