<?php

namespace App\Http\Controllers\Auth;

use App\Http\Controllers\Controller;
use App\Http\Requests\Auth\ResetPasswordRequest;
use App\Support\ApiResponse;
use Illuminate\Auth\Events\PasswordReset;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Facades\Password;
use Illuminate\Support\Str;
use App\Services\UserAccessService;

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
        if (! \App\Models\User::query()->where('email', $request->input('email'))->whereNotNull('role_id')->exists()) {
            return $request->expectsJson()
                ? ApiResponse::error('Token khôi phục mật khẩu không hợp lệ hoặc đã hết hạn.', 422)
                : back()->withErrors(['email' => 'Token khôi phục mật khẩu không hợp lệ hoặc đã hết hạn.']);
        }

        $status = Password::reset(
            $request->only('email', 'password', 'password_confirmation', 'token'),
            function ($user) use ($request): void {
                $user->forceFill([
                    'password' => Hash::make($request->input('password')),
                    'remember_token' => Str::random(60),
                ])->save();
                app(UserAccessService::class)->revoke($user);

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
