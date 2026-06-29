<?php

use App\Http\Controllers\Api\AuthController;
use App\Http\Controllers\Api\PublicController;
use Illuminate\Support\Facades\Route;

Route::prefix('public')->group(function () {
    Route::get('/health', [PublicController::class, 'health']);
    Route::get('/settings', [PublicController::class, 'settings']);

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
    Route::post('/orders/checkout', [PublicController::class, 'checkout']);
    Route::get('/orders/track', [PublicController::class, 'trackOrder']);
    Route::get('/payment/vnpay/ipn', [PublicController::class, 'vnpayIpn'])->name('api.payment.vnpay.ipn');
    Route::post('/webhooks/sepay-addon', [\App\Http\Controllers\Api\AddonWebhookController::class, 'handleWebhook'])->name('api.webhooks.sepay-addon');

    // Customer Authentication
    Route::prefix('auth')->group(function () {
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
    Route::post('/login', [AuthController::class, 'login']);

    Route::middleware('auth:sanctum')->group(function () {
        Route::get('/me', [AuthController::class, 'me']);
        Route::post('/logout', [AuthController::class, 'logout']);
    });
});

Route::post('/webhooks/ghtk', [\App\Http\Controllers\Api\WebhookController::class, 'handleGHTK']);

