<?php

use App\Http\Controllers\Api\PublicController;
use App\Http\Controllers\Auth\CustomerResetPasswordController;
use App\Http\Controllers\Client\SeoFileController;
use App\Services\LanguageRegistry;
use Illuminate\Support\Facades\Route;

// `/` is registered after the storefront routes (bootstrap/app.php): a theme home page wins.
Route::get('/login', fn () => redirect('/'.app(LanguageRegistry::class)->defaultLocale().'/admin/login'))->name('login');

Route::get('/api/docs', [PublicController::class, 'docs'])->name('api.docs');

// Dynamic so they follow APP_URL and the environment (staging is never indexed).
// There must be no robots.txt / sitemap.xml file in public/: the web server
// would serve it before Laravel is asked.
Route::get('/robots.txt', [SeoFileController::class, 'robots'])->name('seo.robots');
Route::get('/sitemap.xml', [SeoFileController::class, 'sitemap'])
    ->middleware('throttle:60,1')
    ->name('seo.sitemap');

Route::get('/customer/reset-password/{token}', [CustomerResetPasswordController::class, 'create'])->name('customer.password.reset');
Route::post('/customer/reset-password', [CustomerResetPasswordController::class, 'store'])
    ->middleware('throttle:public-auth')
    ->name('customer.password.update');

if (config('app.payment_mock_enabled') && app()->environment(['local', 'testing'])) {
    Route::get('/payment/vnpay/mock', [PublicController::class, 'vnpayMockPayment'])->name('vnpay.mock');
    Route::post('/payment/vnpay/mock/submit', [PublicController::class, 'vnpayMockSubmit'])
        ->middleware('throttle:10,1')
        ->name('vnpay.mock.submit');
}
