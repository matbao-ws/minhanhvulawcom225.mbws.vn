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

        $featureGate = app(FeatureGate::class);
        if (! $featureGate->enabled($feature)) {
            $message = $featureGate->unavailableMessage();

            if ($request->expectsJson() || $request->is('api/*')) {
                return response()->json([
                    'success' => false,
                    'message' => $message,
                ], 403);
            }

            return redirect()
                ->route('admin.dashboard')
                ->with('error', $message);
        }

        return $next($request);
    }
}
