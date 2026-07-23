<?php

namespace App\Http\Controllers\Auth;

use App\Http\Controllers\Controller;
use App\Http\Requests\Auth\ForgotPasswordRequest;
use App\Support\ApiResponse;
use Illuminate\Support\Facades\Password;

class ForgotPasswordController extends Controller
{
    public function create()
    {
        return view('auth.forgot-password');
    }

    public function store(ForgotPasswordRequest $request)
    {
        $user = \App\Models\User::query()->where('email', $request->input('email'))->whereNotNull('role_id')->first();
        if ($user) {
            Password::sendResetLink(['email' => $user->email]);
        }
        $message = 'Nếu email tồn tại, liên kết đặt lại mật khẩu đã được gửi.';

        if ($request->expectsJson()) {
            return ApiResponse::success(null, $message);
        }

        return back()->with('status', $message);
    }
}
