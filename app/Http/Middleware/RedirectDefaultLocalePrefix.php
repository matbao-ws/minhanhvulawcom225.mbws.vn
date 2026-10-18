<?php

namespace App\Http\Middleware;

use App\Support\LocaleUrl;
use Closure;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\Response;

/**
 * `/vi/chinh-sach/x` moved to `/chinh-sach/x` when vi is the default language: a permanent
 * redirect, so links already shared and pages search engines know keep their ranking. Storefront
 * routes only; the admin keeps its prefix.
 */
class RedirectDefaultLocalePrefix
{
    public function handle(Request $request, Closure $next): Response
    {
        if (! LocaleUrl::enabled() || ! $request->isMethod('GET') && ! $request->isMethod('HEAD')) {
            return $next($request);
        }

        $locale = strtolower((string) $request->route('locale'));
        if ($locale !== LocaleUrl::defaultLocale()) {
            return $next($request);
        }

        $path = LocaleUrl::stripDefault('/'.ltrim($request->path(), '/'));
        $query = $request->getQueryString();

        return redirect()->to(url($path).($query !== null && $query !== '' ? '?'.$query : ''), 301);
    }
}
