<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Http\Resources\PublicUserResource;
use App\Models\User;
use App\Support\ApiResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Facades\Password;
use Illuminate\Support\Facades\Validator;
use Illuminate\Validation\Rules\Password as PasswordRule;
use Illuminate\Support\Str;
use App\Services\UserAccessService;

class PublicAuthController extends Controller
{
    /**
     * Customer registration.
     */
    public function register(Request $request)
    {
        $validator = Validator::make($request->all(), [
            'name' => 'required|string|max:255',
            'email' => 'required|string|email|max:255|unique:users',
            'password' => ['required', 'string', 'confirmed', PasswordRule::min(12)->mixedCase()->numbers()->symbols()],
        ]);

        if ($validator->fails()) {
            return ApiResponse::error(__('controller.api.email_unavailable'), 422, $validator->errors()->toArray());
        }

        $user = User::query()->create([
            'role_id' => null, // Customer role
            'name' => $request->input('name'),
            'email' => $request->email,
            'preferred_locale' => $request->attributes->get('content_locale', app()->getLocale()),
            'password' => Hash::make($request->input('password')),
            'is_active' => true,
        ]);

        $token = $user->createToken('customer-api', ['customer'])->plainTextToken;

        return ApiResponse::success([
            'user' => new PublicUserResource($user),
            'token' => $token,
        ], __('controller.api.registered'));
    }

    /**
     * Customer login.
     */
    public function login(Request $request)
    {
        $validator = Validator::make($request->all(), [
            'email' => 'required|string|email',
            'password' => 'required|string',
        ]);

        if ($validator->fails()) {
            return ApiResponse::error(__('controller.api.invalid_data'), 422, $validator->errors()->toArray());
        }

        $user = User::query()->where('email', $request->email)->first();

        if (! $user || ! $user->is_active || $user->role_id !== null || ! Hash::check($request->password, $user->password)) {
            return ApiResponse::error(__('controller.auth.invalid_credentials'), 401);
        }

        $user->update([
            'last_login_at' => now(),
            'preferred_locale' => $request->attributes->get('content_locale', app()->getLocale()),
        ]);

        $token = $user->createToken('customer-api', ['customer'])->plainTextToken;

        return ApiResponse::success([
            'user' => new PublicUserResource($user),
            'token' => $token,
        ], __('controller.api.logged_in'));
    }

    /**
     * Get authenticated customer profile.
     */
    public function me(Request $request)
    {
        return ApiResponse::success(new PublicUserResource($request->user('sanctum')));
    }

    /**
     * Customer logout.
     */
    public function logout(Request $request)
    {
        $user = $request->user('sanctum');
        if ($user && $user->currentAccessToken()) {
            $user->currentAccessToken()->delete();
        }

        return ApiResponse::success(null, __('controller.api.logged_out'));
    }

    /**
     * Send password reset link email.
     */
    public function forgotPassword(Request $request)
    {
        $validator = Validator::make($request->all(), [
            'email' => 'required|email',
        ]);

        if ($validator->fails()) {
            return ApiResponse::error(__('controller.api.invalid_data'), 422, $validator->errors()->toArray());
        }

        $user = User::query()->where('email', $request->input('email'))->whereNull('role_id')->first();
        if ($user) {
            Password::sendResetLink(['email' => $user->email]);
        }

        return ApiResponse::success(null, __('controller.auth.reset_link_sent'));
    }

    /**
     * Reset password using token.
     */
    public function resetPassword(Request $request)
    {
        $validator = Validator::make($request->all(), [
            'token' => 'required|string',
            'email' => 'required|email',
            'password' => ['required', 'string', 'confirmed', PasswordRule::min(12)->mixedCase()->numbers()->symbols()],
        ]);

        if ($validator->fails()) {
            return ApiResponse::error(__('controller.api.invalid_data'), 422, $validator->errors()->toArray());
        }

        if (! User::query()->where('email', $request->input('email'))->whereNull('role_id')->exists()) {
            return ApiResponse::error(__('controller.auth.invalid_reset_token'), 400);
        }

        $status = Password::reset(
            $request->only('email', 'password', 'password_confirmation', 'token'),
            function ($user, $password) {
                $user->forceFill([
                    'password' => Hash::make($password),
                    'remember_token' => Str::random(60),
                ])->save();
                app(UserAccessService::class)->revoke($user);
            }
        );

        if ($status === Password::PASSWORD_RESET) {
            return ApiResponse::success(null, __('controller.api.password_reset'));
        }

        return ApiResponse::error(__('controller.auth.invalid_reset_token'), 400);
    }
}
