<?php

namespace App\Http\Middleware;

use App\Support\FeatureGate;
use Closure;
use Illuminate\Http\Request;

class EnsureFeatureEnabled
{
    public function handle(Request $request, Closure $next, string $feature)
    {
        // Superadmin bypasses feature checks
        if (auth()->check() && auth()->user()->isSuperAdmin()) {
            return $next($request);
        }

        app(FeatureGate::class)->require($feature);

        return $next($request);
    }
}
