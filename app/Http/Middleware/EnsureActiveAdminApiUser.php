<?php

namespace App\Http\Middleware;

use Closure;
use Illuminate\Http\Request;

class EnsureActiveAdminApiUser
{
    public function handle(Request $request, Closure $next)
    {
        $user = $request->user('sanctum');
        if (! $user || ! $user->is_active || $user->role_id === null) {
            $user?->currentAccessToken()?->delete();

            return response()->json(['message' => 'Unauthenticated.'], 401);
        }

        return $next($request);
    }
}
