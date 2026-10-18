<?php

namespace App\Http\Middleware;

use App\Support\AiAccess;
use Closure;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\Response;

/**
 * The server-side lock of every AI route (ai.access[:feature]): AI buttons are always shown, so a
 * locked one is stopped here, with the reason AiAccess gives — JSON for the in-page tools, a page
 * that explains itself for a link opened directly, a flash on a form post.
 */
class EnsureAiAccess
{
    public function handle(Request $request, Closure $next, string $feature = 'ai_seo'): Response
    {
        $access = app(AiAccess::class)->check($request->user(), $feature);
        if ($access['allowed']) {
            return $next($request);
        }

        if ($request->expectsJson()) {
            return response()->json([
                'success' => false,
                'reason' => 'ai_locked',
                'locked' => $access,
                'message' => $access['message'],
            ], 403);
        }

        if ($request->isMethod('GET')) {
            return response()->view('admin.ai.locked', ['access' => $access], 403);
        }

        return back()->with('error', $access['message']);
    }
}
