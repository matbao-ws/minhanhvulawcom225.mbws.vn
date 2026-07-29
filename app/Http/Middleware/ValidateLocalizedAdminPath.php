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
            if (! $this->languages->supportsAdmin($locale)) {
                abort(404);
            }

            app()->setLocale($locale);
            config()->set('app.fallback_locale', $this->languages->fallbackLocale());
            URL::defaults(['locale' => $locale]);
        }

        return $next($request);
    }
}
