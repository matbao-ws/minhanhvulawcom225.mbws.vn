<?php

namespace App\Http\Middleware;

use App\Services\LanguageRegistry;
use Closure;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Schema;
use Illuminate\Support\Facades\URL;

class SetLocaleFromRoute
{
    public function __construct(private readonly LanguageRegistry $languages) {}

    public function handle(Request $request, Closure $next)
    {
        $locale = $this->languages->normalize((string) ($request->route('locale') ?: $this->languages->defaultLocale()));

        if (! $this->languages->supportsAdmin($locale)) {
            abort(404);
        }

        app()->setLocale($locale);
        config()->set('app.fallback_locale', $this->languages->fallbackLocale());
        URL::defaults(['locale' => $locale]);
        $request->session()->put('locale', $locale);

        if ($request->user() && Schema::hasColumn('users', 'preferred_locale') && $request->user()->preferred_locale !== $locale) {
            $request->user()->forceFill(['preferred_locale' => $locale])->saveQuietly();
        }

        return $next($request);
    }
}
