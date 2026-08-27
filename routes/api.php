<?php

use App\Http\Controllers\Api\AuthController;
use App\Http\Controllers\Api\PublicAuthController;
use App\Http\Controllers\Api\PublicController;
use App\Http\Controllers\Api\UserAddressController;
use App\Http\Controllers\Api\WebhookController;
use App\Mail\ContactInquiryMail;
use App\Models\ContactSubmission;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Mail;
use Illuminate\Support\Facades\Route;
use Illuminate\Support\Facades\Validator;

Route::prefix('public')->middleware('apiLocale')->group(function () {
    Route::get('/health', [PublicController::class, 'health']);
    Route::get('/settings', [PublicController::class, 'settings']);
    Route::get('/languages', [PublicController::class, 'languages']);

    Route::post('/contact', function (Request $request) {
        $validator = Validator::make($request->all(), [
            'name' => 'required|string|max:255',
            'phone' => 'required|string|max:20',
            'email' => 'nullable|email|max:255',
            'message' => 'required|string|max:5000',
        ]);

        if ($validator->fails()) {
            return response()->json([
                'success' => false,
                'message' => 'Dữ liệu không hợp lệ.',
                'errors' => $validator->errors(),
            ], 422);
        }

        $inquiry = $request->only(['name', 'phone', 'email', 'message']);
        $meta = $request->except(['name', 'phone', 'email', 'message']);

        ContactSubmission::query()->create([
            ...$inquiry,
            'meta' => $meta !== [] ? $meta : null,
        ]);

        try {
            Mail::to(config('mail.seller'))->send(new ContactInquiryMail($inquiry));
        } catch (Exception $e) {
            Log::error('Contact form email failed: '.$e->getMessage());

            return response()->json([
                'success' => false,
                'message' => 'Không thể gửi yêu cầu vào lúc này. Vui lòng thử lại sau.',
            ], 500);
        }

        return response()->json([
            'success' => true,
            'message' => 'Yêu cầu báo giá của bạn đã được gửi thành công. Chúng tôi sẽ liên hệ lại sớm nhất!',
        ]);
    })->middleware('throttle:public-contact');

    Route::middleware('feature:catalog')->group(function () {
        Route::get('/categories', [PublicController::class, 'categories']);
        Route::get('/brands', [PublicController::class, 'brands']);
        Route::get('/products', [PublicController::class, 'products']);
        Route::get('/products/{id_or_slug}', [PublicController::class, 'productDetail']);
    });

    Route::middleware('feature:menu')->group(function () {
        Route::get('/menus/{key}', [PublicController::class, 'menu'])->where('key', '[a-z0-9-]+');
    });

    Route::middleware('feature:cms_page')->group(function () {
        Route::get('/post-categories', [PublicController::class, 'postCategories']);
        Route::get('/posts', [PublicController::class, 'posts']);
        Route::get('/posts/{id_or_slug}', [PublicController::class, 'postDetail']);
        Route::get('/pages', [PublicController::class, 'pages']);
        Route::get('/pages/{id_or_slug}', [PublicController::class, 'pageDetail']);
    });

    Route::post('/products/{id_or_slug}/reviews', [PublicController::class, 'storeReview'])
        ->middleware(['feature:catalog', 'feature:review', 'throttle:public-review']);

    // Vouchers
    Route::post('/vouchers/apply', [PublicController::class, 'applyVoucher'])->middleware('feature:voucher');

    // Checkout & Tracking
    Route::post('/orders/checkout', [PublicController::class, 'checkout'])
        ->middleware(['feature:catalog', 'feature:cart', 'throttle:public-checkout']);
    Route::get('/orders/track', [PublicController::class, 'trackOrder'])->middleware('throttle:public-tracking');
    Route::get('/payment/vnpay/ipn', [PublicController::class, 'vnpayIpn'])->middleware('throttle:webhook')->name('api.payment.vnpay.ipn');
    Route::get('/payment/vnpay/return', [PublicController::class, 'vnpayReturn'])->middleware('throttle:webhook')->name('api.payment.vnpay.return');

    // Customer Authentication
    Route::prefix('auth')->middleware('throttle:public-auth')->group(function () {
        Route::post('/register', [PublicAuthController::class, 'register']);
        Route::post('/login', [PublicAuthController::class, 'login']);
        Route::post('/forgot-password', [PublicAuthController::class, 'forgotPassword']);
        Route::post('/reset-password', [PublicAuthController::class, 'resetPassword']);

        Route::middleware(['auth:sanctum', 'abilities:customer', 'active-api'])->group(function () {
            Route::get('/me', [PublicAuthController::class, 'me']);
            Route::post('/logout', [PublicAuthController::class, 'logout']);
        });
    });

    // Guarded Orders history & Address management
    Route::middleware(['auth:sanctum', 'abilities:customer', 'active-api'])->group(function () {
        Route::get('/orders', [PublicController::class, 'orderHistory']);
        Route::get('/orders/{order_number}', [PublicController::class, 'orderDetail']);

        Route::get('/addresses', [UserAddressController::class, 'index']);
        Route::post('/addresses', [UserAddressController::class, 'store']);
        Route::put('/addresses/{address}', [UserAddressController::class, 'update']);
        Route::delete('/addresses/{address}', [UserAddressController::class, 'destroy']);
        Route::patch('/addresses/{address}/set-default', [UserAddressController::class, 'setDefault']);
    });

});

Route::prefix('admin')->group(function () {
    Route::post('/login', [AuthController::class, 'login'])->middleware('throttle:admin-login');

    Route::middleware(['auth:sanctum', 'abilities:admin', 'active-admin-api'])->group(function () {
        Route::get('/me', [AuthController::class, 'me']);
        Route::post('/logout', [AuthController::class, 'logout']);
    });
});

Route::post('/webhooks/ghtk', [WebhookController::class, 'handleGHTK'])->middleware('throttle:webhook');
Route::post('/webhooks/sepay', [WebhookController::class, 'handleSepay'])
    ->middleware('throttle:webhook')
    ->name('api.webhooks.sepay');
