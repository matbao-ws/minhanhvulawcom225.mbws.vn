<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Models\User;
use App\Support\ApiResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Facades\Password;
use Illuminate\Support\Facades\Validator;

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
            'password' => 'required|string|min:8|confirmed',
        ]);

        if ($validator->fails()) {
            return ApiResponse::error('Dữ liệu không hợp lệ.', 422, $validator->errors()->toArray());
        }

        $user = User::query()->create([
            'role_id' => null, // Customer role
            'name' => $request->name,
            'email' => $request->email,
            'password' => Hash::make($request->password),
            'is_active' => true,
        ]);

        $token = \App\Support\JwtService::generateToken($user);

        return ApiResponse::success([
            'user' => $user,
            'token' => $token,
        ], 'Đăng ký tài khoản thành công.');
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
            return ApiResponse::error('Dữ liệu không hợp lệ.', 422, $validator->errors()->toArray());
        }

        $user = User::query()->where('email', $request->email)->first();

        if (!$user || !Hash::check($request->password, $user->password)) {
            return ApiResponse::error('Email hoặc mật khẩu không chính xác.', 401);
        }

        if (!$user->is_active) {
            return ApiResponse::error('Tài khoản đã bị khóa.', 403);
        }

        $user->update(['last_login_at' => now()]);

        $token = \App\Support\JwtService::generateToken($user);

        return ApiResponse::success([
            'user' => $user,
            'token' => $token,
        ], 'Đăng nhập thành công.');
    }

    /**
     * Get authenticated customer profile.
     */
    public function me(Request $request)
    {
        return ApiResponse::success($request->user());
    }

    /**
     * Customer logout.
     */
    public function logout(Request $request)
    {
        if ($request->user() && method_exists($request->user(), 'currentAccessToken') && $request->user()->currentAccessToken()) {
            $request->user()->currentAccessToken()->delete();
        }

        return ApiResponse::success(null, 'Đăng xuất thành công.');
    }

    /**
     * Send password reset link email.
     */
    public function forgotPassword(Request $request)
    {
        $validator = Validator::make($request->all(), [
            'email' => 'required|email|exists:users,email',
        ]);

        if ($validator->fails()) {
            return ApiResponse::error('Email không tồn tại trên hệ thống.', 422, $validator->errors()->toArray());
        }

        $status = Password::sendResetLink($request->only('email'));

        if ($status === Password::RESET_LINK_SENT) {
            return ApiResponse::success(null, 'Email khôi phục mật khẩu đã được gửi.');
        }

        return ApiResponse::error('Không thể gửi email khôi phục mật khẩu.', 500);
    }

    /**
     * Reset password using token.
     */
    public function resetPassword(Request $request)
    {
        $validator = Validator::make($request->all(), [
            'token' => 'required|string',
            'email' => 'required|email|exists:users,email',
            'password' => 'required|string|min:8|confirmed',
        ]);

        if ($validator->fails()) {
            return ApiResponse::error('Dữ liệu không hợp lệ.', 422, $validator->errors()->toArray());
        }

        $status = Password::reset(
            $request->only('email', 'password', 'password_confirmation', 'token'),
            function ($user, $password) {
                $user->forceFill([
                    'password' => Hash::make($password)
                ])->save();
            }
        );

        if ($status === Password::PASSWORD_RESET) {
            return ApiResponse::success(null, 'Đổi mật khẩu mới thành công.');
        }

        return ApiResponse::error('Token khôi phục mật khẩu không hợp lệ hoặc đã hết hạn.', 400);
    }
}
