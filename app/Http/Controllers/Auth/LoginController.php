<?php

namespace App\Http\Controllers\Auth;

use App\Http\Controllers\Controller;
use App\Http\Requests\Auth\LoginRequest;
use App\Models\User;
use App\Support\ApiResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\Hash;
use Illuminate\Validation\ValidationException;

class LoginController extends Controller
{
    public function create()
    {
        if (Auth::check()) {
            return redirect()->route('admin.dashboard');
        }

        return view('auth.login');
    }

    public function store(LoginRequest $request)
    {
        if (Auth::check()) {
            if ($request->expectsJson()) {
                return ApiResponse::success([
                    'redirect' => route('admin.dashboard'),
                ], __('auth.login.success'));
            }

            return redirect()->route('admin.dashboard');
        }

        $user = User::query()
            ->where('email', $request->input('email'))
            ->first();

        if (! $user || ! $user->is_active || $user->role_id === null || ! Hash::check($request->input('password'), $user->password)) {
            throw ValidationException::withMessages([
                'email' => ['Email hoặc mật khẩu không chính xác.'],
            ]);
        }

        Auth::login($user, $request->remember());

        $request->session()->regenerate();

        $user->forceFill([
            'last_login_at' => now(),
        ])->save();

        if ($request->expectsJson()) {
            return ApiResponse::success([
                'redirect' => redirect()->intended(route('admin.dashboard'))->getTargetUrl(),
            ], __('auth.login.success'));
        }

        return redirect()->intended(route('admin.dashboard'));
    }

    public function destroy(Request $request)
    {
        Auth::logout();

        $request->session()->invalidate();
        $request->session()->regenerateToken();

        return redirect()->route('admin.login');
    }
}
