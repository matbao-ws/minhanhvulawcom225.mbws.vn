<?php

namespace App\Http\Middleware;

use App\Services\Packages\PackageSync;
use Closure;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\Response;

/**
 * Keeps the package fresh on hosting without a cron.
 *
 * The lookup runs in terminate(), after the response has gone out, and at most
 * once per sync TTL; an admin never waits on the internal tool.
 */
class SyncPackageInfo
{
    public function __construct(private readonly PackageSync $sync) {}

    public function handle(Request $request, Closure $next): Response
    {
        return $next($request);
    }

    public function terminate(Request $request, Response $response): void
    {
        $this->sync->syncIfStale();
    }
}
