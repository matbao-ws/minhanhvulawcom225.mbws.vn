<?php

use App\Http\Controllers\Api\AuthController;
use App\Http\Controllers\Api\PublicController;
use Illuminate\Support\Facades\Route;

Route::prefix('public')->group(function () {
    Route::get('/health', [PublicController::class, 'health']);
    Route::get('/settings', [PublicController::class, 'settings']);
    
    Route::post('/contact', function (\Illuminate\Http\Request $request) {
        $validator = \Illuminate\Support\Facades\Validator::make($request->all(), [
            'name' => 'required|string|max:255',
            'phone' => 'required|string|max:20',
            'email' => 'nullable|email|max:255',
            'message' => 'required|string|max:5000',
        ]);

        if ($validator->fails()) {
            return response()->json([
                'success' => false,
                'message' => 'Dữ liệu không hợp lệ.',
                'errors' => $validator->errors()
            ], 422);
        }

        $inquiry = $request->only(['name', 'phone', 'email', 'message']);

        try {
            \Illuminate\Support\Facades\Mail::to(config('mail.seller'))->send(new \App\Mail\ContactInquiryMail($inquiry));
        } catch (\Exception $e) {
            \Illuminate\Support\Facades\Log::error('Contact form email failed: ' . $e->getMessage());
            return response()->json([
                'success' => false,
                'message' => 'Không thể gửi yêu cầu vào lúc này. Vui lòng thử lại sau.'
            ], 500);
        }

        return response()->json([
            'success' => true,
            'message' => 'Yêu cầu báo giá của bạn đã được gửi thành công. Chúng tôi sẽ liên hệ lại sớm nhất!'
        ]);
    })->middleware('throttle:public-contact');

    // Catalog routes
    Route::get('/categories', [PublicController::class, 'categories']);
    Route::get('/brands', [PublicController::class, 'brands']);
    Route::get('/products', [PublicController::class, 'products']);
    Route::get('/products/{id_or_slug}', [PublicController::class, 'productDetail']);

    Route::middleware('feature:review')->group(function () {
        Route::post('/products/{id_or_slug}/reviews', [PublicController::class, 'storeReview']);
    });


    // Vouchers
    Route::post('/vouchers/apply', [PublicController::class, 'applyVoucher']);

    // Checkout & Tracking
    Route::post('/orders/checkout', [PublicController::class, 'checkout'])->middleware('throttle:public-checkout');
    Route::get('/orders/track', [PublicController::class, 'trackOrder'])->middleware('throttle:public-tracking');
    Route::get('/payment/vnpay/ipn', [PublicController::class, 'vnpayIpn'])->name('api.payment.vnpay.ipn');

    // Customer Authentication
    Route::prefix('auth')->middleware('throttle:public-auth')->group(function () {
        Route::post('/register', [\App\Http\Controllers\Api\PublicAuthController::class, 'register']);
        Route::post('/login', [\App\Http\Controllers\Api\PublicAuthController::class, 'login']);
        Route::post('/forgot-password', [\App\Http\Controllers\Api\PublicAuthController::class, 'forgotPassword']);
        Route::post('/reset-password', [\App\Http\Controllers\Api\PublicAuthController::class, 'resetPassword']);

        Route::middleware('auth:sanctum')->group(function () {
            Route::get('/me', [\App\Http\Controllers\Api\PublicAuthController::class, 'me']);
            Route::post('/logout', [\App\Http\Controllers\Api\PublicAuthController::class, 'logout']);
        });
    });

    // Guarded Orders history & Address management
    Route::middleware('auth:sanctum')->group(function () {
        Route::get('/orders', [PublicController::class, 'orderHistory']);
        Route::get('/orders/{order_number}', [PublicController::class, 'orderDetail']);

        Route::get('/addresses', [\App\Http\Controllers\Api\UserAddressController::class, 'index']);
        Route::post('/addresses', [\App\Http\Controllers\Api\UserAddressController::class, 'store']);
        Route::put('/addresses/{address}', [\App\Http\Controllers\Api\UserAddressController::class, 'update']);
        Route::delete('/addresses/{address}', [\App\Http\Controllers\Api\UserAddressController::class, 'destroy']);
        Route::patch('/addresses/{address}/set-default', [\App\Http\Controllers\Api\UserAddressController::class, 'setDefault']);
    });

});

Route::prefix('admin')->group(function () {
    Route::post('/login', [AuthController::class, 'login'])->middleware('throttle:admin-login');

    Route::middleware('auth:sanctum')->group(function () {
        Route::get('/me', [AuthController::class, 'me']);
        Route::post('/logout', [AuthController::class, 'logout']);
    });
});

Route::post('/webhooks/ghtk', [\App\Http\Controllers\Api\WebhookController::class, 'handleGHTK'])->middleware('throttle:webhook');
