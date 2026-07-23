<?php

namespace App\Http\Middleware;

use Closure;
use Illuminate\Http\Request;

class EnsureUserIsAdmin
{
    /**
     * Handle an incoming request.
     *
     * @param  \Illuminate\Http\Request  $request
     * @param  \Closure(\Illuminate\Http\Request): (\Illuminate\Http\Response|\Illuminate\Http\RedirectResponse)  $next
     * @return \Illuminate\Http\Response|\Illuminate\Http\RedirectResponse
     */
    public function handle(Request $request, Closure $next)
    {
        $user = $request->user();

        if (! $user || ! $user->is_active || $user->role_id === null) {
            if (session()->has('impersonated_by')) {
                return redirect()->route('admin.login', [
                    'locale' => $request->route('locale') ?: config('app.locale', 'vi'),
                ])->with('error', 'Tài khoản này không có quyền truy cập trang quản trị.');
            }

            if ($user && ! $user->is_active) {
                auth()->logout();
                $request->session()->invalidate();
                $request->session()->regenerateToken();
            }

            abort(403, 'Unauthorized.');
        }

        return $next($request);
    }
}
