<?php

namespace App\Http\Middleware;

use App\Support\CoreUpdate\CoreUpdateAccess;
use Closure;
use Illuminate\Http\Request;

class EnsureCoreUpdateAccess
{
    public function __construct(private readonly CoreUpdateAccess $access) {}

    public function handle(Request $request, Closure $next)
    {
        abort_unless($this->access->allows($request->user()), 403, 'Unauthorized.');

        return $next($request);
    }
}
