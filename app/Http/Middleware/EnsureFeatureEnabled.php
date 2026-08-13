<?php

namespace App\Http\Middleware;

use App\Support\FeatureGate;
use Closure;
use Illuminate\Http\Request;

class EnsureFeatureEnabled
{
    /**
     * Passes when *any* of the listed codes is enabled ("feature:a,b"), which is
     * what a screen serving several features needs — payment configuration is
     * meaningful while either COD or online payment is on. Requiring several at
     * once stays expressible by stacking the middleware: ['feature:a','feature:b'].
     */
    public function handle(Request $request, Closure $next, string ...$features)
    {
        // Superadmin bypasses feature checks
        if (auth()->check() && auth()->user()->isSuperAdmin()) {
            return $next($request);
        }

        $featureGate = app(FeatureGate::class);
        $enabled = false;
        foreach ($features as $feature) {
            if ($featureGate->enabled($feature)) {
                $enabled = true;
                break;
            }
        }

        if (! $enabled) {
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
