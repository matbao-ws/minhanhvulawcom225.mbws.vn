<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Http\Resources\PublicUserResource;
use App\Models\User;
use App\Services\UserAccessService;
use App\Support\ApiResponse;
use App\Support\PasswordCheck;
use Illuminate\Database\UniqueConstraintViolationException;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Facades\Password;
use Illuminate\Support\Facades\Validator;
use Illuminate\Support\Str;
use Illuminate\Validation\Rules\Password as PasswordRule;

class PublicAuthController extends Controller
{
    /**
     * Customer registration.
     */
    public function register(Request $request)
    {
        $email = self::normalizeEmail($request->input('email'));
        $validator = Validator::make(['email' => $email] + $request->all(), [
            // No markup, no control or invisible characters: the name is shown in emails and the admin.
            'name' => ['required', 'string', 'min:2', 'max:255', 'not_regex:/[<>\p{C}]/u'],
            // bail: the uniqueness lookup only runs on a well-formed email (an array used to reach it: 500).
            'email' => ['bail', 'required', 'string', 'email:rfc', 'max:255', function (string $attribute, mixed $value, \Closure $fail) {
                if (self::emailTaken((string) $value)) {
                    $fail(__('controller.api.email_unavailable'));
                }
            }],
            // max:64 keeps it under bcrypt's 72-byte limit even with multi-byte characters.
            'password' => ['required', 'string', 'max:64', 'confirmed', 'different:email', PasswordRule::min(12)->mixedCase()->numbers()->symbols()],
        ]);

        if ($validator->fails()) {
            return ApiResponse::error(__('controller.api.invalid_data'), 422, $validator->errors()->toArray());
        }

        try {
            // One unit: a failure after the account row (the token) leaves no half-made account behind.
            [$user, $token] = DB::transaction(function () use ($request, $email) {
                $user = User::query()->create([
                    'role_id' => null, // Customer role
                    'name' => $request->input('name'),
                    'email' => $email,
                    'preferred_locale' => $request->attributes->get('content_locale', app()->getLocale()),
                    'password' => Hash::make($request->input('password')),
                    'is_active' => true,
                ]);

                return [$user, $user->createToken('customer-api', ['customer'])->plainTextToken];
            });
        } catch (UniqueConstraintViolationException) {
            // A double submit: the other request created this account a moment ago.
            return ApiResponse::error(__('controller.api.invalid_data'), 422, ['email' => [__('controller.api.email_unavailable')]]);
        }

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
        $email = self::normalizeEmail($request->input('email'));
        $validator = Validator::make(['email' => $email] + $request->all(), [
            'email' => 'required|string|email|max:255',
            // Bounded: hashing a megabyte-long "password" is a cheap way to load the server.
            'password' => 'required|string|max:255',
        ]);

        if ($validator->fails()) {
            return ApiResponse::error(__('controller.api.invalid_data'), 422, $validator->errors()->toArray());
        }

        $user = User::query()->whereRaw('LOWER(email) = ?', [$email])->first();

        if (! PasswordCheck::matches($user, (string) $request->password) || ! $user->is_active || $user->role_id !== null) {
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

    /** Emails are stored trimmed and lowercase, so Khach@Example.com and khach@example.com are one account. */
    private static function normalizeEmail(mixed $email): mixed
    {
        return is_string($email) ? Str::lower(trim($email)) : $email;
    }

    /** Case-insensitive on every database, including accounts saved before emails were lowercased. */
    private static function emailTaken(string $email): bool
    {
        return User::query()->whereRaw('LOWER(email) = ?', [$email])->exists();
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
