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
        $status = Password::sendResetLink($request->only('email'));
        $success = $status === Password::RESET_LINK_SENT;
        $message = __($status);

        if ($request->expectsJson()) {
            return $success
                ? ApiResponse::success(null, $message)
                : ApiResponse::error($message, 422, ['email' => [$message]]);
        }

        return $success
            ? back()->with('status', $message)
            : back()->withErrors(['email' => $message])->withInput();
    }
}
