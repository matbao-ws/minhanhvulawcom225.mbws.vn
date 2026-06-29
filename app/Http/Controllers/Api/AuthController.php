<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Http\Requests\Auth\LoginRequest;
use App\Models\User;
use App\Support\ApiResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Hash;
use Illuminate\Validation\ValidationException;

class AuthController extends Controller
{
    public function login(LoginRequest $request)
    {
        $user = User::query()
            ->where('email', $request->input('email'))
            ->first();

        if (! $user) {
            throw ValidationException::withMessages([
                'email' => __('auth.login.email_not_found'),
            ]);
        }

        if (! $user->is_active) {
            throw ValidationException::withMessages([
                'email' => __('auth.login.inactive'),
            ]);
        }

        if ($user->role_id === null) {
            throw ValidationException::withMessages([
                'email' => __('auth.login.unauthorized'),
            ]);
        }


        if (! Hash::check($request->input('password'), $user->password)) {
            throw ValidationException::withMessages([
                'password' => __('auth.login.password_incorrect'),
            ]);
        }

        $user->forceFill([
            'last_login_at' => now(),
        ])->save();

        $token = \App\Support\JwtService::generateToken($user);

        return ApiResponse::success([
            'token_type' => 'Bearer',
            'access_token' => $token,
            'user' => [
                'id' => $user->id,
                'name' => $user->name,
                'email' => $user->email,
            ],
        ], __('auth.login.success'));
    }

    public function me(Request $request)
    {
        return ApiResponse::success([
            'user' => $request->user(),
        ]);
    }

    public function logout(Request $request)
    {
        if ($request->user() && method_exists($request->user(), 'currentAccessToken') && $request->user()->currentAccessToken()) {
            $request->user()->currentAccessToken()->delete();
        }

        return ApiResponse::success(null, __('auth.logout.success'));
    }
}
