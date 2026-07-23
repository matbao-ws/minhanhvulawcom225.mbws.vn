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

        if (! $user || ! $user->is_active || $user->role_id === null || ! Hash::check($request->input('password'), $user->password)) {
            throw ValidationException::withMessages([
                'email' => ['Email hoặc mật khẩu không chính xác.'],
            ]);
        }

        $user->forceFill([
            'last_login_at' => now(),
        ])->save();

        $token = $user->createToken('admin-api', ['admin'])->plainTextToken;

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
            'user' => $request->user('sanctum')->only(['id', 'name', 'email', 'last_login_at']),
        ]);
    }

    public function logout(Request $request)
    {
        $user = $request->user('sanctum');
        if ($user && $user->currentAccessToken()) {
            $user->currentAccessToken()->delete();
        }

        return ApiResponse::success(null, __('auth.logout.success'));
    }
}
