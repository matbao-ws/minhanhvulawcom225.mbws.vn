<?php

namespace App\Http\Middleware;

use App\Services\LanguageRegistry;
use Closure;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\URL;
use Symfony\Component\HttpFoundation\Response;

class ValidateLocalizedAdminPath
{
    public function __construct(private readonly LanguageRegistry $languages) {}

    public function handle(Request $request, Closure $next): Response
    {
        $segments = $request->segments();
        if (($segments[1] ?? null) === 'admin' && ($segments[0] ?? null) !== 'api') {
            $locale = $this->languages->normalize((string) $segments[0]);
            if (! $this->languages->supportsRoute($locale)) {
                abort(404);
            }

            // A storefront-only language: send someone opening an admin page to the
            // admin in a language it is translated into. JSON calls and writes keep
            // their locale — the storefront editor on /ja/… posts to /ja/admin/…, and
            // block history reads the content locale from that URL. A redirect would
            // also turn a POST into a GET and drop its body.
            if (! $this->languages->supportsAdmin($locale)
                && $request->isMethodSafe()
                && ! $request->expectsJson()) {
                $segments[0] = $this->languages->adminLocale($locale);
                $query = $request->getQueryString();

                return redirect()->to(url(implode('/', $segments)).($query !== null ? '?'.$query : ''));
            }

            app()->setLocale($locale);
            config()->set('app.fallback_locale', $this->languages->fallbackLocale());
            URL::defaults(['locale' => $locale]);
        }

        return $next($request);
    }
}
