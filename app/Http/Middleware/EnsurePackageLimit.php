<?php

namespace App\Http\Middleware;

use App\Exceptions\PackageLimitExceeded;
use App\Services\Packages\PackageLimits;
use Closure;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\Response;

/**
 * Stops the user at the "create" button once the package limit is used up,
 * instead of letting them fill a whole form only to be refused on save.
 *
 * The model-level check in AppServiceProvider stays the real guarantee; this
 * is the early, friendlier stop for the create screens.
 */
class EnsurePackageLimit
{
    public function __construct(private readonly PackageLimits $limits) {}

    public function handle(Request $request, Closure $next, string $metric): Response
    {
        $remaining = $this->limits->remaining($metric);

        if ($remaining === null || $remaining > 0) {
            return $next($request);
        }

        $exception = new PackageLimitExceeded($metric, (int) $this->limits->limit($metric));

        if ($request->expectsJson()) {
            return response()->json(['success' => false, 'message' => $exception->getMessage()], 422);
        }

        // Back to the list the button was on; a direct hit on the URL lands on the dashboard.
        return redirect()->back(fallback: route('admin.dashboard'))
            ->with(PackageLimitExceeded::FLASH_KEY, $exception->getMessage());
    }
}
