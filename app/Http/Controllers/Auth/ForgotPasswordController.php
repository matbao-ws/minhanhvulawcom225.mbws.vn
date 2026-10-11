<?php

namespace App\Http\Controllers\Auth;

use App\Http\Controllers\Controller;
use App\Http\Requests\Auth\ForgotPasswordRequest;
use App\Models\User;
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
        $user = User::query()->where('email', $request->input('email'))->whereNotNull('role_id')->first();
        if ($user) {
            Password::sendResetLink(['email' => $user->email]);
        }
        $message = __('controller.auth.reset_link_sent');

        if ($request->expectsJson()) {
            return ApiResponse::success(null, $message);
        }

        return back()->with('status', $message);
    }
}
