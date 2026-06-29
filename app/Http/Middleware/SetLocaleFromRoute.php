<?php

namespace App\Http\Middleware;

use Closure;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\URL;

class SetLocaleFromRoute
{
    public function handle(Request $request, Closure $next)
    {
        $locale = $request->route('locale') ?: config('app.locale', 'vi');
        $supportedLocales = array_keys(config('laravellocalization.supportedLocales', []));

        if (! in_array($locale, $supportedLocales, true)) {
            abort(404);
        }

        app()->setLocale($locale);
        URL::defaults(['locale' => $locale]);

        return $next($request);
    }
}
