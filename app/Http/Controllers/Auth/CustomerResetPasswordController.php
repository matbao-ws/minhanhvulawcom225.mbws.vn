<?php

namespace App\Http\Controllers\Auth;

use App\Http\Controllers\Controller;
use App\Http\Requests\Auth\ResetPasswordRequest;
use App\Models\User;
use App\Services\UserAccessService;
use Illuminate\Auth\Events\PasswordReset;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Facades\Password;
use Illuminate\Support\Str;

class CustomerResetPasswordController extends Controller
{
    public function create(string $token)
    {
        return view('auth.reset-password', [
            'token' => $token,
            'email' => request('email'),
            'formAction' => route('customer.password.update'),
            'loginUrl' => config('app.public_frontend_url') ?: url('/'),
        ]);
    }

    public function store(ResetPasswordRequest $request)
    {
        if (! User::query()->where('email', $request->input('email'))->whereNull('role_id')->exists()) {
            return back()->withErrors(['email' => 'Token khôi phục mật khẩu không hợp lệ hoặc đã hết hạn.']);
        }

        $status = Password::reset(
            $request->only('email', 'password', 'password_confirmation', 'token'),
            function (User $user) use ($request): void {
                if ($user->role_id !== null) {
                    return;
                }

                $user->forceFill([
                    'password' => Hash::make($request->input('password')),
                    'remember_token' => Str::random(60),
                ])->save();
                app(UserAccessService::class)->revoke($user);

                event(new PasswordReset($user));
            }
        );

        if ($status !== Password::PASSWORD_RESET) {
            return back()->withErrors(['email' => __($status)])->withInput();
        }

        return redirect()->to(config('app.public_frontend_url') ?: url('/'))
            ->with('status', __($status));
    }
}
