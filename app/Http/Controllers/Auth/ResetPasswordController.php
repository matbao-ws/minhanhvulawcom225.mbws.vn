<?php

namespace App\Http\Controllers\Auth;

use App\Http\Controllers\Controller;
use App\Http\Requests\Auth\ResetPasswordRequest;
use App\Support\ApiResponse;
use Illuminate\Auth\Events\PasswordReset;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Facades\Password;
use Illuminate\Support\Str;

class ResetPasswordController extends Controller
{
    public function create(string $locale, string $token)
    {
        return view('auth.reset-password', [
            'token' => $token,
            'email' => request('email'),
        ]);
    }

    public function store(ResetPasswordRequest $request)
    {
        $status = Password::reset(
            $request->only('email', 'password', 'password_confirmation', 'token'),
            function ($user) use ($request): void {
                $user->forceFill([
                    'password' => Hash::make($request->input('password')),
                    'remember_token' => Str::random(60),
                ])->save();

                event(new PasswordReset($user));
            }
        );

        $success = $status === Password::PASSWORD_RESET;
        $message = __($status);

        if ($request->expectsJson()) {
            return $success
                ? ApiResponse::success(['redirect' => route('admin.login')], $message)
                : ApiResponse::error($message, 422, ['email' => [$message]]);
        }

        return $success
            ? redirect()->route('admin.login')->with('status', $message)
            : back()->withErrors(['email' => $message])->withInput();
    }
}
