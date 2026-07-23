<?php

namespace App\Http\Middleware;

use Closure;
use Illuminate\Http\Request;

class EnsureActiveApiUser
{
    public function handle(Request $request, Closure $next)
    {
        $user = $request->user('sanctum');
        if (! $user || ! $user->is_active) {
            $user?->currentAccessToken()?->delete();

            return response()->json(['message' => 'Unauthenticated.'], 401);
        }

        return $next($request);
    }
}
